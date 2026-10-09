<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockService
{
    /**
     * Apply stock changes atomically, including when called without a controller transaction.
     *
     * @param  array<int, TransactionItem|array<string, mixed>>  $items
     */
    public static function applyTransaction(Transaction $trx, array $items): void
    {
        $quantities = [];
        foreach ($items as $key => $item) {
            $quantities[$key] = self::validatedQuantity(self::itemQuantity($item));
        }

        DB::transaction(function () use ($trx, $items, $quantities): void {
            self::lockRelevantStocks($trx, $items, false);

            foreach ($items as $key => $item) {
                $transactionItem = $item instanceof TransactionItem ? $item : null;
                $productId = $transactionItem?->product_id ?? ($item['product_id'] ?? null);
                $quantity = $quantities[$key];

                if (! is_int($productId) && ! is_string($productId)) {
                    throw new RuntimeException('Produk transaksi tidak valid.');
                }

                if ($trx->type === 'IN') {
                    self::adjust($trx->warehouse_id, $productId, $quantity, $trx, $transactionItem);

                    continue;
                }

                if ($trx->type === 'OUT') {
                    self::adjust($trx->warehouse_id, $productId, -$quantity, $trx, $transactionItem);

                    continue;
                }

                if ($trx->type === 'TRANSFER') {
                    if ($trx->destination_warehouse_id === null) {
                        throw new RuntimeException('Gudang tujuan wajib diisi untuk transfer.');
                    }

                    self::adjust($trx->warehouse_id, $productId, -$quantity, $trx, $transactionItem);
                    self::adjust($trx->destination_warehouse_id, $productId, $quantity, $trx, $transactionItem);

                    continue;
                }

                throw new RuntimeException("Tipe transaksi '{$trx->type}' tidak didukung.");
            }
        }, 3);
    }

    public static function reverseTransaction(Transaction $trx): void
    {
        $items = $trx->items->all();
        $quantities = [];
        foreach ($items as $key => $item) {
            $quantities[$key] = self::validatedQuantity(self::itemQuantity($item));
        }

        DB::transaction(function () use ($trx, $items, $quantities): void {
            self::lockRelevantStocks($trx, $items, true);

            foreach ($items as $key => $item) {
                $quantity = $quantities[$key];

                if ($trx->type === 'IN') {
                    self::adjust($trx->warehouse_id, $item->product_id, -$quantity, $trx, $item);

                    continue;
                }

                if ($trx->type === 'OUT') {
                    self::adjust($trx->warehouse_id, $item->product_id, $quantity, $trx, $item);

                    continue;
                }

                if ($trx->type === 'TRANSFER') {
                    if ($trx->destination_warehouse_id === null) {
                        throw new RuntimeException('Gudang tujuan tidak ditemukan untuk transfer ini.');
                    }

                    self::adjust($trx->destination_warehouse_id, $item->product_id, -$quantity, $trx, $item);
                    self::adjust($trx->warehouse_id, $item->product_id, $quantity, $trx, $item);

                    continue;
                }

                throw new RuntimeException("Tipe transaksi '{$trx->type}' tidak dapat dibatalkan.");
            }
        }, 3);
    }

    private static function itemQuantity(mixed $item): mixed
    {
        if ($item instanceof TransactionItem) {
            return $item->getAttribute('quantity');
        }

        if (is_array($item)) {
            return $item['quantity'] ?? null;
        }

        throw new RuntimeException('Detail transaksi tidak valid.');
    }

    private static function validatedQuantity(mixed $quantity): int
    {
        if (is_int($quantity)) {
            $integer = $quantity;
        } elseif (is_string($quantity) && preg_match('/\A[0-9]+\z/', $quantity) === 1) {
            $normalized = ltrim($quantity, '0');
            $normalized = $normalized === '' ? '0' : $normalized;

            if (
                strlen($normalized) > 10
                || (strlen($normalized) === 10 && strcmp($normalized, '2147483647') > 0)
            ) {
                throw new RuntimeException('Jumlah barang harus bilangan bulat antara 1 dan 2147483647.');
            }

            $integer = (int) $normalized;
        } else {
            throw new RuntimeException('Jumlah barang harus bilangan bulat antara 1 dan 2147483647.');
        }

        if ($integer < 1 || $integer > 2147483647) {
            throw new RuntimeException('Jumlah barang harus bilangan bulat antara 1 dan 2147483647.');
        }

        return $integer;
    }

    private static function adjust(
        int|string $warehouseId,
        int|string $productId,
        int $qty,
        Transaction $transaction,
        ?TransactionItem $transactionItem,
    ): void {
        $stockQuery = Stock::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId);
        $stock = (clone $stockQuery)->lockForUpdate()->first();

        if ($stock === null && $qty > 0) {
            Stock::query()->firstOrCreate(
                ['warehouse_id' => $warehouseId, 'product_id' => $productId],
                ['quantity' => 0],
            );
            $stock = (clone $stockQuery)->lockForUpdate()->first();
        }

        if ($stock === null) {
            throw new RuntimeException('Stok tidak mencukupi untuk transaksi ini.');
        }

        $balance = (int) $stock->quantity + $qty;

        if ($qty < 0) {
            $product = Product::query()->whereKey($productId)->first();
            $minStock = $product?->min_stock ?? 1;

            if ($balance < $minStock) {
                $productName = $product?->name ?? "Product #{$productId}";
                throw new RuntimeException(
                    "Stok {$productName} di gudang ini akan tersisa {$balance} unit. ".
                    "Minimum stok yang diperbolehkan adalah {$minStock} unit. Transaksi ditolak."
                );
            }
        }

        if ($balance < 0) {
            throw new RuntimeException('Stok tidak mencukupi untuk transaksi ini.');
        }

        if ($balance > 2147483647) {
            throw new RuntimeException('Jumlah stok melebihi batas kapasitas penyimpanan.');
        }

        $stock->quantity = $balance;
        $stock->save();

        StockMovement::create([
            'transaction_id' => $transaction->getKey(),
            'transaction_item_id' => $transactionItem?->getKey(),
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'quantity' => $qty,
            'balance_after' => $balance,
        ]);
    }

    /**
     * Create only rows that receive stock, then acquire every participating
     * stock lock in the same warehouse/product order for every transaction.
     *
     * @param  array<int, TransactionItem|array<string, mixed>>  $items
     */
    private static function lockRelevantStocks(Transaction $trx, array $items, bool $reverse): void
    {
        $keys = [];

        foreach ($items as $item) {
            $productId = $item instanceof TransactionItem ? $item->product_id : ($item['product_id'] ?? null);
            if (! is_int($productId) && ! is_string($productId)) {
                throw new RuntimeException('Produk transaksi tidak valid.');
            }

            if ($trx->type === 'TRANSFER' && $trx->destination_warehouse_id === null) {
                throw new RuntimeException('Gudang tujuan wajib diisi untuk transfer.');
            }

            [$warehouseIds, $createWarehouseIds] = match ($trx->type) {
                'IN' => [[$trx->warehouse_id], $reverse ? [] : [$trx->warehouse_id]],
                'OUT' => [[$trx->warehouse_id], $reverse ? [$trx->warehouse_id] : []],
                'TRANSFER' => [
                    [$trx->warehouse_id, $trx->destination_warehouse_id],
                    $reverse ? [$trx->warehouse_id] : [$trx->destination_warehouse_id],
                ],
                default => throw new RuntimeException("Tipe transaksi '{$trx->type}' tidak didukung."),
            };

            foreach ($warehouseIds as $warehouseId) {
                $key = (string) $warehouseId.':'.(string) $productId;
                $keys[$key] ??= [
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'create' => false,
                ];
                $keys[$key]['create'] = $keys[$key]['create']
                    || in_array($warehouseId, $createWarehouseIds, true);
            }
        }

        uasort($keys, static fn (array $left, array $right): int => ((int) $left['warehouse_id'] <=> (int) $right['warehouse_id'])
            ?: ((int) $left['product_id'] <=> (int) $right['product_id'])
        );

        foreach ($keys as $key) {
            if ($key['create']) {
                Stock::query()->firstOrCreate(
                    ['warehouse_id' => $key['warehouse_id'], 'product_id' => $key['product_id']],
                    ['quantity' => 0],
                );
            }
        }

        foreach ($keys as $key) {
            Stock::query()
                ->where('warehouse_id', $key['warehouse_id'])
                ->where('product_id', $key['product_id'])
                ->lockForUpdate()
                ->first();
        }
    }
}
