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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_report_index(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);

        $this->actingAs($operator)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Laporan Stok')
            ->assertSee('Laporan Barang Masuk')
            ->assertSee('Laporan Penjualan');
    }

    public function test_stock_report_shows_data_with_filter(): void
    {
        [$operator, $warehouse, $otherWarehouse, $product, $otherProduct] = $this->reportFixtures();
        Stock::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 17]);
        Stock::create(['warehouse_id' => $otherWarehouse->id, 'product_id' => $product->id, 'quantity' => 8]);
        Stock::create(['warehouse_id' => $warehouse->id, 'product_id' => $otherProduct->id, 'quantity' => 4]);

        $response = $this->actingAs($operator)
            ->get(route('reports.stock', [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
            ]))
            ->assertOk()
            ->assertSee('17')
            ->assertSee($product->sku)
            ->assertSee($product->category->name);
        $this->assertStringNotContainsString($otherWarehouse->name, $this->tableBody($response->getContent()));
    }

    public function test_inbound_report_filters_by_date_range(): void
    {
        [$operator, $warehouse, , $product] = $this->reportFixtures();
        $inside = $this->makeTransaction($operator, $warehouse, $product, 'IN', 'IN-INSIDE', now()->startOfMonth()->addDays(2)->toDateString());
        $outside = $this->makeTransaction($operator, $warehouse, $product, 'IN', 'IN-OUTSIDE', now()->subMonth()->startOfMonth()->toDateString());

        $this->actingAs($operator)
            ->get(route('reports.inbound', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee($inside->trx_no)
            ->assertDontSee($outside->trx_no);
    }

    public function test_sales_report_filters_by_customer(): void
    {
        [$operator, $warehouse, , $product] = $this->reportFixtures();
        $customer = Customer::create(['name' => 'Pelanggan Filter', 'phone' => null, 'address' => null]);
        $otherCustomer = Customer::create(['name' => 'Pelanggan Lain', 'phone' => null, 'address' => null]);
        $matching = $this->makeTransaction($operator, $warehouse, $product, 'OUT', 'OUT-MATCH', now()->toDateString(), $customer);
        $other = $this->makeTransaction($operator, $warehouse, $product, 'OUT', 'OUT-OTHER', now()->toDateString(), $otherCustomer);

        $response = $this->actingAs($operator)
            ->get(route('reports.sales', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
                'customer_id' => $customer->id,
            ]))
            ->assertOk()
            ->assertSee($matching->trx_no)
            ->assertSee($customer->name)
            ->assertDontSee($other->trx_no);
        $this->assertStringNotContainsString($otherCustomer->name, $this->tableBody($response->getContent()));
    }

    public function test_stock_report_csv_export_returns_csv_content_type(): void
    {
        [$operator, $warehouse, , $product] = $this->reportFixtures();
        Stock::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 17]);

        $this->actingAs($operator)
            ->get(route('reports.stock', ['export' => 'csv']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="laporan-stock-'.now()->format('Y-m-d').'.csv"')
            ->assertSee('"Nama Produk"', false)
            ->assertSee($product->sku, false);
    }

    public function test_inbound_report_csv_export_has_correct_headers(): void
    {
        [$operator, $warehouse, , $product] = $this->reportFixtures();
        $this->makeTransaction($operator, $warehouse, $product, 'IN', 'IN-CSV', now()->toDateString());

        $response = $this->actingAs($operator)->get(route('reports.inbound', [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
            'export' => 'csv',
        ]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertSee('"No Trx",Tanggal,Gudang,SKU,"Nama Produk",Qty,Harga,Subtotal', false)
            ->assertSee('IN-CSV', false);
    }

    private function tableBody(string $html): string
    {
        preg_match('/<tbody>(.*?)<\/tbody>/s', $html, $matches);

        return $matches[1] ?? '';
    }

    /**
     * @return array{User, Warehouse, Warehouse, Product, Product}
     */
    private function reportFixtures(): array
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $warehouse = Warehouse::create(['code' => 'RPT-A', 'name' => 'Gudang A', 'is_active' => true]);
        $otherWarehouse = Warehouse::create(['code' => 'RPT-B', 'name' => 'Gudang B', 'is_active' => true]);
        $category = Category::create(['name' => 'Kategori Laporan', 'slug' => 'kategori-laporan']);
        $product = Product::create([
            'sku' => 'RPT-001',
            'name' => 'Produk Laporan',
            'category_id' => $category->id,
            'cost_price' => 100,
            'selling_price' => 150,
            'is_active' => true,
        ]);
        $otherProduct = Product::create([
            'sku' => 'RPT-002',
            'name' => 'Produk Lain',
            'category_id' => $category->id,
            'cost_price' => 50,
            'selling_price' => 75,
            'is_active' => true,
        ]);

        return [$operator, $warehouse, $otherWarehouse, $product, $otherProduct];
    }

    private function makeTransaction(
        User $user,
        Warehouse $warehouse,
        Product $product,
        string $type,
        string $number,
        string $date,
        ?Customer $customer = null,
    ): Transaction {
        $transaction = Transaction::create([
            'trx_no' => $number,
            'type' => $type,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer?->id,
            'transaction_date' => $date,
            'status' => 'active',
            'user_id' => $user->id,
        ]);
        TransactionItem::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 12.50,
            'subtotal' => 25,
        ]);

        return $transaction;
    }
}
