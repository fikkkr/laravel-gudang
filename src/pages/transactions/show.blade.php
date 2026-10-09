@extends('layouts.app')

@section('content')
<section class="crud-shell">
    <header class="crud-form-heading">
        <a href="{{ route('transactions.index') }}" class="back-link">&larr; Kembali ke transaksi</a>
        <p class="eyebrow">Detail transaksi</p>
        <h1 class="crud-title">{{ $transaction->trx_no }}</h1>
        <p class="crud-description">
            {{ $transaction->type }} — {{ $transaction->transaction_date?->format('d/m/Y') }}
            — {{ $transaction->warehouse?->name ?? 'Gudang tidak tersedia' }}
            @if ($transaction->destinationWarehouse)
                &rarr; {{ $transaction->destinationWarehouse->name }}
            @endif
            @if ($transaction->customer)
                — {{ $transaction->customer->name }}
            @endif
        </p>
    </header>

    @if (session('success'))
        <p class="flash-success" role="status">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <div class="form-error-summary" role="alert">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <p>Status: {{ $transaction->status }}</p>

    @if ($transaction->notes)
        <p class="crud-description">{{ $transaction->notes }}</p>
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
                @forelse ($transaction->items as $item)
                    <tr>
                        <td>{{ $item->product?->sku }} — {{ $item->product?->name ?? 'Produk dihapus' }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ \App\Support\TransactionMoney::format($item->unit_price) }}</td>
                        <td>{{ \App\Support\TransactionMoney::format($item->subtotal) }}</td>
                    </tr>
                @empty
                    <tr><td class="empty-state" colspan="4">Tidak ada item pada transaksi ini.</td></tr>
                @endforelse
                <tr>
                    <th colspan="3">Total</th>
                    <th>{{ \App\Support\TransactionMoney::format($transaction->total) }}</th>
                </tr>
            </tbody>
        </table>
    </div>

    @if ($transaction->status === 'active')
        <form method="POST" action="{{ route('transactions.cancel', $transaction) }}" class="barang-form">
            @csrf
            <label class="crud-field">
                Alasan pembatalan
                <input type="text" name="cancel_reason" placeholder="Alasan pembatalan" class="crud-input" value="{{ old('cancel_reason') }}">
            </label>
            <div class="crud-form-actions">
                <button
                    type="submit"
                    class="button button-danger"
                    onclick="return confirm('Batalkan transaksi ini? Stok akan direstore.')"
                >
                    Batalkan Transaksi
                </button>
            </div>
        </form>
    @endif
</section>
@endsection
