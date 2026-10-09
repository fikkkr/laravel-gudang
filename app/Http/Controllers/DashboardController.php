<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\Warehouse;
use App\Support\Frontend;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return Frontend::render('dashboard', 'dashboard', [
            'totalProducts' => Product::query()->count(),
            'totalWarehouses' => Warehouse::query()->count(),
            'totalCustomers' => Customer::query()->count(),
            'totalCategories' => Category::query()->count(),
            'salesToday' => Transaction::query()
                ->where('type', 'OUT')
                ->where('status', 'active')
                ->whereDate('transaction_date', today())
                ->count(),
            'lowStock' => Stock::query()
                ->with(['product', 'warehouse'])
                ->get()
                ->filter(fn (Stock $stock): bool => $stock->quantity <= ($stock->product?->min_stock ?? 1))
                ->sortBy('quantity')
                ->take(5),
            'recentTransactions' => Transaction::query()
                ->with(['warehouse', 'customer'])
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }
}
