@extends('layouts.app')

@section('content')
<section class="crud-shell">
    <header class="crud-form-heading">
        <a href="{{ route('transactions.index') }}" class="back-link">&larr; Kembali ke transaksi</a>
        <p class="eyebrow">Detail transaksi</p>
        <h1 class="crud-title">{{ $transaction->trx_no }}</h1>
        <p class="crud-description">
            Barang masuk — {{ $transaction->warehouse?->name }} —
            {{ $transaction->transaction_date?->format('d/m/Y') }}
        </p>
    </header>

    @if (session('success'))
        <p class="flash-success" role="status">{{ session('success') }}</p>
    @endif

    @if ($transaction->notes)
        <p>{{ $transaction->notes }}</p>
    @endif

    <div class="table-wrap">
        <table class="barang-table">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Qty</th>
                    <th>Harga Satuan</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transaction->items as $item)
                    <tr>
                        <td>{{ $item->product?->sku }} — {{ $item->product?->name ?? 'Produk dihapus' }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ \App\Support\TransactionMoney::format($item->unit_price) }}</td>
                        <td>{{ \App\Support\TransactionMoney::format($item->subtotal) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <th colspan="3">Total</th>
                    <th>{{ \App\Support\TransactionMoney::format($transaction->total) }}</th>
                </tr>
            </tbody>
        </table>
    </div>
</section>
@endsection
