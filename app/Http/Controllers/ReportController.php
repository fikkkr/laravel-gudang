<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\Warehouse;
use App\Support\Frontend;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        $from = now()->startOfMonth()->toDateString();
        $to = now()->endOfMonth()->toDateString();

        return Frontend::render('reports.index', 'reports.index', [
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function stock(Request $request): View|Response
    {
        $filters = $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        $stocks = Stock::with(['warehouse', 'product', 'product.category'])
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, $id) => $query->where('warehouse_id', $id))
            ->when($filters['product_id'] ?? null, fn (Builder $query, $id) => $query->where('product_id', $id))
            ->orderBy('warehouse_id')
            ->orderBy('product_id')
            ->get();

        if ($request->query('export') === 'csv') {
            return $this->streamCsv(
                $stocks->map(fn (Stock $stock): array => [
                    $stock->warehouse?->name,
                    $stock->product?->sku,
                    $stock->product?->name,
                    $stock->product?->category?->name,
                    $stock->quantity,
                ])->all(),
                ['Gudang', 'SKU', 'Nama Produk', 'Kategori', 'Qty'],
                $this->filename('stock'),
            );
        }

        return Frontend::render('reports.stock', 'reports.stock', [
            'stocks' => $stocks,
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'code', 'name']),
            'products' => Product::query()->orderBy('name')->get(['id', 'sku', 'name']),
        ]);
    }

    public function inbound(Request $request): View|Response
    {
        $filters = $this->dateFilters($request);
        $transactions = Transaction::with(['warehouse', 'items.product', 'user'])
            ->where('type', 'IN')
            ->where('status', 'active')
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, $id) => $query->where('warehouse_id', $id))
            ->when($filters['product_id'] ?? null, fn (Builder $query, $id) => $query->whereHas(
                'items',
                fn (Builder $items) => $items->where('product_id', $id),
            ))
            ->when($filters['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('transaction_date', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, $date) => $query->whereDate('transaction_date', '<=', $date))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();
        $rows = $this->transactionRows($transactions, $filters['product_id'] ?? null);

        if ($request->query('export') === 'csv') {
            return $this->streamCsv(
                array_map(fn (array $row): array => [
                    $row['trx_no'],
                    $row['transaction_date'],
                    $row['warehouse'],
                    $row['sku'],
                    $row['product_name'],
                    $row['quantity'],
                    $row['unit_price'],
                    $row['subtotal'],
                ], $rows),
                ['No Trx', 'Tanggal', 'Gudang', 'SKU', 'Nama Produk', 'Qty', 'Harga', 'Subtotal'],
                $this->filename('inbound'),
            );
        }

        return Frontend::render('reports.inbound', 'reports.inbound', [
            'rows' => $rows,
            'filters' => $filters,
            ...$this->filterOptions(),
        ]);
    }

    public function sales(Request $request): View|Response
    {
        $filters = $this->dateFilters($request, true);
        $transactions = Transaction::with(['warehouse', 'customer', 'items.product', 'user'])
            ->where('type', 'OUT')
            ->where('status', 'active')
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, $id) => $query->where('warehouse_id', $id))
            ->when($filters['product_id'] ?? null, fn (Builder $query, $id) => $query->whereHas(
                'items',
                fn (Builder $items) => $items->where('product_id', $id),
            ))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, $id) => $query->where('customer_id', $id))
            ->when($filters['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('transaction_date', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, $date) => $query->whereDate('transaction_date', '<=', $date))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();
        $rows = $this->transactionRows($transactions, $filters['product_id'] ?? null);

        if ($request->query('export') === 'csv') {
            return $this->streamCsv(
                array_map(fn (array $row): array => [
                    $row['trx_no'],
                    $row['transaction_date'],
                    $row['customer'],
                    $row['warehouse'],
                    $row['sku'],
                    $row['product_name'],
                    $row['quantity'],
                    $row['unit_price'],
                    $row['subtotal'],
                ], $rows),
                ['No Trx', 'Tanggal', 'Pelanggan', 'Gudang', 'SKU', 'Nama', 'Qty', 'Harga', 'Subtotal'],
                $this->filename('sales'),
            );
        }

        return Frontend::render('reports.sales', 'reports.sales', [
            'rows' => $rows,
            'filters' => $filters,
            ...$this->filterOptions(),
        ]);
    }

    public function stocks(Request $request): View|Response
    {
        return $this->stock($request);
    }

    public function inbounds(Request $request): View|Response
    {
        return $this->inbound($request);
    }

    public function exportStocks(Request $request): View|Response
    {
        $request->query->set('export', 'csv');

        return $this->stock($request);
    }

    public function exportInbounds(Request $request): View|Response
    {
        $request->query->set('export', 'csv');

        return $this->inbound($request);
    }

    public function exportSales(Request $request): View|Response
    {
        $request->query->set('export', 'csv');

        return $this->sales($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function dateFilters(Request $request, bool $withCustomer = false): array
    {
        $query = $request->query->all();
        $query['from'] = $query['from'] ?? $query['start_date'] ?? now()->startOfMonth()->toDateString();
        $query['to'] = $query['to'] ?? $query['end_date'] ?? now()->endOfMonth()->toDateString();

        $rules = [
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
        if ($withCustomer) {
            $rules['customer_id'] = ['nullable', 'integer', 'exists:customers,id'];
        }

        $validated = validator($query, $rules)->validate();

        return array_filter($validated, static fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array<int, array<string, mixed>>
     */
    private function transactionRows(Collection $transactions, int|string|null $productId = null): array
    {
        $rows = [];

        foreach ($transactions as $transaction) {
            foreach ($transaction->items as $item) {
                if ($productId !== null && (string) $item->product_id !== (string) $productId) {
                    continue;
                }

                $rows[] = [
                    'trx_no' => $transaction->trx_no,
                    'transaction_date' => $transaction->transaction_date->toDateString(),
                    'customer' => $transaction->customer?->name,
                    'warehouse' => $transaction->warehouse?->name,
                    'sku' => $item->product?->sku,
                    'product_name' => $item->product?->name ?? 'Produk dihapus',
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array{warehouses: \Illuminate\Database\Eloquent\Collection, products: \Illuminate\Database\Eloquent\Collection, customers: \Illuminate\Database\Eloquent\Collection}
     */
    private function filterOptions(): array
    {
        return [
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'code', 'name']),
            'products' => Product::query()->orderBy('name')->get(['id', 'sku', 'name']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, string>  $headers
     */
    private function streamCsv(array $rows, array $headers, string $filename): Response
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Tidak dapat membuka stream ekspor CSV.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $headers, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, array_map($this->safeCsvValue(...), $row), ',', '"', '');
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        if ($content === false) {
            throw new \RuntimeException('Tidak dapat membaca stream ekspor CSV.');
        }

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function safeCsvValue(mixed $value): mixed
    {
        if ($value === null || ! is_scalar($value)) {
            return '';
        }
        if (! is_string($value) || is_numeric($value)) {
            return $value;
        }
        if (preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }

    private function filename(string $type): string
    {
        return 'laporan-'.$type.'-'.now()->format('Y-m-d').'.csv';
    }
}
