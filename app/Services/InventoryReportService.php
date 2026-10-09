<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class InventoryReportService
{
    /**
     * @param  array{warehouse_id?: int|string|null, product_id?: int|string|null}  $filters
     */
    public function stockQuery(array $filters): Builder
    {
        return DB::table('stocks as s')
            ->join('products as p', 'p.id', '=', 's.product_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->join('warehouses as w', 'w.id', '=', 's.warehouse_id')
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, $warehouseId) => $query->where('s.warehouse_id', $warehouseId))
            ->when($filters['product_id'] ?? null, fn (Builder $query, $productId) => $query->where('s.product_id', $productId))
            ->select([
                'p.sku',
                'p.name as product_name',
                'c.name as category_name',
                'w.name as warehouse_name',
                's.quantity',
                'p.is_active as product_is_active',
            ])
            ->orderBy('w.name')
            ->orderBy('p.name');
    }

    /**
     * @param  array{start_date?: string|null, end_date?: string|null, warehouse_id?: int|string|null, product_id?: int|string|null}  $filters
     */
    public function inboundQuery(array $filters): Builder
    {
        return $this->transactionItemQuery('IN', $filters)
            ->select([
                't.id as transaction_id',
                't.trx_no',
                't.transaction_date',
                'w.name as warehouse_name',
                'p.sku',
                'p.name as product_name',
                'ti.quantity',
                DB::raw('ti.unit_price as unit_price'),
                DB::raw('ti.subtotal as subtotal'),
                't.status',
                'u.name as user_name',
            ]);
    }

    /**
     * @param  array{start_date?: string|null, end_date?: string|null, warehouse_id?: int|string|null, product_id?: int|string|null}  $filters
     */
    public function salesQuery(array $filters): Builder
    {
        return $this->transactionItemQuery('OUT', $filters)
            ->leftJoin('customers as cu', 'cu.id', '=', 't.customer_id')
            ->select([
                't.id as transaction_id',
                't.trx_no',
                't.transaction_date',
                'cu.name as customer_name',
                'w.name as warehouse_name',
                'p.sku',
                'p.name as product_name',
                'ti.quantity',
                DB::raw('ti.unit_price as unit_price'),
                DB::raw('ti.subtotal as subtotal'),
                't.status',
                'u.name as user_name',
            ]);
    }

    /**
     * @param  array{start_date?: string|null, end_date?: string|null, warehouse_id?: int|string|null, product_id?: int|string|null}  $filters
     */
    private function transactionItemQuery(string $type, array $filters): Builder
    {
        return DB::table('transactions as t')
            ->join('transaction_items as ti', 'ti.transaction_id', '=', 't.id')
            ->join('products as p', 'p.id', '=', 'ti.product_id')
            ->join('warehouses as w', 'w.id', '=', 't.warehouse_id')
            ->join('users as u', 'u.id', '=', 't.user_id')
            ->where('t.type', $type)
            ->when($filters['start_date'] ?? null, fn (Builder $query, $date) => $query->whereDate('t.transaction_date', '>=', $date))
            ->when($filters['end_date'] ?? null, fn (Builder $query, $date) => $query->whereDate('t.transaction_date', '<=', $date))
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, $warehouseId) => $query->where('t.warehouse_id', $warehouseId))
            ->when($filters['product_id'] ?? null, fn (Builder $query, $productId) => $query->where('ti.product_id', $productId))
            ->orderByDesc('t.transaction_date')
            ->orderByDesc('t.id')
            ->orderBy('ti.id');
    }
}
