@extends('layouts.app')

@section('content')
<section class="crud-shell">
    <header class="crud-heading">
        <div class="crud-heading-copy">
            <p class="eyebrow">Data</p>
            <h1 class="crud-title">warehouses</h1>
            <p class="crud-description">Kelola data warehouses.</p>
        </div>
        <a href="{{ route('warehouses.create') }}" class="button button-primary">Tambah Warehouse</a>
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

    <div class="crud-summary">
        <span><strong class="crud-summary-count">{{ $warehouses->total() }}</strong> data</span>
        <label class="crud-search-wrap">
            <span class="sr-only">Cari data pada halaman ini</span>
            <input type="search" data-table-search placeholder="Cari…" class="crud-search">
        </label>
    </div>

    <div class="table-wrap">
        <table class="barang-table">
            <caption class="sr-only">Daftar warehouses</caption>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Address</th>
                    <th>Is Active</th>
                    <th class="actions-heading">Aksi</th>
                </tr>
            </thead>
            <tbody data-table-body>
                @forelse ($warehouses as $warehouse)
                    <tr data-table-row>
                    <td>{{ $warehouse->{'code'} }}</td>
                    <td>{{ $warehouse->{'name'} }}</td>
                    <td>{{ $warehouse->{'address'} }}</td>
                    <td>{{ $warehouse->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('warehouses.edit', $warehouse) }}" class="button button-secondary button-small">Edit</a>
                                <form method="POST" action="{{ route('warehouses.destroy', $warehouse) }}" data-confirm="Yakin ingin menghapus data ini?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button button-secondary button-small">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="empty-state" colspan="5">
                            <div class="crud-empty-state">
                                <h2>Belum ada data</h2>
                                <p>Tambahkan Warehouse pertama.</p>
                                <a href="{{ route('warehouses.create') }}" class="button button-primary">Tambah Warehouse</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                <tr data-table-no-results hidden>
                    <td class="empty-state" colspan="5">Tidak ada data yang cocok.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="crud-pagination">{{ $warehouses->links() }}</div>
</section>
@endsection
