@extends('layouts.app')

@section('content')
<section class="crud-shell">
    <header class="crud-heading">
        <div class="crud-heading-copy">
            <p class="eyebrow">Data</p>
            <h1 class="crud-title">Kategori</h1>
            <p class="crud-description">Kelola data kategori.</p>
        </div>
        <a href="{{ route('categories.create') }}" class="button button-primary">Tambah Kategori</a>
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
        <span><strong class="crud-summary-count">{{ $categories->total() }}</strong> data</span>
        <label class="crud-search-wrap">
            <span class="sr-only">Cari data pada halaman ini</span>
            <input type="search" data-table-search placeholder="Cari…" class="crud-search">
        </label>
    </div>

    <div class="table-wrap">
        <table class="barang-table">
            <caption class="sr-only">Daftar categories</caption>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th class="actions-heading">Aksi</th>
                </tr>
            </thead>
            <tbody data-table-body>
                @forelse ($categories as $category)
                    <tr data-table-row>
                    <td>{{ $category->name }}</td>
                    <td>{{ $category->slug }}</td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('categories.edit', $category) }}" class="button button-secondary button-small">Edit</a>
                                <form method="POST" action="{{ route('categories.destroy', $category) }}" data-confirm="Yakin ingin menghapus data ini?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button button-secondary button-small">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="empty-state" colspan="3">
                            <div class="crud-empty-state">
                                <h2>Belum ada data</h2>
                                <p>Tambahkan kategori pertama.</p>
                                <a href="{{ route('categories.create') }}" class="button button-primary">Tambah Kategori</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                <tr data-table-no-results hidden>
                    <td class="empty-state" colspan="3">Tidak ada data yang cocok.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="crud-pagination">{{ $categories->links() }}</div>
</section>
@endsection
