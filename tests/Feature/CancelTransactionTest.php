<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelling_inbound_cannot_reduce_stock_below_default_minimum(): void
    {
        [$operator, $warehouse, , $product] = $this->cancellationFixtures();
        $transaction = $this->createAndApplyTransaction($operator, $warehouse, $product, 'IN', 5);

        $this->actingAs($operator)
            ->from(route('transactions.show', $transaction))
            ->post(route('transactions.cancel', $transaction), ['cancel_reason' => 'Salah input'])
            ->assertRedirect(route('transactions.show', $transaction))
            ->assertSessionHasErrors('transaction');

        $this->assertStock($warehouse, $product, 5);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'status' => 'active']);
        $this->assertDatabaseMissing('stock_movements', [
            'transaction_id' => $transaction->id,
            'quantity' => -5,
        ]);
    }

    public function test_cancelling_outbound_restores_stock(): void
    {
        [$operator, $warehouse, , $product] = $this->cancellationFixtures();
        Stock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);
        $transaction = $this->createAndApplyTransaction($operator, $warehouse, $product, 'OUT', 2);

        $this->actingAs($operator)
            ->post(route('transactions.cancel', $transaction))
            ->assertRedirect(route('transactions.show', $transaction));

        $this->assertStock($warehouse, $product, 5);
        $this->assertDatabaseHas('stock_movements', [
            'transaction_id' => $transaction->id,
            'quantity' => 2,
            'balance_after' => 5,
        ]);
    }

    public function test_cancelling_transfer_restores_both_warehouse_balances(): void
    {
        [$operator, $source, $destination, $product] = $this->cancellationFixtures();
        Stock::create([
            'warehouse_id' => $source->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);
        Stock::create([
            'warehouse_id' => $destination->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        $transaction = $this->createAndApplyTransaction(
            $operator,
            $source,
            $product,
            'TRANSFER',
            4,
            $destination
        );

        $this->actingAs($operator)
            ->post(route('transactions.cancel', $transaction))
            ->assertRedirect(route('transactions.show', $transaction));

        $this->assertStock($source, $product, 10);
        $this->assertStock($destination, $product, 1);
        $this->assertDatabaseHas('stock_movements', [
            'transaction_id' => $transaction->id,
            'warehouse_id' => $destination->id,
            'quantity' => -4,
            'balance_after' => 1,
        ]);
    }

    public function test_cancelling_multi_item_transfer_restores_every_item_and_keeps_history(): void
    {
        [$operator, $source, $destination, $firstProduct] = $this->cancellationFixtures();
        $category = Category::query()->firstOrFail();
        $secondProduct = Product::create([
            'sku' => 'CAN-PRD-2',
            'name' => 'Produk Pembatalan Kedua',
            'category_id' => $category->id,
            'cost_price' => 100,
            'selling_price' => 150,
            'is_active' => true,
        ]);
        Stock::create(['warehouse_id' => $source->id, 'product_id' => $firstProduct->id, 'quantity' => 10]);
        Stock::create(['warehouse_id' => $source->id, 'product_id' => $secondProduct->id, 'quantity' => 20]);
        Stock::create(['warehouse_id' => $destination->id, 'product_id' => $firstProduct->id, 'quantity' => 1]);
        Stock::create(['warehouse_id' => $destination->id, 'product_id' => $secondProduct->id, 'quantity' => 1]);
        $transaction = Transaction::create([
            'trx_no' => 'TRANSFER-MULTI-CANCEL',
            'type' => 'TRANSFER',
            'warehouse_id' => $source->id,
            'destination_warehouse_id' => $destination->id,
            'transaction_date' => '2026-10-09',
            'status' => 'active',
            'user_id' => $operator->id,
        ]);
        $items = [
            TransactionItem::create([
                'transaction_id' => $transaction->id,
                'product_id' => $firstProduct->id,
                'quantity' => 3,
                'unit_price' => 0,
                'subtotal' => 0,
            ]),
            TransactionItem::create([
                'transaction_id' => $transaction->id,
                'product_id' => $secondProduct->id,
                'quantity' => 4,
                'unit_price' => 0,
                'subtotal' => 0,
            ]),
        ];
        StockService::applyTransaction($transaction, $items);

        $this->actingAs($operator)
            ->post(route('transactions.cancel', $transaction))
            ->assertRedirect(route('transactions.show', $transaction));

        $this->assertStock($source, $firstProduct, 10);
        $this->assertStock($source, $secondProduct, 20);
        $this->assertStock($destination, $firstProduct, 1);
        $this->assertStock($destination, $secondProduct, 1);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'status' => 'cancelled']);
        $this->assertSame(2, $transaction->items()->count());
        $this->assertSame(8, StockMovement::query()
            ->where('transaction_id', $transaction->id)->count());

        $movementCount = StockMovement::query()->count();
        $this->actingAs($operator)
            ->from(route('transactions.show', $transaction))
            ->post(route('transactions.cancel', $transaction))
            ->assertRedirect(route('transactions.show', $transaction))
            ->assertSessionHasErrors('transaction');

        $this->assertStock($source, $firstProduct, 10);
        $this->assertStock($source, $secondProduct, 20);
        $this->assertStock($destination, $firstProduct, 1);
        $this->assertStock($destination, $secondProduct, 1);
        $this->assertSame($movementCount, StockMovement::query()->count());
    }

    public function test_failed_multi_item_cancellation_rolls_back_every_stock_and_movement_change(): void
    {
        [$operator, $warehouse, , $firstProduct] = $this->cancellationFixtures();
        $category = Category::query()->firstOrFail();
        $secondProduct = Product::create([
            'sku' => 'CAN-PRD-3',
            'name' => 'Produk Pembatalan Ketiga',
            'category_id' => $category->id,
            'cost_price' => 100,
            'selling_price' => 150,
            'is_active' => true,
        ]);
        Stock::create(['warehouse_id' => $warehouse->id, 'product_id' => $firstProduct->id, 'quantity' => 10]);
        Stock::create(['warehouse_id' => $warehouse->id, 'product_id' => $secondProduct->id, 'quantity' => 1]);

        $inbound = Transaction::create([
            'trx_no' => 'IN-MULTI-CANCEL-FAIL',
            'type' => 'IN',
            'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-10-09',
            'status' => 'active',
            'user_id' => $operator->id,
        ]);
        $items = [
            TransactionItem::create([
                'transaction_id' => $inbound->id,
                'product_id' => $firstProduct->id,
                'quantity' => 5,
                'unit_price' => 100,
                'subtotal' => 500,
            ]),
            TransactionItem::create([
                'transaction_id' => $inbound->id,
                'product_id' => $secondProduct->id,
                'quantity' => 4,
                'unit_price' => 100,
                'subtotal' => 400,
            ]),
        ];
        StockService::applyTransaction($inbound, $items);
        $this->createAndApplyTransaction($operator, $warehouse, $secondProduct, 'OUT', 4);
        $movementCount = StockMovement::query()->count();

        $this->actingAs($operator)
            ->from(route('transactions.show', $inbound))
            ->post(route('transactions.cancel', $inbound))
            ->assertRedirect(route('transactions.show', $inbound))
            ->assertSessionHasErrors('transaction');

        $this->assertDatabaseHas('transactions', ['id' => $inbound->id, 'status' => 'active']);
        $this->assertStock($warehouse, $firstProduct, 15);
        $this->assertStock($warehouse, $secondProduct, 1);
        $this->assertSame($movementCount, StockMovement::query()->count());
    }

    public function test_cancel_inbound_is_blocked_when_reversal_would_breach_product_minimum(): void
    {
        [$operator, $warehouse, , $product] = $this->cancellationFixtures();
        $inbound = $this->createAndApplyTransaction($operator, $warehouse, $product, 'IN', 10);
        $this->createAndApplyTransaction($operator, $warehouse, $product, 'OUT', 8);
        $product->update(['min_stock' => 3]);
        $movementCount = StockMovement::query()->count();

        $this->actingAs($operator)
            ->from(route('transactions.show', $inbound))
            ->post(route('transactions.cancel', $inbound))
            ->assertRedirect(route('transactions.show', $inbound))
            ->assertSessionHasErrors('transaction');

        $this->assertDatabaseHas('transactions', ['id' => $inbound->id, 'status' => 'active']);
        $this->assertStock($warehouse, $product, 2);
        $this->assertSame($movementCount, StockMovement::query()->count());
    }

    public function test_cannot_cancel_transaction_that_is_already_cancelled(): void
    {
        [$operator, $warehouse, , $product] = $this->cancellationFixtures();
        $transaction = $this->createAndApplyTransaction($operator, $warehouse, $product, 'IN', 5);
        $transaction->update(['status' => 'cancelled']);

        $this->actingAs($operator)
            ->from(route('transactions.show', $transaction))
            ->post(route('transactions.cancel', $transaction))
            ->assertRedirect(route('transactions.show', $transaction))
            ->assertSessionHasErrors('transaction');

        $this->assertStock($warehouse, $product, 5);
    }

    public function test_cannot_cancel_inbound_if_received_stock_has_been_consumed(): void
    {
        [$operator, $warehouse, , $product] = $this->cancellationFixtures();
        $inbound = $this->createAndApplyTransaction($operator, $warehouse, $product, 'IN', 10);
        $this->createAndApplyTransaction($operator, $warehouse, $product, 'OUT', 9);

        $this->actingAs($operator)
            ->from(route('transactions.show', $inbound))
            ->post(route('transactions.cancel', $inbound))
            ->assertRedirect(route('transactions.show', $inbound))
            ->assertSessionHasErrors('transaction');

        $this->assertDatabaseHas('transactions', [
            'id' => $inbound->id,
            'status' => 'active',
        ]);
        $this->assertStock($warehouse, $product, 1);
    }

    /**
     * @return array{User, Warehouse, Warehouse, Product}
     */
    private function cancellationFixtures(): array
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $source = Warehouse::create([
            'code' => 'CAN-A',
            'name' => 'Gudang Pembatalan A',
            'address' => null,
            'is_active' => true,
        ]);
        $destination = Warehouse::create([
            'code' => 'CAN-B',
            'name' => 'Gudang Pembatalan B',
            'address' => null,
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Kategori Pembatalan',
            'slug' => 'kategori-pembatalan',
        ]);
        $product = Product::create([
            'sku' => 'CAN-PRD',
            'name' => 'Produk Pembatalan',
            'category_id' => $category->id,
            'cost_price' => 100,
            'selling_price' => 150,
            'is_active' => true,
        ]);

        return [$operator, $source, $destination, $product];
    }

    private function createAndApplyTransaction(
        User $operator,
        Warehouse $warehouse,
        Product $product,
        string $type,
        int $quantity,
        ?Warehouse $destination = null,
    ): Transaction {
        $transaction = Transaction::create([
            'trx_no' => $type.'-CANCEL-'.uniqid(),
            'type' => $type,
            'warehouse_id' => $warehouse->id,
            'destination_warehouse_id' => $destination?->id,
            'transaction_date' => '2026-10-09',
            'status' => 'active',
            'user_id' => $operator->id,
        ]);
        $item = TransactionItem::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => 0,
            'subtotal' => 0,
        ]);

        StockService::applyTransaction($transaction, [$item]);

        return $transaction;
    }

    private function assertStock(Warehouse $warehouse, Product $product, int $expected): void
    {
        $this->assertSame($expected, (int) Stock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->value('quantity'));
    }
}
