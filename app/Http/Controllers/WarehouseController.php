<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\Warehouse;
use App\Support\Frontend;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(): View
    {
        return Frontend::render('warehouses.index', 'warehouses.index', [
            'warehouses' => Warehouse::query()->paginate(15),
        ]);
    }

    public function create(): View
    {
        return Frontend::render('warehouses.create', 'warehouses.create', [

        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:warehouses,code'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        Warehouse::create($validated);

        return redirect()->route('warehouses.index')->with('success', 'Data berhasil ditambahkan.');
    }

    public function show(Warehouse $warehouse): RedirectResponse
    {
        return redirect()->route('warehouses.edit', $warehouse);
    }

    public function edit(Warehouse $warehouse): View
    {
        return Frontend::render('warehouses.edit', 'warehouses.edit', [
            'warehouse' => $warehouse,

        ]);
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('warehouses', 'code')->ignore($warehouse)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $warehouse->update($validated);

        return redirect()->route('warehouses.index')->with('success', 'Data berhasil diperbarui.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        if (
            Stock::query()->where('warehouse_id', $warehouse->id)->exists()
            || StockMovement::query()->where('warehouse_id', $warehouse->id)->exists()
            || Transaction::query()
                ->where('warehouse_id', $warehouse->id)
                ->orWhere('destination_warehouse_id', $warehouse->id)
                ->exists()
        ) {
            return redirect()
                ->route('warehouses.index')
                ->withErrors(['warehouse' => 'Gudang yang masih digunakan stok atau histori transaksi tidak dapat dihapus.']);
        }

        $warehouse->delete();

        return redirect()->route('warehouses.index')->with('success', 'Data berhasil dihapus.');
    }
}