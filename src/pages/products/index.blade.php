@extends('layouts.app')

@section('content')
<section class="crud-shell">
    <header class="crud-heading">
        <div class="crud-heading-copy">
            <p class="eyebrow">Data</p>
            <h1 class="crud-title">products</h1>
            <p class="crud-description">Kelola data products.</p>
        </div>
        <a href="{{ route('products.create') }}" class="button button-primary">Tambah Product</a>
    </header>

    @if (session('success'))
        <p class="flash-success" role="status">{{ session('success') }}</p>
    @endif

    <div class="crud-summary">
        <span><strong class="crud-summary-count">{{ $products->total() }}</strong> data</span>
        <label class="crud-search-wrap">
            <span class="sr-only">Cari data pada halaman ini</span>
            <input type="search" data-table-search placeholder="Cari…" class="crud-search">
        </label>
    </div>

    <div class="table-wrap">
        <table class="barang-table">
            <caption class="sr-only">Daftar products</caption>
            <thead>
                <tr>
                    <th>Sku</th>
                    <th>Name</th>
                    <th>Category Id</th>
                    <th>Cost Price</th>
                    <th>Selling Price</th>
                    <th>Is Active</th>
                    <th class="actions-heading">Aksi</th>
                </tr>
            </thead>
            <tbody data-table-body>
                @forelse ($products as $product)
                    <tr data-table-row>
                    <td>{{ $product->{'sku'} }}</td>
                    <td>{{ $product->{'name'} }}</td>
                    <td>{{ $product->{'category_id'} }}</td>
                    <td>{{ $product->{'cost_price'} }}</td>
                    <td>{{ $product->{'selling_price'} }}</td>
                    <td>{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('products.edit', $product) }}" class="button button-secondary button-small">Edit</a>
                                <form method="POST" action="{{ route('products.destroy', $product) }}" data-confirm="Yakin ingin menghapus data ini?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button button-secondary button-small">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="empty-state" colspan="7">
                            <div class="crud-empty-state">
                                <h2>Belum ada data</h2>
                                <p>Tambahkan Product pertama.</p>
                                <a href="{{ route('products.create') }}" class="button button-primary">Tambah Product</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                <tr data-table-no-results hidden>
                    <td class="empty-state" colspan="7">Tidak ada data yang cocok.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="crud-pagination">{{ $products->links() }}</div>
</section>
@endsection
