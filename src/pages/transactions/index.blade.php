@extends('layouts.app')

@section('content')
<section class="crud-shell">
    <header class="crud-heading">
        <div class="crud-heading-copy">
            <p class="eyebrow">Persediaan</p>
            <h1 class="crud-title">Riwayat Transaksi</h1>
            <p class="crud-description">Transaksi terbaru inventori.</p>
        </div>
        <a href="{{ route('inbounds.create') }}" class="button button-primary">+ Barang Masuk</a>
    </header>

    <div class="table-wrap">
        <table class="barang-table">
            <thead>
                <tr>
                    <th>No. Transaksi</th>
                    <th>Tipe</th>
                    <th>Tanggal</th>
                    <th>Gudang</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transactions as $transaction)
                    <tr>
                        <td>{{ $transaction->trx_no }}</td>
                        <td>{{ $transaction->type }}</td>
                        <td>{{ $transaction->transaction_date?->format('d/m/Y') }}</td>
                        <td>{{ $transaction->warehouse?->name }}</td>
                        <td>{{ $transaction->status }}</td>
                        <td><a href="{{ route('transactions.show', $transaction) }}" class="button button-secondary button-small">Detail</a></td>
                    </tr>
                @empty
                    <tr><td class="empty-state" colspan="6">Belum ada transaksi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="crud-pagination">{{ $transactions->links() }}</div>
</section>
@endsection
