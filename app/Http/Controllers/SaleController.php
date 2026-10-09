<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Support\Frontend;
use App\Support\TransactionMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class SaleController extends Controller
{
    public function create(): View
    {
        return Frontend::render('sales.create', 'sales.create', [
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
            'customers' => Customer::query()->orderBy('name')->get(),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'transaction_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('is_active', true)],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ], [
            'items.*.quantity.required' => 'Jumlah barang wajib diisi.',
            'items.*.quantity.integer' => 'Jumlah barang harus berupa bilangan bulat.',
            'items.*.quantity.min' => 'Jumlah barang minimal 1.',
            'items.*.quantity.max' => 'Jumlah barang melebihi batas maksimum.',
        ]);

        try {
            $transaction = DB::transaction(function () use ($validated, $request): Transaction {
                $warehouse = Warehouse::query()
                    ->whereKey($validated['warehouse_id'])
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->firstOrFail();
                $products = Product::query()
                    ->whereIn('id', collect($validated['items'])->pluck('product_id')->unique())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($products->count() !== count($validated['items']) || $products->contains(fn (Product $product): bool => ! $product->is_active)) {
                    throw ValidationException::withMessages([
                        'items' => 'Pastikan setiap produk masih aktif dan hanya dicantumkan satu kali.',
                    ]);
                }

                $transaction = Transaction::create([
                    'trx_no' => 'OUT-'.Str::ulid(),
                    'type' => 'OUT',
                    'warehouse_id' => $warehouse->id,
                    'customer_id' => $validated['customer_id'],
                    'transaction_date' => $validated['transaction_date'],
                    'status' => 'active',
                    'user_id' => $request->user()->id,
                    'notes' => $validated['notes'] ?? null,
                ]);

                $items = [];
                foreach ($validated['items'] as $item) {
                    $product = $products->get($item['product_id']);
                    $unitPriceMinor = TransactionMoney::toMinorUnits($product->selling_price);
                    $subtotalMinor = TransactionMoney::lineSubtotalMinorUnits(
                        $unitPriceMinor,
                        (int) $item['quantity'],
                    );

                    $transactionItem = [
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'unit_price' => TransactionMoney::fromMinorUnits($unitPriceMinor),
                        'subtotal' => TransactionMoney::fromMinorUnits($subtotalMinor),
                    ];

                    $items[] = $transaction->items()->create($transactionItem);
                }

                StockService::applyTransaction($transaction, $items);

                return $transaction;
            }, 3);

            return redirect()
                ->route('transactions.show', $transaction)
                ->with('success', 'Penjualan berhasil disimpan.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['transaction' => 'Transaksi penjualan gagal disimpan. '.$exception->getMessage()]);
        }
    }

    public function show(Transaction $transaction): RedirectResponse
    {
        return redirect()->route('transactions.show', $transaction);
    }
}
