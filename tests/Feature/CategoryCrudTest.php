<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_user_can_view_index_and_delete_category(): void
    {
        $category = Category::create([
            'name' => 'Stationery',
            'slug' => 'stationery',
        ]);

        $this->get(route('categories.index'))
            ->assertOk()
            ->assertSee('method="POST"', false)
            ->assertSee(route('categories.destroy', $category->id), false)
            ->assertSee('name="_method" value="DELETE"', false);

        $this->delete(route('categories.destroy', $category))
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_edit_form_uses_the_category_update_route_without_a_manual_slug_field(): void
    {
        $category = Category::create([
            'name' => 'Stationery',
            'slug' => 'stationery',
        ]);

        $this->get(route('categories.edit', $category))
            ->assertOk()
            ->assertSee(route('categories.update', $category->id), false)
            ->assertSee('name="name"', false)
            ->assertDontSee('name="slug"', false);
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $category = Category::create([
            'name' => 'Elektronik',
            'slug' => 'elektronik',
        ]);
        $product = Product::create([
            'sku' => 'CAT-PRD',
            'name' => 'Produk Kategori',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $this->delete(route('categories.destroy', $category))
            ->assertRedirect(route('categories.index'))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $category->id]);
    }

    public function test_update_generates_a_unique_slug_and_ignores_submitted_slug(): void
    {
        $category = Category::create([
            'name' => 'Old name',
            'slug' => 'old-name',
        ]);
        Category::create([
            'name' => 'Taken name',
            'slug' => 'taken-name',
        ]);

        $this->put(route('categories.update', $category), [
            'name' => 'Taken name',
            'slug' => 'manually-submitted-slug',
        ])->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Taken name',
            'slug' => 'taken-name-2',
        ]);
    }

    public function test_update_allows_a_category_to_keep_its_existing_slug(): void
    {
        $category = Category::create([
            'name' => 'Stationery',
            'slug' => 'stationery',
        ]);

        $this->put(route('categories.update', $category), [
            'name' => 'Stationery',
        ])->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'slug' => 'stationery',
        ]);
    }

    public function test_store_generates_a_unique_slug_without_a_slug_input(): void
    {
        Category::create([
            'name' => 'Stationery',
            'slug' => 'stationery',
        ]);

        $this->get(route('categories.create'))
            ->assertOk()
            ->assertDontSee('name="slug"', false);

        $this->post(route('categories.store'), [
            'name' => 'Stationery',
        ])->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', [
            'name' => 'Stationery',
            'slug' => 'stationery-2',
        ]);
    }
}
