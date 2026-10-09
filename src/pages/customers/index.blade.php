@extends('layouts.app')

@section('content')
<section class="crud-shell">
    <header class="crud-heading">
        <div class="crud-heading-copy">
            <p class="eyebrow">Data</p>
            <h1 class="crud-title">customers</h1>
            <p class="crud-description">Kelola data customers.</p>
        </div>
        <a href="{{ route('customers.create') }}" class="button button-primary">Tambah Customer</a>
    </header>

    @if (session('success'))
        <p class="flash-success" role="status">{{ session('success') }}</p>
    @endif

    <div class="crud-summary">
        <span><strong class="crud-summary-count">{{ $customers->total() }}</strong> data</span>
        <label class="crud-search-wrap">
            <span class="sr-only">Cari data pada halaman ini</span>
            <input type="search" data-table-search placeholder="Cari…" class="crud-search">
        </label>
    </div>

    <div class="table-wrap">
        <table class="barang-table">
            <caption class="sr-only">Daftar customers</caption>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th class="actions-heading">Aksi</th>
                </tr>
            </thead>
            <tbody data-table-body>
                @forelse ($customers as $customer)
                    <tr data-table-row>
                    <td>{{ $customer->{'name'} }}</td>
                    <td>{{ $customer->{'phone'} }}</td>
                    <td>{{ $customer->{'address'} }}</td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('customers.edit', $customer) }}" class="button button-secondary button-small">Edit</a>
                                <form method="POST" action="{{ route('customers.destroy', $customer) }}" data-confirm="Yakin ingin menghapus data ini?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button button-secondary button-small">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="empty-state" colspan="4">
                            <div class="crud-empty-state">
                                <h2>Belum ada data</h2>
                                <p>Tambahkan Customer pertama.</p>
                                <a href="{{ route('customers.create') }}" class="button button-primary">Tambah Customer</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                <tr data-table-no-results hidden>
                    <td class="empty-state" colspan="4">Tidak ada data yang cocok.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="crud-pagination">{{ $customers->links() }}</div>
</section>
@endsection
