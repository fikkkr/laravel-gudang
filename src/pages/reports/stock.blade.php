@extends('layouts.app')

@section('content')
<section class="crud-shell">
    <header class="crud-heading">
        <div>
            <a href="{{ route('reports.index') }}" class="back-link">&larr; Semua laporan</a>
            <p class="eyebrow">PT Sinar Nusantara</p>
            <h1 class="crud-title">Laporan Stok</h1>
            <p class="crud-description">Jumlah stok terkini per kombinasi produk dan gudang.</p>
        </div>
    </header>

    <form method="GET" action="{{ route('reports.stock') }}" class="barang-form">
        <div class="barang-form-grid">
            <label class="crud-field">
                Gudang
                <select name="warehouse_id" class="crud-input">
                    <option value="">Semua gudang</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(request('warehouse_id') == $warehouse->id)>
                            {{ $warehouse->code }} — {{ $warehouse->name }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="crud-field">
                Produk
                <select name="product_id" class="crud-input">
                    <option value="">Semua produk</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>
                            {{ $product->sku }} — {{ $product->name }}
                        </option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="crud-form-actions">
            <a href="{{ route('reports.stock') }}" class="button button-secondary">Reset</a>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="button button-secondary">Export CSV</a>
            <button type="submit" class="button button-primary">Terapkan Filter</button>
        </div>
    </form>

    <div class="table-wrap">
        <table class="barang-table">
            <thead>
                <tr><th>Gudang</th><th>SKU</th><th>Nama Produk</th><th>Kategori</th><th>Qty</th></tr>
            </thead>
            <tbody>
                @forelse ($stocks as $stock)
                    <tr>
                        <td>{{ $stock->warehouse?->name ?? '—' }}</td>
                        <td>{{ $stock->product?->sku ?? '—' }}</td>
                        <td>{{ $stock->product?->name ?? 'Produk dihapus' }}</td>
                        <td>{{ $stock->product?->category?->name ?? '—' }}</td>
                        <td>{{ $stock->quantity }}</td>
                    </tr>
                @empty
                    <tr><td class="empty-state" colspan="5">Tidak ada data stok untuk filter tersebut.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
