<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_inventory_counts_and_recent_data_for_operator(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $category = Category::create(['name' => 'Elektronik', 'slug' => 'elektronik']);
        $customer = Customer::create(['name' => 'Toko Maju']);
        $warehouse = Warehouse::create([
            'code' => 'GDG-A',
            'name' => 'Gudang Pusat',
            'is_active' => true,
        ]);
        $product = Product::create([
            'sku' => 'PRD-001',
            'name' => 'Laptop',
            'category_id' => $category->id,
            'cost_price' => 5_000_000,
            'selling_price' => 6_000_000,
            'min_stock' => 4,
            'is_active' => true,
        ]);
        Stock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 4,
        ]);
        $transaction = Transaction::create([
            'trx_no' => 'OUT-001',
            'type' => 'OUT',
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'transaction_date' => today(),
            'status' => 'active',
            'user_id' => $operator->id,
        ]);

        $this->actingAs($operator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Total Produk')
            ->assertSee('Stok Terendah')
            ->assertSee('Transaksi Terbaru')
            ->assertSee($product->name)
            ->assertSee($transaction->trx_no)
            ->assertSee($customer->name);
    }
}
