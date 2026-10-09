<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\StockService;
use App\Support\Frontend;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class TransactionController extends Controller
{
    public function index(): View
    {
        return Frontend::render('transactions.index', 'transactions.index', [
            'transactions' => Transaction::query()
                ->with(['warehouse', 'customer', 'items'])
                ->latest()
                ->paginate(15),
        ]);
    }

    public function show(Transaction $transaction): View
    {
        $transaction->load(['items.product', 'warehouse', 'destinationWarehouse', 'customer', 'user']);

        return Frontend::render('transactions.show', 'transactions.show', [
            'transaction' => $transaction,
        ]);
    }

    public function cancel(Request $request, Transaction $transaction): RedirectResponse
    {
        $validated = $request->validate([
            'cancel_reason' => ['nullable', 'string'],
        ]);

        try {
            return DB::transaction(function () use ($transaction, $request, $validated): RedirectResponse {
                $lockedTransaction = Transaction::query()
                    ->whereKey($transaction->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedTransaction->status === 'cancelled') {
                    throw ValidationException::withMessages(['transaction' => 'Sudah dibatalkan']);
                }

                $lockedTransaction->load('items');
                StockService::reverseTransaction($lockedTransaction);

                $lockedTransaction->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_by' => $request->user()->id,
                    'cancel_reason' => $validated['cancel_reason'] ?? null,
                ]);

                return redirect()
                    ->route('transactions.show', $lockedTransaction)
                    ->with('success', 'Transaksi dibatalkan, stok direstore.');
            }, 3);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            $message = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Pembatalan transaksi gagal diproses.';

            return redirect()
                ->route('transactions.show', $transaction)
                ->withInput()
                ->withErrors(['transaction' => $message]);
        }
    }
}
