@extends('layouts.app')

@section('content')
<section class="crud-shell">
    <header class="crud-heading">
        <div>
            <a href="{{ route('reports.index') }}" class="back-link">&larr; Semua laporan</a>
            <p class="eyebrow">PT Sinar Nusantara</p>
            <h1 class="crud-title">Laporan Penjualan</h1>
            <p class="crud-description">Item transaksi penjualan berstatus aktif.</p>
        </div>
    </header>

    <form method="GET" action="{{ route('reports.sales') }}" class="barang-form">
        <div class="barang-form-grid">
            <label class="crud-field">Dari tanggal
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="crud-input">
            </label>
            <label class="crud-field">Sampai tanggal
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="crud-input">
            </label>
            <label class="crud-field">Gudang
                <select name="warehouse_id" class="crud-input">
                    <option value="">Semua gudang</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(($filters['warehouse_id'] ?? '') == $warehouse->id)>
                            {{ $warehouse->code }} — {{ $warehouse->name }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="crud-field">Produk
                <select name="product_id" class="crud-input">
                    <option value="">Semua produk</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected(($filters['product_id'] ?? '') == $product->id)>
                            {{ $product->sku }} — {{ $product->name }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="crud-field">Pelanggan
                <select name="customer_id" class="crud-input">
                    <option value="">Semua pelanggan</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(($filters['customer_id'] ?? '') == $customer->id)>
                            {{ $customer->name }}
                        </option>
                    @endforeach
                </select>
            </label>
        </div>
        @if ($errors->any())
            <div class="form-error-summary" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif
        <div class="crud-form-actions">
            <a href="{{ route('reports.sales') }}" class="button button-secondary">Reset</a>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="button button-secondary">Export CSV</a>
            <button type="submit" class="button button-primary">Terapkan Filter</button>
        </div>
    </form>

    <div class="table-wrap">
        <table class="barang-table">
            <thead>
                <tr><th>No Trx</th><th>Tanggal</th><th>Pelanggan</th><th>Gudang</th><th>SKU</th><th>Nama</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['trx_no'] }}</td>
                        <td>{{ $row['transaction_date'] }}</td>
                        <td>{{ $row['customer'] ?? '—' }}</td>
                        <td>{{ $row['warehouse'] ?? '—' }}</td>
                        <td>{{ $row['sku'] ?? '—' }}</td>
                        <td>{{ $row['product_name'] }}</td>
                        <td>{{ $row['quantity'] }}</td>
                        <td>{{ \App\Support\TransactionMoney::format($row['unit_price']) }}</td>
                        <td>{{ \App\Support\TransactionMoney::format($row['subtotal']) }}</td>
                    </tr>
                @empty
                    <tr><td class="empty-state" colspan="9">Tidak ada penjualan untuk filter tersebut.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
