<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_open_transfer_form(): void
    {
        [$operator] = $this->transferFixtures();

        $this->actingAs($operator)
            ->get(route('transfers.create'))
            ->assertOk()
            ->assertSee('Gudang Asal')
            ->assertSee('Gudang Tujuan')
            ->assertSee('Transfer Stok');
    }

    public function test_transfer_decreases_source_and_increases_destination_stock(): void
    {
        [$operator, $source, $destination, $product] = $this->transferFixtures();
        Stock::create([
            'warehouse_id' => $source->id,
            'product_id' => $product->id,
            'quantity' => 8,
        ]);

        $response = $this->actingAs($operator)->post(route('transfers.store'), [
            'warehouse_id' => $source->id,
            'destination_warehouse_id' => $destination->id,
            'transaction_date' => '2026-10-09',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ]);

        $transaction = Transaction::query()->firstOrFail();
        $response->assertRedirect(route('transactions.show', $transaction));
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'type' => 'TRANSFER',
            'warehouse_id' => $source->id,
            'destination_warehouse_id' => $destination->id,
        ]);
        $this->assertSame(5, (int) Stock::query()
            ->where('warehouse_id', $source->id)
            ->where('product_id', $product->id)
            ->value('quantity'));
        $this->assertSame(3, (int) Stock::query()
            ->where('warehouse_id', $destination->id)
            ->where('product_id', $product->id)
            ->value('quantity'));
    }

    public function test_transfer_rejects_same_source_and_destination(): void
    {
        [$operator, $source, , $product] = $this->transferFixtures();

        $this->actingAs($operator)->post(route('transfers.store'), [
            'warehouse_id' => $source->id,
            'destination_warehouse_id' => $source->id,
            'transaction_date' => '2026-10-09',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertSessionHasErrors('destination_warehouse_id');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_transfer_rejects_inactive_product(): void
    {
        [$operator, $source, $destination, $product] = $this->transferFixtures();
        $product->update(['is_active' => false]);

        $this->actingAs($operator)
            ->from(route('transfers.create'))
            ->post(route('transfers.store'), [
                'warehouse_id' => $source->id,
                'destination_warehouse_id' => $destination->id,
                'transaction_date' => '2026-10-09',
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertRedirect(route('transfers.create'))
            ->assertSessionHasErrors('items.0.product_id');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_transfer_with_insufficient_source_stock_rolls_back(): void
    {
        [$operator, $source, $destination, $product] = $this->transferFixtures();
        Stock::create([
            'warehouse_id' => $source->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($operator)->from(route('transfers.create'))->post(route('transfers.store'), [
            'warehouse_id' => $source->id,
            'destination_warehouse_id' => $destination->id,
            'transaction_date' => '2026-10-09',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])->assertRedirect(route('transfers.create'))
            ->assertSessionHasErrors('transaction');

        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('transaction_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(1, (int) Stock::query()
            ->where('warehouse_id', $source->id)
            ->where('product_id', $product->id)
            ->value('quantity'));
        $this->assertSame(0, (int) Stock::query()
            ->where('warehouse_id', $destination->id)
            ->where('product_id', $product->id)
            ->value('quantity'));
    }

    public function test_transfer_blocked_when_source_below_min(): void
    {
        [$operator, $source, $destination, $product] = $this->transferFixtures();
        $product->update(['min_stock' => 2]);
        Stock::create([
            'warehouse_id' => $source->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $transfer = [
            'warehouse_id' => $source->id,
            'destination_warehouse_id' => $destination->id,
            'transaction_date' => '2026-10-09',
            'items' => [['product_id' => $product->id, 'quantity' => 4]],
        ];

        $this->actingAs($operator)
            ->from(route('transfers.create'))
            ->post(route('transfers.store'), $transfer)
            ->assertRedirect(route('transfers.create'))
            ->assertSessionHasErrors('transaction');

        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame(5, (int) Stock::query()
            ->where('warehouse_id', $source->id)
            ->where('product_id', $product->id)
            ->value('quantity'));

        $transfer['items'][0]['quantity'] = 3;
        $response = $this->post(route('transfers.store'), $transfer);
        $transaction = Transaction::query()->firstOrFail();

        $response->assertRedirect(route('transactions.show', $transaction));
        $this->assertSame(2, (int) Stock::query()
            ->where('warehouse_id', $source->id)
            ->where('product_id', $product->id)
            ->value('quantity'));
    }

    /**
     * @return array{User, Warehouse, Warehouse, Product}
     */
    private function transferFixtures(): array
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $source = Warehouse::create([
            'code' => 'TRF-A',
            'name' => 'Gudang Asal Tes',
            'address' => null,
            'is_active' => true,
        ]);
        $destination = Warehouse::create([
            'code' => 'TRF-B',
            'name' => 'Gudang Tujuan Tes',
            'address' => null,
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Kategori Transfer',
            'slug' => 'kategori-transfer',
        ]);
        $product = Product::create([
            'sku' => 'TRF-PRD',
            'name' => 'Produk Transfer',
            'category_id' => $category->id,
            'cost_price' => 100,
            'selling_price' => 150,
            'is_active' => true,
        ]);

        return [$operator, $source, $destination, $product];
    }
}
