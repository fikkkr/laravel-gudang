@extends('layouts.app')

@section('content')
<section class="crud-shell">
    <header class="crud-heading">
        <div>
            <p class="eyebrow">PT Sinar Nusantara</p>
            <h1 class="crud-title">Laporan Inventory</h1>
            <p class="crud-description">Pilih laporan untuk meninjau stok dan aktivitas inventory.</p>
        </div>
    </header>

    <div class="barang-form-grid">
        <article class="crud-summary">
            <div>
                <p class="eyebrow">Persediaan</p>
                <h2 class="crud-title">Laporan Stok</h2>
                <p class="crud-description">Lihat jumlah stok terkini per gudang, produk, dan kategori.</p>
                <a href="{{ route('reports.stock') }}" class="button button-primary">Laporan Stok</a>
            </div>
        </article>
        <article class="crud-summary">
            <div>
                <p class="eyebrow">Penerimaan</p>
                <h2 class="crud-title">Laporan Barang Masuk</h2>
                <p class="crud-description">Tinjau penerimaan barang pada bulan ini.</p>
                <a href="{{ route('reports.inbound', ['from' => $from, 'to' => $to]) }}" class="button button-primary">Laporan Barang Masuk</a>
            </div>
        </article>
        <article class="crud-summary">
            <div>
                <p class="eyebrow">Penjualan</p>
                <h2 class="crud-title">Laporan Penjualan</h2>
                <p class="crud-description">Tinjau item penjualan bulan ini menurut pelanggan.</p>
                <a href="{{ route('reports.sales', ['from' => $from, 'to' => $to]) }}" class="button button-primary">Laporan Penjualan</a>
            </div>
        </article>
    </div>
</section>
@endsection
