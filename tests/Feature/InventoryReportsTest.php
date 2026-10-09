<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_are_available_to_operators_and_admins_and_guests_are_redirected(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
        $this->get(route('reports.stocks.export'))->assertRedirect(route('login'));

        $operator = User::factory()->create(['role' => 'operator']);
        $this->actingAs($operator)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee(route('reports.stock'))
            ->assertSee(route('reports.inbound'));
        $this->get(route('reports.sales.export'))->assertOk();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee(route('reports.stock'))
            ->assertSee(route('reports.inbound'))
            ->assertSee(route('reports.sales'));
    }

    public function test_stock_report_filters_per_product_warehouse_pair_and_uses_current_balance(): void
    {
        [$admin, $warehouseA, $warehouseB, $productA, $productB] = $this->reportFixtures();
        Stock::create(['warehouse_id' => $warehouseA->id, 'product_id' => $productA->id, 'quantity' => 7]);
        Stock::create(['warehouse_id' => $warehouseB->id, 'product_id' => $productA->id, 'quantity' => 3]);
        Stock::create(['warehouse_id' => $warehouseA->id, 'product_id' => $productB->id, 'quantity' => 11]);

        $response = $this->actingAs($admin)->get(route('reports.stocks'));
        $response->assertOk()
            ->assertSee('7')
            ->assertSee('3')
            ->assertSee('11')
            ->assertSee($warehouseA->name)
            ->assertSee($warehouseB->name);

        $warehouseFilter = $this->get(route('reports.stocks', ['warehouse_id' => $warehouseA->id]));
        $warehouseFilter->assertOk()->assertSee('7')->assertSee('11');
        $this->assertStringNotContainsString($warehouseB->name, $this->tableBody($warehouseFilter->getContent()));

        $productFilter = $this->get(route('reports.stocks', ['product_id' => $productB->id]));
        $productFilter->assertOk()->assertSee($productB->sku);
        $this->assertStringNotContainsString($productA->sku, $this->tableBody($productFilter->getContent()));
    }

    public function test_stock_report_tracks_balance_after_cancelled_inbound(): void
    {
        [$admin, $warehouse, , $product] = $this->reportFixtures();
        $operator = User::factory()->create(['role' => 'operator']);
        Stock::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 4]);
        $transaction = Transaction::create([
            'trx_no' => 'IN-REPORT-CANCEL',
            'type' => 'IN',
            'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-10-08',
            'status' => 'active',
            'user_id' => $operator->id,
        ]);
        $item = TransactionItem::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'unit_price' => 100,
            'subtotal' => 400,
        ]);
        StockService::applyTransaction($transaction, [$item]);

        $this->actingAs($admin)
            ->post(route('transactions.cancel', $transaction))
            ->assertRedirect(route('transactions.show', $transaction));

        $this->actingAs($admin)
            ->get(route('reports.stocks', ['warehouse_id' => $warehouse->id]))
            ->assertOk()
            ->assertSee($product->sku)
            ->assertSee('4');
    }

    public function test_inbound_report_filters_dates_warehouse_and_product_and_excludes_cancelled_history(): void
    {
        [$admin, $warehouseA, $warehouseB, $productA, $productB, $customer, $operator] = $this->reportFixtures(true);
        $matching = $this->makeTransaction($operator, $warehouseA, 'IN', 'IN-RPT-A', '2026-10-05', [
            [$productA, 2, 125, 250],
            [$productB, 3, 50, 150],
        ]);
        $cancelled = $this->makeTransaction($operator, $warehouseA, 'IN', 'IN-RPT-CANCELLED', '2026-10-05', [
            [$productA, 1, 125, 125],
        ], null, 'cancelled');
        $this->makeTransaction($operator, $warehouseB, 'IN', 'IN-RPT-B', '2026-10-08', [
            [$productA, 1, 125, 125],
        ]);

        $response = $this->actingAs($admin)->get(route('reports.inbounds', [
            'from' => '2026-10-05',
            'to' => '2026-10-05',
            'warehouse_id' => $warehouseA->id,
            'product_id' => $productA->id,
        ]));
        $response->assertOk()
            ->assertSee($matching->trx_no)
            ->assertSee($productA->sku)
            ->assertDontSee($cancelled->trx_no)
            ->assertDontSee('IN-RPT-B');
        $this->assertStringNotContainsString($productB->sku, $this->tableBody($response->getContent()));
    }

    public function test_inbound_report_rejects_invalid_date_ranges_and_unknown_filters(): void
    {
        [$admin, $warehouse] = $this->reportFixtures();

        $this->actingAs($admin)->get(route('reports.inbounds', [
            'from' => '2026-10-10',
            'to' => '2026-10-09',
        ]))->assertSessionHasErrors('to');

        $this->get(route('reports.sales', ['warehouse_id' => 99999]))
            ->assertSessionHasErrors('warehouse_id');

        $this->get(route('reports.sales', ['product_id' => 'not-an-id']))
            ->assertSessionHasErrors('product_id');

        $this->assertNotNull($warehouse);
    }

    public function test_sales_report_only_shows_active_sales(): void
    {
        [$admin, $warehouse, , $product, , $customer, $operator] = $this->reportFixtures(true);
        $active = $this->makeTransaction($operator, $warehouse, 'OUT', 'OUT-RPT-A', '2026-10-08', [
            [$product, 2, 125.50, 251],
        ], $customer);
        $cancelled = $this->makeTransaction($operator, $warehouse, 'OUT', 'OUT-RPT-B', '2026-10-09', [
            [$product, 1, 125.50, 125.50],
        ], $customer, 'cancelled');
        $product->update(['selling_price' => 9999]);

        $response = $this->actingAs($admin)->get(route('reports.sales'));
        $response->assertOk()
            ->assertSee($active->trx_no)
            ->assertDontSee($cancelled->trx_no)
            ->assertSee('251,00');

        $this->get(route('reports.sales', [
            'from' => '2026-10-09',
            'to' => '2026-10-09',
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
        ]))->assertOk()
            ->assertDontSee('OUT-RPT-B')
            ->assertDontSee('OUT-RPT-A');
    }

    public function test_report_csv_exports_have_headers_filter_rows_and_escape_formulas_and_special_text(): void
    {
        [$admin, $warehouseA, $warehouseB, $product, , $customer, $operator] = $this->reportFixtures(true);
        $product->update(['name' => '=SUM(1,1) "unsafe"']);
        Stock::create(['warehouse_id' => $warehouseA->id, 'product_id' => $product->id, 'quantity' => 4]);
        Stock::create(['warehouse_id' => $warehouseB->id, 'product_id' => $product->id, 'quantity' => 9]);
        $this->makeTransaction($operator, $warehouseA, 'IN', 'IN-CSV', '2026-10-09', [
            [$product, 2, 12.5, 25],
        ]);
        $this->makeTransaction($operator, $warehouseA, 'OUT', 'OUT-CSV', '2026-10-09', [
            [$product, 1, 15.5, 15.5],
        ], $customer);

        $stockCsv = $this->actingAs($admin)->get(route('reports.stocks.export', [
            'warehouse_id' => $warehouseA->id,
        ]))->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $stockCsv);
        $stockRows = $this->csvRows($stockCsv);
        $this->assertSame(['Gudang', 'SKU', 'Nama Produk', 'Kategori', 'Qty'], $stockRows[0]);
        $this->assertSame("'=SUM(1,1) \"unsafe\"", $stockRows[1][2]);
        $this->assertSame('4', $stockRows[1][4]);
        $this->assertCount(2, $stockRows);

        $inboundCsv = $this->get(route('reports.inbounds.export', [
            'from' => '2026-10-09',
            'to' => '2026-10-09',
            'warehouse_id' => $warehouseA->id,
            'product_id' => $product->id,
        ]))->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
        $inboundRows = $this->csvRows($inboundCsv);
        $this->assertSame(['No Trx', 'Tanggal', 'Gudang', 'SKU', 'Nama Produk', 'Qty', 'Harga', 'Subtotal'], $inboundRows[0]);
        $this->assertSame('IN-CSV', $inboundRows[1][0]);

        $salesCsv = $this->get(route('reports.sales.export', [
            'from' => '2026-10-09',
            'to' => '2026-10-09',
            'warehouse_id' => $warehouseA->id,
            'product_id' => $product->id,
        ]))->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
        $salesRows = $this->csvRows($salesCsv);
        $this->assertSame(['No Trx', 'Tanggal', 'Pelanggan', 'Gudang', 'SKU', 'Nama', 'Qty', 'Harga', 'Subtotal'], $salesRows[0]);
        $this->assertSame('OUT-CSV', $salesRows[1][0]);
        $this->assertSame("'=SUM(1,1) \"unsafe\"", $salesRows[1][5]);
    }

    public function test_empty_report_exports_still_include_csv_headers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $csv = $this->actingAs($admin)
            ->get(route('reports.inbounds.export'))
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->getContent();

        $rows = $this->csvRows($csv);
        $this->assertSame([
            'No Trx', 'Tanggal', 'Gudang', 'SKU', 'Nama Produk', 'Qty', 'Harga', 'Subtotal',
        ], $rows[0]);
        $this->assertCount(1, $rows);
    }

    /**
     * @return array{User, Warehouse, Warehouse, Product, Product, Customer|null, User|null}
     */
    private function reportFixtures(bool $withSalesRelations = false): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $warehouseA = Warehouse::create([
            'code' => 'RPT-A',
            'name' => 'Gudang Laporan A',
            'address' => null,
            'is_active' => true,
        ]);
        $warehouseB = Warehouse::create([
            'code' => 'RPT-B',
            'name' => 'Gudang Laporan B',
            'address' => null,
            'is_active' => true,
        ]);
        $category = Category::create(['name' => 'Kategori Laporan', 'slug' => 'kategori-laporan']);
        $productA = Product::create([
            'sku' => 'RPT-001',
            'name' => 'Barang Laporan Satu',
            'category_id' => $category->id,
            'cost_price' => 100,
            'selling_price' => 150,
            'is_active' => true,
        ]);
        $productB = Product::create([
            'sku' => 'RPT-002',
            'name' => 'Barang Laporan Dua',
            'category_id' => $category->id,
            'cost_price' => 200,
            'selling_price' => 250,
            'is_active' => false,
        ]);
        $customer = $withSalesRelations
            ? Customer::create(['name' => 'Pelanggan Laporan', 'phone' => null, 'address' => null])
            : null;
        $operator = $withSalesRelations
            ? User::factory()->create(['role' => 'operator'])
            : null;

        return [$admin, $warehouseA, $warehouseB, $productA, $productB, $customer, $operator];
    }

    /**
     * @param  array<int, array{Product, int, float|int, float|int}>  $items
     */
    private function makeTransaction(
        User $user,
        Warehouse $warehouse,
        string $type,
        string $number,
        string $date,
        array $items,
        ?Customer $customer = null,
        string $status = 'active',
    ): Transaction {
        $transaction = Transaction::create([
            'trx_no' => $number,
            'type' => $type,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer?->id,
            'transaction_date' => $date,
            'status' => $status,
            'user_id' => $user->id,
        ]);

        foreach ($items as [$product, $quantity, $unitPrice, $subtotal]) {
            TransactionItem::create([
                'transaction_id' => $transaction->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ]);
        }

        return $transaction;
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function csvRows(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $lines = explode("\n", rtrim($csv, "\r\n"));

        return array_map(
            static fn (string $line): array => str_getcsv($line, ',', '"', ''),
            $lines,
        );
    }

    private function tableBody(string $html): string
    {
        preg_match('/<tbody>(.*?)<\/tbody>/s', $html, $matches);

        return $matches[1] ?? '';
    }
}
