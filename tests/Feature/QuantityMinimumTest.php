<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class QuantityMinimumTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Warehouse $source;

    private Warehouse $destination;

    private Customer $customer;

    private Product $product;

    private Product $secondProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = User::factory()->create(['role' => 'operator']);
        $this->source = Warehouse::create([
            'code' => 'QTY-A',
            'name' => 'Gudang Kuantitas A',
            'address' => null,
            'is_active' => true,
        ]);
        $this->destination = Warehouse::create([
            'code' => 'QTY-B',
            'name' => 'Gudang Kuantitas B',
            'address' => null,
            'is_active' => true,
        ]);
        $this->customer = Customer::create([
            'name' => 'Pelanggan Kuantitas',
            'phone' => null,
            'address' => null,
        ]);
        $category = Category::create([
            'name' => 'Kategori Kuantitas',
            'slug' => 'kategori-kuantitas',
        ]);
        $this->product = Product::create([
            'sku' => 'QTY-1',
            'name' => 'Produk Kuantitas Satu',
            'category_id' => $category->id,
            'cost_price' => 10,
            'selling_price' => 20,
            'is_active' => true,
        ]);
        $this->secondProduct = Product::create([
            'sku' => 'QTY-2',
            'name' => 'Produk Kuantitas Dua',
            'category_id' => $category->id,
            'cost_price' => 30,
            'selling_price' => 40,
            'is_active' => true,
        ]);

        Stock::create([
            'warehouse_id' => $this->source->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);
        Stock::create([
            'warehouse_id' => $this->source->id,
            'product_id' => $this->secondProduct->id,
            'quantity' => 20,
        ]);
    }

    public function test_all_transaction_endpoints_reject_invalid_quantities_without_changing_stock(): void
    {
        $invalidQuantities = [
            'empty' => '',
            'null' => null,
            'missing' => '__missing__',
            'zero' => 0,
            'negative' => -1,
            'fraction' => 1.5,
            'fraction_string' => '1.5',
            'not_numeric' => 'abc',
        ];

        foreach (['inbounds', 'sales', 'transfers'] as $feature) {
            foreach ($invalidQuantities as $label => $quantity) {
                $item = ['product_id' => $this->product->id];
                if ($label !== 'missing') {
                    $item['quantity'] = $quantity;
                }

                $this->actingAs($this->operator)
                    ->from(route($feature.'.create'))
                    ->post(route($feature.'.store'), $this->payload($feature, [$item]))
                    ->assertRedirect(route($feature.'.create'))
                    ->assertSessionHasErrors('items.0.quantity');

                $this->assertNoInventoryTransactionWasCreated();
                $this->assertInventoryUnchanged();
            }
        }
    }

    public function test_one_invalid_quantity_rejects_every_item_in_multi_item_transactions(): void
    {
        foreach (['inbounds', 'sales', 'transfers'] as $feature) {
            $this->actingAs($this->operator)
                ->from(route($feature.'.create'))
                ->post(route($feature.'.store'), $this->payload($feature, [
                    ['product_id' => $this->product->id, 'quantity' => 2],
                    ['product_id' => $this->secondProduct->id, 'quantity' => 0],
                ]))
                ->assertRedirect(route($feature.'.create'))
                ->assertSessionHasErrors('items.1.quantity');

            $this->assertNoInventoryTransactionWasCreated();
            $this->assertInventoryUnchanged();
        }
    }

    public function test_stock_service_rejects_invalid_quantities_before_any_stock_or_movement_is_written(): void
    {
        $transaction = Transaction::create([
            'trx_no' => 'IN-QTY-SERVICE',
            'type' => 'IN',
            'warehouse_id' => $this->source->id,
            'transaction_date' => '2026-10-09',
            'status' => 'active',
            'user_id' => $this->operator->id,
        ]);

        foreach ([null, '', 0, -1, 1.5, '1.5', '2147483648'] as $invalidQuantity) {
            try {
                StockService::applyTransaction($transaction, [
                    ['product_id' => $this->product->id, 'quantity' => 1],
                    ['product_id' => $this->secondProduct->id, 'quantity' => $invalidQuantity],
                ]);
                $this->fail('Expected an invalid quantity to be rejected.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('Jumlah barang', $exception->getMessage());
            }

            $this->assertSame(10, (int) Stock::query()
                ->where('warehouse_id', $this->source->id)
                ->where('product_id', $this->product->id)
                ->value('quantity'));
            $this->assertSame(20, (int) Stock::query()
                ->where('warehouse_id', $this->source->id)
                ->where('product_id', $this->secondProduct->id)
                ->value('quantity'));
            $this->assertSame(0, StockMovement::query()->count());
        }
    }

    public function test_stock_service_rejects_invalid_persisted_quantity_when_reversing(): void
    {
        $transaction = Transaction::create([
            'trx_no' => 'IN-QTY-REVERSE',
            'type' => 'IN',
            'warehouse_id' => $this->source->id,
            'transaction_date' => '2026-10-09',
            'status' => 'active',
            'user_id' => $this->operator->id,
        ]);
        $transaction->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 0,
            'unit_price' => 10,
            'subtotal' => 0,
        ]);

        try {
            StockService::reverseTransaction($transaction->load('items'));
            $this->fail('Expected an invalid persisted quantity to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Jumlah barang', $exception->getMessage());
        }

        $this->assertSame(10, (int) Stock::query()
            ->where('warehouse_id', $this->source->id)
            ->where('product_id', $this->product->id)
            ->value('quantity'));
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_stock_service_rejects_stock_balance_overflow_without_partial_movement(): void
    {
        Stock::query()
            ->where('warehouse_id', $this->source->id)
            ->where('product_id', $this->product->id)
            ->update(['quantity' => 2147483647]);
        $transaction = Transaction::create([
            'trx_no' => 'IN-QTY-OVERFLOW',
            'type' => 'IN',
            'warehouse_id' => $this->source->id,
            'transaction_date' => '2026-10-09',
            'status' => 'active',
            'user_id' => $this->operator->id,
        ]);

        try {
            StockService::applyTransaction($transaction, [[
                'product_id' => $this->secondProduct->id,
                'quantity' => 1,
            ], [
                'product_id' => $this->product->id,
                'quantity' => 1,
            ]]);
            $this->fail('Expected stock balance overflow to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('batas kapasitas', $exception->getMessage());
        }

        $this->assertSame(2147483647, (int) Stock::query()
            ->where('warehouse_id', $this->source->id)
            ->where('product_id', $this->product->id)
            ->value('quantity'));
        $this->assertSame(20, (int) Stock::query()
            ->where('warehouse_id', $this->source->id)
            ->where('product_id', $this->secondProduct->id)
            ->value('quantity'));
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_quantity_one_is_valid_for_transfer(): void
    {
        $this->actingAs($this->operator)
            ->post(route('transfers.store'), $this->payload('transfers', [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('transaction_items', [
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);
        $this->assertSame(9, (int) Stock::query()
            ->where('warehouse_id', $this->source->id)
            ->where('product_id', $this->product->id)
            ->value('quantity'));
        $this->assertSame(1, (int) Stock::query()
            ->where('warehouse_id', $this->destination->id)
            ->where('product_id', $this->product->id)
            ->value('quantity'));
    }

    public function test_quantity_one_is_valid_for_inbound_and_sale(): void
    {
        $this->actingAs($this->operator)
            ->post(route('inbounds.store'), $this->payload('inbounds', [
                ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => '10.00'],
            ]))
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->post(route('sales.store'), $this->payload('sales', [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ]))
            ->assertRedirect();

        $this->assertSame(2, TransactionItem::query()->count());
        $this->assertSame(2, StockMovement::query()->count());
        $this->assertSame(10, (int) Stock::query()
            ->where('warehouse_id', $this->source->id)
            ->where('product_id', $this->product->id)
            ->value('quantity'));
    }

    public function test_inbound_sale_and_transfer_items_reference_products_without_snapshots(): void
    {
        $this->actingAs($this->operator)
            ->post(route('inbounds.store'), $this->payload('inbounds', [
                ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => '10.00'],
            ]))
            ->assertRedirect();
        $this->actingAs($this->operator)
            ->post(route('sales.store'), $this->payload('sales', [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ]))
            ->assertRedirect();
        $this->actingAs($this->operator)
            ->post(route('transfers.store'), $this->payload('transfers', [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ]))
            ->assertRedirect();

        $this->assertSame(3, TransactionItem::query()->count());
        foreach (TransactionItem::query()->get() as $item) {
            $this->assertSame('Produk Kuantitas Satu', $item->product->name);
            $this->assertSame('QTY-1', $item->product->sku);
        }
    }

    public function test_quantity_validation_error_preserves_blank_input_and_all_forms_have_client_constraints(): void
    {
        foreach (['inbounds', 'sales', 'transfers'] as $feature) {
            $form = $this->actingAs($this->operator)
                ->withSession(['_old_input' => [
                    'items' => [['product_id' => $this->product->id, 'quantity' => null]],
                ]])
                ->get(route($feature.'.create'));

            $form->assertOk()
                ->assertSee('type="number"', false)
                ->assertSee('min="1"', false)
                ->assertSee('step="1"', false)
                ->assertSee('value="" min="1"', false);
        }
    }

    private function payload(string $feature, array $items): array
    {
        $payload = [
            'warehouse_id' => $this->source->id,
            'transaction_date' => '2026-10-09',
            'items' => $items,
        ];

        if ($feature === 'sales') {
            $payload['customer_id'] = $this->customer->id;
        }

        if ($feature === 'transfers') {
            $payload['destination_warehouse_id'] = $this->destination->id;
        }

        return $payload;
    }

    private function assertNoInventoryTransactionWasCreated(): void
    {
        $this->assertSame(0, Transaction::query()->count());
        $this->assertSame(0, TransactionItem::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
    }

    private function assertInventoryUnchanged(): void
    {
        $this->assertSame(10, (int) Stock::query()
            ->where('warehouse_id', $this->source->id)
            ->where('product_id', $this->product->id)
            ->value('quantity'));
        $this->assertSame(20, (int) Stock::query()
            ->where('warehouse_id', $this->source->id)
            ->where('product_id', $this->secondProduct->id)
            ->value('quantity'));
        $this->assertSame(0, (int) Stock::query()
            ->where('warehouse_id', $this->destination->id)
            ->sum('quantity'));
    }
}
