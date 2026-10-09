<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Support\Frontend;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        return Frontend::render('products.index', 'products.index', [
            'products' => Product::query()->paginate(15),
        ]);
    }

    public function create(): View
    {
        return Frontend::render('products.create', 'products.create', [
            'categories' => DB::table('categories')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:255', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'cost_price' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'selling_price' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'min_stock' => ['required', 'integer', 'min:1', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        Product::create($validated);

        return redirect()->route('products.index')->with('success', 'Data berhasil ditambahkan.');
    }

    public function show(Product $product): RedirectResponse
    {
        return redirect()->route('products.edit', $product);
    }

    public function edit(Product $product): View
    {
        return Frontend::render('products.edit', 'products.edit', [
            'product' => $product,
            'categories' => DB::table('categories')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product)],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'cost_price' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'selling_price' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'min_stock' => ['required', 'integer', 'min:1', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $product->update($validated);

        return redirect()->route('products.index')->with('success', 'Data berhasil diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $hasHistory = Stock::query()->where('product_id', $product->id)->exists()
            || StockMovement::query()->where('product_id', $product->id)->exists()
            || $product->transactionItems()->exists();

        if ($hasHistory) {
            $product->update(['is_active' => false]);

            return redirect()
                ->route('products.index')
                ->with('success', 'Produk dinonaktifkan agar histori transaksi tetap utuh.');
        }

        $product->delete();

        return redirect()->route('products.index')->with('success', 'Data berhasil dihapus.');
    }
}
