<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Support\Frontend;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class TransferController extends Controller
{
    public function create(): View
    {
        return Frontend::render('transfers.create', 'transfers.create', [
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'destination_warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')->where('is_active', true),
                'different:warehouse_id',
            ],
            'transaction_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')->where('is_active', true),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ], [
            'items.*.quantity.required' => 'Jumlah barang wajib diisi.',
            'items.*.quantity.integer' => 'Jumlah barang harus berupa bilangan bulat.',
            'items.*.quantity.min' => 'Jumlah barang minimal 1.',
            'items.*.quantity.max' => 'Jumlah barang melebihi batas maksimum.',
        ]);

        try {
            DB::beginTransaction();

            $warehouses = Warehouse::query()
                ->whereIn('id', [$validated['warehouse_id'], $validated['destination_warehouse_id']])
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($warehouses->count() !== 2) {
                throw new \RuntimeException('Pastikan gudang asal dan tujuan masih aktif.');
            }

            $products = Product::query()
                ->whereIn('id', collect($validated['items'])->pluck('product_id')->unique())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if (
                $products->count() !== collect($validated['items'])->pluck('product_id')->unique()->count()
                || $products->contains(fn (Product $product): bool => ! $product->is_active)
            ) {
                throw new \RuntimeException('Pastikan setiap produk masih tersedia.');
            }

            $transaction = Transaction::create([
                'trx_no' => 'TRF-'.Str::ulid(),
                'type' => 'TRANSFER',
                'warehouse_id' => $validated['warehouse_id'],
                'destination_warehouse_id' => $validated['destination_warehouse_id'],
                'transaction_date' => $validated['transaction_date'],
                'status' => 'active',
                'user_id' => $request->user()->id,
                'notes' => $validated['notes'] ?? null,
            ]);

            $items = [];
            foreach ($validated['items'] as $item) {
                $product = $products->get($item['product_id']);
                $transactionItem = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => 0,
                    'subtotal' => 0,
                ];

                $items[] = $transaction->items()->create($transactionItem);
            }

            StockService::applyTransaction($transaction, $items);

            DB::commit();

            return redirect()
                ->route('transactions.show', $transaction)
                ->with('success', 'Transfer stok berhasil disimpan.');
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['transaction' => 'Transfer stok gagal disimpan. '.$exception->getMessage()]);
        }
    }

    public function show(Transaction $transaction): RedirectResponse
    {
        return redirect()->route('transactions.show', $transaction);
    }
}
