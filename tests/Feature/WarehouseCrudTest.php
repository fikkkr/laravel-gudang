<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_admin_can_list_and_create_warehouses(): void
    {
        $this->get(route('warehouses.index'))->assertOk();
        $this->get(route('warehouses.create'))
            ->assertOk()
            ->assertSee('type="checkbox" name="is_active"', false);

        $this->post(route('warehouses.store'), [
            'code' => 'GDG-TEST',
            'name' => 'Gudang Tes',
            'address' => 'Jakarta',
            'is_active' => '1',
        ])->assertRedirect(route('warehouses.index'));

        $this->assertDatabaseHas('warehouses', [
            'code' => 'GDG-TEST',
            'name' => 'Gudang Tes',
            'is_active' => true,
        ]);
    }

    public function test_warehouse_code_must_be_unique(): void
    {
        Warehouse::create([
            'code' => 'GDG-TEST',
            'name' => 'Gudang Lama',
            'is_active' => true,
        ]);

        $this->post(route('warehouses.store'), [
            'code' => 'GDG-TEST',
            'name' => 'Gudang Duplikat',
        ])->assertSessionHasErrors('code');

        $this->assertDatabaseCount('warehouses', 1);
    }

    public function test_admin_can_edit_and_delete_a_warehouse(): void
    {
        $warehouse = Warehouse::create([
            'code' => 'GDG-TEST',
            'name' => 'Gudang Lama',
            'is_active' => true,
        ]);

        $this->get(route('warehouses.edit', $warehouse))
            ->assertOk()
            ->assertSee(route('warehouses.update', $warehouse->id), false)
            ->assertSee('name="is_active"', false);

        $this->put(route('warehouses.update', $warehouse), [
            'code' => 'GDG-TEST',
            'name' => 'Gudang Baru',
        ])->assertRedirect(route('warehouses.index'));

        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse->id,
            'name' => 'Gudang Baru',
            'is_active' => false,
        ]);

        $this->delete(route('warehouses.destroy', $warehouse))
            ->assertRedirect(route('warehouses.index'));

        $this->assertDatabaseMissing('warehouses', ['id' => $warehouse->id]);
    }

    public function test_warehouse_with_stock_cannot_be_deleted(): void
    {
        $warehouse = Warehouse::create([
            'code' => 'GDG-HISTORY',
            'name' => 'Gudang Berstok',
            'is_active' => true,
        ]);
        $category = Category::create(['name' => 'Stok', 'slug' => 'stok']);
        $product = Product::create([
            'sku' => 'WH-PRD',
            'name' => 'Produk Gudang',
            'category_id' => $category->id,
            'is_active' => true,
        ]);
        Stock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 0,
        ]);

        $this->delete(route('warehouses.destroy', $warehouse))
            ->assertRedirect(route('warehouses.index'))
            ->assertSessionHasErrors('warehouse');

        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id]);
        $this->assertDatabaseHas('stocks', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
        ]);
    }
}
