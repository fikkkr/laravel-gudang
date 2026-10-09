<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Support\TransactionMoney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InboundTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_only_saves_expected_columns(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $warehouse = Warehouse::create([
            'code' => 'GDG-SCHEMA',
            'name' => 'Gudang Schema',
            'is_active' => true,
        ]);
        $category = Category::create(['name' => 'Schema', 'slug' => 'schema']);
        $product = Product::create([
            'sku' => 'PRD-SCHEMA',
            'name' => 'Produk Schema',
            'category_id' => $category->id,
            'cost_price' => 100,
            'selling_price' => 200,
            'is_active' => true,
        ]);

        $this->assertFalse(Schema::hasColumn('transaction_items', 'product_name'));
        $this->assertFalse(Schema::hasColumn('transaction_items', 'product_sku'));

        $response = $this->actingAs($operator)->post(route('inbounds.store'), [
            'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-10-09',
            'items' => [[
                'product_id' => $product->id,
                'product_name' => 'Nama Kiriman Tak Tepercaya',
                'product_sku' => 'SKU-KIRIMAN',
                'quantity' => 10,
                'unit_price' => '12.34',
            ]],
        ]);

        $transaction = Transaction::query()->firstOrFail();
        $response->assertRedirect(route('transactions.show', $transaction));

        $item = $transaction->items()->firstOrFail();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame(10, $item->quantity);
        $this->assertSame('12.34', TransactionMoney::sum([$item->unit_price]));
        $this->assertSame('123.40', TransactionMoney::sum([$item->subtotal]));
        $this->assertNull($item->getAttribute('product_name'));
        $this->assertNull($item->getAttribute('product_sku'));
        $this->assertSame(10, (int) Stock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->value('quantity'));
    }

    public function test_operator_can_create_inbound_and_increase_stock(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $warehouse = Warehouse::create([
            'code' => 'GDG-T',
            'name' => 'Gudang Tes',
            'address' => 'Jakarta',
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Elektronik',
            'slug' => 'elektronik',
        ]);
        $product = Product::create([
            'sku' => 'PRD-T',
            'name' => 'Laptop',
            'category_id' => $category->id,
            'cost_price' => 5_000_000,
            'selling_price' => 6_000_000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($operator)->post(route('inbounds.store'), [
            'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-10-09',
            'notes' => 'Penerimaan barang',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                    'unit_price' => 5_000_000,
                ],
            ],
        ]);

        $transaction = Transaction::query()->firstOrFail();

        $response->assertRedirect(route('transactions.show', $transaction));
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'type' => 'IN',
            'warehouse_id' => $warehouse->id,
            'user_id' => $operator->id,
        ]);
        $this->assertDatabaseHas('transaction_items', [
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'subtotal' => 15_000_000,
        ]);
        $this->assertSame(3, (int) Stock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->value('quantity'));
        $this->assertDatabaseHas('stock_movements', [
            'transaction_id' => $transaction->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'balance_after' => 3,
        ]);

        $this->actingAs($operator)
            ->get(route('transactions.show', $transaction))
            ->assertOk()
            ->assertSee($transaction->trx_no);
    }

    public function test_inbound_detail_route_rejects_non_inbound_transactions(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $warehouse = Warehouse::create([
            'code' => 'GDG-NOT-IN',
            'name' => 'Gudang Non Inbound',
            'is_active' => true,
        ]);
        $transaction = Transaction::create([
            'trx_no' => 'OUT-NOT-IN',
            'type' => 'OUT',
            'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-10-09',
            'status' => 'active',
            'user_id' => $operator->id,
        ]);

        $this->actingAs($operator)
            ->get(route('inbounds.show', $transaction))
            ->assertNotFound();
    }

    public function test_inbound_stores_multiple_items_and_snapshots_each_cost(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $warehouse = Warehouse::create([
            'code' => 'GDG-MI',
            'name' => 'Gudang Multi Item',
            'address' => null,
            'is_active' => true,
        ]);
        $category = Category::create(['name' => 'Multi', 'slug' => 'multi']);
        $firstProduct = Product::create([
            'sku' => 'MI-1',
            'name' => 'Produk Satu',
            'category_id' => $category->id,
            'cost_price' => 100,
            'selling_price' => 200,
            'is_active' => true,
        ]);
        $secondProduct = Product::create([
            'sku' => 'MI-2',
            'name' => 'Produk Dua',
            'category_id' => $category->id,
            'cost_price' => 300,
            'selling_price' => 400,
            'is_active' => true,
        ]);

        $response = $this->actingAs($operator)->post(route('inbounds.store'), [
            'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-10-09',
            'items' => [
                ['product_id' => $firstProduct->id, 'quantity' => 2, 'unit_price' => '12.34'],
                ['product_id' => $secondProduct->id, 'quantity' => 3, 'unit_price' => '5.10'],
            ],
        ]);

        $transaction = Transaction::query()->firstOrFail();
        $response->assertRedirect(route('transactions.show', $transaction));
        $this->assertSame(2, $transaction->items()->count());
        $this->assertSame('39.98', $transaction->total);
        $this->assertDatabaseHas('transaction_items', [
            'transaction_id' => $transaction->id,
            'product_id' => $firstProduct->id,
            'quantity' => 2,
            'unit_price' => '12.34',
            'subtotal' => '24.68',
        ]);
        $this->assertDatabaseHas('transaction_items', [
            'transaction_id' => $transaction->id,
            'product_id' => $secondProduct->id,
            'quantity' => 3,
            'unit_price' => '5.10',
            'subtotal' => '15.30',
        ]);
        $this->assertSame(2, (int) Stock::query()
            ->where('warehouse_id', $warehouse->id)->where('product_id', $firstProduct->id)->value('quantity'));
        $this->assertSame(3, (int) Stock::query()
            ->where('warehouse_id', $warehouse->id)->where('product_id', $secondProduct->id)->value('quantity'));
        $this->assertSame(2, StockMovement::query()->where('transaction_id', $transaction->id)->count());

        $firstProduct->update(['name' => 'Produk Satu Baru', 'sku' => 'MI-1-NEW', 'cost_price' => '99.99']);
        $this->assertSame('Produk Satu Baru', $transaction->items()->with('product')->where('product_id', $firstProduct->id)->firstOrFail()->product->name);
        $this->assertSame('MI-1-NEW', $transaction->items()->with('product')->where('product_id', $firstProduct->id)->firstOrFail()->product->sku);
        $this->assertSame('12.34', TransactionMoney::sum([
            $transaction->items()->where('product_id', $firstProduct->id)->value('unit_price'),
        ]));
    }

    public function test_invalid_item_in_multi_item_inbound_creates_no_partial_transaction(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $warehouse = Warehouse::create([
            'code' => 'GDG-IV',
            'name' => 'Gudang Validasi',
            'address' => null,
            'is_active' => true,
        ]);

        $this->actingAs($operator)
            ->from(route('inbounds.create'))
            ->post(route('inbounds.store'), [
                'warehouse_id' => $warehouse->id,
                'transaction_date' => '2026-10-09',
                'items' => [
                    ['product_id' => 999999, 'quantity' => 1, 'unit_price' => '1.00'],
                    ['product_id' => 999998, 'quantity' => 0, 'unit_price' => '1.00'],
                ],
            ])
            ->assertRedirect(route('inbounds.create'))
            ->assertSessionHasErrors(['items.0.product_id', 'items.1.product_id', 'items.1.quantity']);

        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('transaction_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_invalid_inbound_does_not_create_transaction_or_stock_movement(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $warehouse = Warehouse::create([
            'code' => 'GDG-T',
            'name' => 'Gudang Tes',
            'address' => null,
            'is_active' => true,
        ]);

        $this->actingAs($operator)->post(route('inbounds.store'), [
            'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-10-09',
            'items' => [],
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_stock_service_rejects_outbound_that_exceeds_available_stock(): void
    {
        $warehouse = Warehouse::create([
            'code' => 'GDG-T',
            'name' => 'Gudang Tes',
            'address' => null,
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Elektronik',
            'slug' => 'elektronik',
        ]);
        $product = Product::create([
            'sku' => 'PRD-T',
            'name' => 'Laptop',
            'category_id' => $category->id,
            'cost_price' => 5_000_000,
            'selling_price' => 6_000_000,
            'is_active' => true,
        ]);
        Stock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $transaction = Transaction::create([
            'trx_no' => 'OUT-TEST',
            'type' => 'OUT',
            'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-10-09',
            'status' => 'active',
            'user_id' => User::factory()->create()->id,
        ]);

        try {
            StockService::applyTransaction($transaction, [[
                'product_id' => $product->id,
                'quantity' => 3,
            ]]);
            $this->fail('Expected insufficient stock to throw an exception.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Minimum stok', $exception->getMessage());
        }

        $this->assertSame(2, (int) Stock::query()->firstOrFail()->quantity);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_stock_service_records_outbound_and_transfer_balances(): void
    {
        $source = Warehouse::create([
            'code' => 'GDG-A',
            'name' => 'Gudang Sumber',
            'address' => null,
            'is_active' => true,
        ]);
        $destination = Warehouse::create([
            'code' => 'GDG-B',
            'name' => 'Gudang Tujuan',
            'address' => null,
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Elektronik',
            'slug' => 'elektronik',
        ]);
        $product = Product::create([
            'sku' => 'PRD-T',
            'name' => 'Laptop',
            'category_id' => $category->id,
            'cost_price' => 5_000_000,
            'selling_price' => 6_000_000,
            'is_active' => true,
        ]);
        Stock::create([
            'warehouse_id' => $source->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);
        $user = User::factory()->create();

        $outbound = Transaction::create([
            'trx_no' => 'OUT-TEST',
            'type' => 'OUT',
            'warehouse_id' => $source->id,
            'transaction_date' => '2026-10-09',
            'status' => 'active',
            'user_id' => $user->id,
        ]);
        StockService::applyTransaction($outbound, [[
            'product_id' => $product->id,
            'quantity' => 2,
        ]]);

        $transfer = Transaction::create([
            'trx_no' => 'TRANSFER-TEST',
            'type' => 'TRANSFER',
            'warehouse_id' => $source->id,
            'destination_warehouse_id' => $destination->id,
            'transaction_date' => '2026-10-09',
            'status' => 'active',
            'user_id' => $user->id,
        ]);
        StockService::applyTransaction($transfer, [[
            'product_id' => $product->id,
            'quantity' => 3,
        ]]);

        $this->assertSame(5, (int) Stock::query()
            ->where('warehouse_id', $source->id)
            ->where('product_id', $product->id)
            ->value('quantity'));
        $this->assertSame(3, (int) Stock::query()
            ->where('warehouse_id', $destination->id)
            ->where('product_id', $product->id)
            ->value('quantity'));
        $this->assertDatabaseHas('stock_movements', [
            'transaction_id' => $outbound->id,
            'quantity' => -2,
            'balance_after' => 8,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'transaction_id' => $transfer->id,
            'warehouse_id' => $source->id,
            'quantity' => -3,
            'balance_after' => 5,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'transaction_id' => $transfer->id,
            'warehouse_id' => $destination->id,
            'quantity' => 3,
            'balance_after' => 3,
        ]);
    }
}
