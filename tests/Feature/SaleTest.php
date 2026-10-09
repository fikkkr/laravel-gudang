<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\TransactionMoney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_create_sale_and_decrease_stock(): void
    {
        [$operator, $warehouse, $customer, $product] = $this->saleFixtures();
        Stock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $response = $this->actingAs($operator)->post(route('sales.store'), [
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'transaction_date' => '2026-10-09',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 6_000_000,
                ],
            ],
        ]);

        $transaction = Transaction::query()->firstOrFail();
        $response->assertRedirect(route('transactions.show', $transaction));
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'type' => 'OUT',
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'quantity' => -2,
            'balance_after' => 3,
        ]);
        $this->assertSame(3, (int) Stock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->value('quantity'));
    }

    public function test_sale_with_insufficient_stock_rolls_back_transaction(): void
    {
        [$operator, $warehouse, $customer, $product] = $this->saleFixtures();
        Stock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($operator)->from(route('sales.create'))->post(route('sales.store'), [
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'transaction_date' => '2026-10-09',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 6_000_000,
                ],
            ],
        ])->assertRedirect(route('sales.create'))
            ->assertSessionHasErrors('transaction');

        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('transaction_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(1, (int) Stock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->value('quantity'));
    }

    public function test_sale_blocked_when_stock_below_min(): void
    {
        [$operator, $warehouse, $customer, $product] = $this->saleFixtures();
        $product->update(['min_stock' => 3]);
        Stock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $sale = [
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'transaction_date' => '2026-10-09',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 4,
                'unit_price' => 6_000_000,
            ]],
        ];

        $this->actingAs($operator)
            ->from(route('sales.create'))
            ->post(route('sales.store'), $sale)
            ->assertRedirect(route('sales.create'))
            ->assertSessionHasErrors('transaction');

        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame(5, (int) Stock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->value('quantity'));

        $sale['items'][0]['quantity'] = 2;
        $response = $this->post(route('sales.store'), $sale);
        $transaction = Transaction::query()->firstOrFail();

        $response->assertRedirect(route('transactions.show', $transaction));
        $this->assertSame(3, (int) Stock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->value('quantity'));
    }

    public function test_sale_snapshots_server_prices_and_calculates_multiple_item_total(): void
    {
        [$operator, $warehouse, $customer, $product] = $this->saleFixtures();
        $category = Category::query()->firstOrFail();
        $secondProduct = Product::create([
            'sku' => 'PRD-S-2',
            'name' => 'Mouse',
            'category_id' => $category->id,
            'cost_price' => '100.00',
            'selling_price' => '250.25',
            'is_active' => true,
        ]);
        Stock::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 10]);
        Stock::create(['warehouse_id' => $warehouse->id, 'product_id' => $secondProduct->id, 'quantity' => 10]);

        $this->actingAs($operator)->post(route('sales.store'), [
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'transaction_date' => '2026-10-09',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => '0.01'],
                ['product_id' => $secondProduct->id, 'quantity' => 3, 'unit_price' => '999999.99'],
            ],
        ])->assertRedirect();

        $firstSale = Transaction::query()->firstOrFail();
        $this->assertSame(2, $firstSale->items()->count());
        $this->assertSame('12000750.75', $firstSale->total);
        $this->assertDatabaseHas('transaction_items', [
            'transaction_id' => $firstSale->id,
            'product_id' => $product->id,
            'unit_price' => '6000000.00',
            'subtotal' => '12000000.00',
        ]);
        $this->assertDatabaseHas('transaction_items', [
            'transaction_id' => $firstSale->id,
            'product_id' => $secondProduct->id,
            'unit_price' => '250.25',
            'subtotal' => '750.75',
        ]);
        $this->assertSame(8, (int) Stock::query()
            ->where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->value('quantity'));
        $this->assertSame(7, (int) Stock::query()
            ->where('warehouse_id', $warehouse->id)->where('product_id', $secondProduct->id)->value('quantity'));

        $product->update(['name' => 'Laptop Baru', 'sku' => 'PRD-S-NEW', 'selling_price' => '7000000.50']);
        $this->actingAs($operator)->post(route('sales.store'), [
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'transaction_date' => '2026-10-10',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '1.00'],
            ],
        ])->assertRedirect();

        $secondSale = Transaction::query()->where('type', 'OUT')->orderByDesc('id')->firstOrFail();
        $this->assertSame('7000000.50', TransactionMoney::sum([
            $secondSale->items()->firstOrFail()->unit_price,
        ]));
        $this->assertSame('12000000.00', TransactionMoney::sum([
            $firstSale->fresh()->items()->where('product_id', $product->id)->value('subtotal'),
        ]));
        $firstItem = $firstSale->items()->with('product')->where('product_id', $product->id)->firstOrFail();
        $this->assertSame('Laptop Baru', $firstItem->product->name);
        $this->assertSame('PRD-S-NEW', $firstItem->product->sku);
        $this->actingAs($operator)
            ->get(route('transactions.show', $firstSale))
            ->assertOk()
            ->assertSee('PRD-S-NEW — Laptop Baru');
    }

    public function test_insufficient_stock_for_later_sale_item_rolls_back_earlier_item_changes(): void
    {
        [$operator, $warehouse, $customer, $product] = $this->saleFixtures();
        $category = Category::query()->firstOrFail();
        $secondProduct = Product::create([
            'sku' => 'PRD-LATE',
            'name' => 'Produk Kedua',
            'category_id' => $category->id,
            'cost_price' => 10,
            'selling_price' => 20,
            'is_active' => true,
        ]);
        Stock::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 5]);
        Stock::create(['warehouse_id' => $warehouse->id, 'product_id' => $secondProduct->id, 'quantity' => 1]);

        $this->actingAs($operator)->from(route('sales.create'))->post(route('sales.store'), [
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'transaction_date' => '2026-10-09',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => '6000000.00'],
                ['product_id' => $secondProduct->id, 'quantity' => 2, 'unit_price' => '20.00'],
            ],
        ])->assertRedirect(route('sales.create'))->assertSessionHasErrors('transaction');

        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('transaction_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(5, (int) Stock::query()
            ->where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->value('quantity'));
        $this->assertSame(1, (int) Stock::query()
            ->where('warehouse_id', $warehouse->id)->where('product_id', $secondProduct->id)->value('quantity'));
    }

    public function test_operator_can_open_sale_form_with_products_and_customers(): void
    {
        [$operator, , , $product] = $this->saleFixtures();

        $this->actingAs($operator)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('Penjualan')
            ->assertSee('data-price="'.$product->selling_price.'"', false);
    }

    /**
     * @return array{User, Warehouse, Customer, Product}
     */
    private function saleFixtures(): array
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $warehouse = Warehouse::create([
            'code' => 'GDG-S',
            'name' => 'Gudang Penjualan',
            'address' => 'Jakarta',
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'name' => 'Toko Penjualan',
            'phone' => '081234567890',
            'address' => 'Bandung',
        ]);
        $category = Category::create([
            'name' => 'Elektronik',
            'slug' => 'elektronik',
        ]);
        $product = Product::create([
            'sku' => 'PRD-S',
            'name' => 'Laptop',
            'category_id' => $category->id,
            'cost_price' => 5_000_000,
            'selling_price' => 6_000_000,
            'is_active' => true,
        ]);

        return [$operator, $warehouse, $customer, $product];
    }
}
