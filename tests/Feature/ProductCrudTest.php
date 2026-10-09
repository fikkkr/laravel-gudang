<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_admin_can_list_and_create_a_product_with_a_valid_category(): void
    {
        $category = Category::create([
            'name' => 'Elektronik',
            'slug' => 'elektronik',
        ]);

        $this->get(route('products.index'))->assertOk();
        $this->get(route('products.create'))
            ->assertOk()
            ->assertSee('type="checkbox" name="is_active"', false);

        $this->post(route('products.store'), [
            'sku' => 'PRD-TEST',
            'name' => 'Laptop Tes',
            'category_id' => $category->id,
            'cost_price' => 5000000,
            'selling_price' => 6000000,
            'min_stock' => 5,
            'is_active' => '1',
        ])->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'sku' => 'PRD-TEST',
            'name' => 'Laptop Tes',
            'category_id' => $category->id,
            'min_stock' => 5,
            'is_active' => true,
        ]);
    }

    public function test_product_create_with_min_stock(): void
    {
        $category = Category::create([
            'name' => 'Elektronik',
            'slug' => 'elektronik',
        ]);

        $this->post(route('products.store'), [
            'sku' => 'PRD-CRITICAL',
            'name' => 'Produk Kritis',
            'category_id' => $category->id,
            'min_stock' => 5,
        ])->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'sku' => 'PRD-CRITICAL',
            'min_stock' => 5,
        ]);
    }

    public function test_product_sku_must_be_unique_and_category_must_exist(): void
    {
        $category = Category::create([
            'name' => 'Elektronik',
            'slug' => 'elektronik',
        ]);
        Product::create([
            'sku' => 'PRD-TEST',
            'name' => 'Laptop Lama',
            'category_id' => $category->id,
            'cost_price' => 5000000,
            'selling_price' => 6000000,
            'is_active' => true,
        ]);

        $this->post(route('products.store'), [
            'sku' => 'PRD-TEST',
            'name' => 'Laptop Duplikat',
            'category_id' => $category->id,
            'min_stock' => 1,
        ])->assertSessionHasErrors('sku');

        $this->post(route('products.store'), [
            'sku' => 'PRD-INVALID',
            'name' => 'Produk Invalid',
            'category_id' => 999999,
            'min_stock' => 1,
        ])->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('products', 1);
    }

    public function test_admin_can_edit_and_delete_a_product(): void
    {
        $category = Category::create([
            'name' => 'Elektronik',
            'slug' => 'elektronik',
        ]);
        $product = Product::create([
            'sku' => 'PRD-TEST',
            'name' => 'Laptop Lama',
            'category_id' => $category->id,
            'cost_price' => 5000000,
            'selling_price' => 6000000,
            'is_active' => true,
        ]);

        $this->get(route('products.edit', $product))
            ->assertOk()
            ->assertSee(route('products.update', $product->id), false)
            ->assertSee('name="min_stock"', false)
            ->assertSee('name="is_active"', false);

        $this->put(route('products.update', $product), [
            'sku' => 'PRD-TEST',
            'name' => 'Laptop Baru',
            'category_id' => $category->id,
            'cost_price' => 5100000,
            'selling_price' => 6100000,
            'min_stock' => 2,
        ])->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Laptop Baru',
            'min_stock' => 2,
            'is_active' => false,
        ]);

        $this->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_deleting_a_product_with_inventory_history_deactivates_it(): void
    {
        $category = Category::create([
            'name' => 'Elektronik',
            'slug' => 'elektronik',
        ]);
        $product = Product::create([
            'sku' => 'PRD-HISTORY',
            'name' => 'Produk Berhistori',
            'category_id' => $category->id,
            'cost_price' => 5000000,
            'selling_price' => 6000000,
            'is_active' => true,
        ]);
        $warehouse = Warehouse::create([
            'code' => 'GDG-HISTORY',
            'name' => 'Gudang Histori',
            'address' => null,
            'is_active' => true,
        ]);
        Stock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 0,
        ]);

        $this->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('success', 'Produk dinonaktifkan agar histori transaksi tetap utuh.');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false]);
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'quantity' => 0]);
    }
}
