@extends('layouts.app')

@section('content')
<section class="crud-shell">
    <header class="crud-heading">
        <div class="crud-heading-copy">
            <p class="eyebrow">Inventory PT Sinar Nusantara</p>
            <h1 class="crud-title">Dashboard</h1>
            <p class="crud-description">Ringkasan persediaan dan aktivitas terbaru.</p>
        </div>
        <a href="{{ route('inbounds.create') }}" class="button button-primary">+ Barang Masuk</a>
    </header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <article class="rounded border border-neutral-200 bg-white p-5">
            <p class="text-sm text-neutral-500">Total Produk</p>
            <p class="mt-2 text-3xl font-semibold text-neutral-900">{{ number_format($totalProducts) }}</p>
        </article>
        <article class="rounded border border-neutral-200 bg-white p-5">
            <p class="text-sm text-neutral-500">Total Gudang</p>
            <p class="mt-2 text-3xl font-semibold text-neutral-900">{{ number_format($totalWarehouses) }}</p>
        </article>
        <article class="rounded border border-neutral-200 bg-white p-5">
            <p class="text-sm text-neutral-500">Total Pelanggan</p>
            <p class="mt-2 text-3xl font-semibold text-neutral-900">{{ number_format($totalCustomers) }}</p>
        </article>
        <article class="rounded border border-neutral-200 bg-white p-5">
            <p class="text-sm text-neutral-500">Total Kategori</p>
            <p class="mt-2 text-3xl font-semibold text-neutral-900">{{ number_format($totalCategories) }}</p>
        </article>
        <article class="rounded border border-neutral-200 bg-white p-5">
            <p class="text-sm text-neutral-500">Penjualan Hari Ini</p>
            <p class="mt-2 text-3xl font-semibold text-neutral-900">{{ number_format($salesToday) }}</p>
        </article>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-2">
        <section>
            <header class="mb-3 flex items-end justify-between">
                <div>
                    <p class="eyebrow">Persediaan</p>
                    <h2 class="text-lg font-semibold text-neutral-900">Stok Terendah</h2>
                </div>
                <span class="text-sm text-neutral-500">5 terendah</span>
            </header>
            <div class="table-wrap">
                <table class="barang-table">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Gudang</th>
                            <th>Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lowStock as $stock)
                            <tr>
                                <td>{{ $stock->product?->name ?? 'Produk dihapus' }}</td>
                                <td>{{ $stock->warehouse?->name ?? 'Gudang dihapus' }}</td>
                                <td class="font-semibold">{{ number_format($stock->quantity) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty-state">Belum ada data stok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section>
            <header class="mb-3 flex items-end justify-between">
                <div>
                    <p class="eyebrow">Aktivitas</p>
                    <h2 class="text-lg font-semibold text-neutral-900">Transaksi Terbaru</h2>
                </div>
                <a href="{{ route('transactions.index') }}" class="text-sm text-neutral-600 hover:text-black">Lihat semua</a>
            </header>
            <div class="table-wrap">
                <table class="barang-table">
                    <thead>
                        <tr>
                            <th>No. Transaksi</th>
                            <th>Tipe</th>
                            <th>Gudang</th>
                            <th>Pelanggan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentTransactions as $transaction)
                            <tr>
                                <td><a href="{{ route('transactions.show', $transaction) }}" class="underline">{{ $transaction->trx_no }}</a></td>
                                <td>{{ $transaction->type }}</td>
                                <td>{{ $transaction->warehouse?->name ?? '—' }}</td>
                                <td>{{ $transaction->customer?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">Belum ada transaksi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</section>
@endsection
