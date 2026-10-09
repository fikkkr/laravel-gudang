@extends('layouts.app')

@section('content')
<section class="crud-shell narrow-shell">
    <header class="crud-form-heading">
        <a href="{{ route('products.index') }}" class="back-link">&larr; Kembali ke daftar</a>
        <p class="eyebrow">Data baru</p>
        <h1 class="crud-title">Tambah Product</h1>
        <p class="crud-description">Isi informasi Product.</p>
    </header>

    <form method="POST" action="{{ route('products.store') }}" class="barang-form">
        @csrf
        @if ($errors->any())
            <div class="form-error-summary" role="alert">
                <strong>Periksa kembali isian berikut:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="barang-form-grid">
    <label class="crud-field">
        Sku
        <input type="text" name="sku" value="{{ old('sku') }}" required class="crud-input">
        @error('sku')<span class="form-error">{{ $message }}</span>@enderror
    </label>
    <label class="crud-field">
        Name
        <input type="text" name="name" value="{{ old('name') }}" required class="crud-input">
        @error('name')<span class="form-error">{{ $message }}</span>@enderror
    </label>
    <label class="crud-field">
        Category Id
        <select name="category_id" required class="crud-input">
            <option value="">Pilih Category Id</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        @error('category_id')<span class="form-error">{{ $message }}</span>@enderror
    </label>
    <label class="crud-field">
        Cost Price
        <input type="number" name="cost_price" value="{{ old('cost_price') }}" min="0" step="0.01" class="crud-input">
        @error('cost_price')<span class="form-error">{{ $message }}</span>@enderror
    </label>
    <label class="crud-field">
        Selling Price
        <input type="number" name="selling_price" value="{{ old('selling_price') }}" min="0" step="0.01" class="crud-input">
        @error('selling_price')<span class="form-error">{{ $message }}</span>@enderror
    </label>
    <label class="crud-field">
        Stok Minimum
        <input type="number" name="min_stock" value="{{ old('min_stock', 1) }}" min="1" max="100000" step="1" required class="crud-input">
        @error('min_stock')<span class="form-error">{{ $message }}</span>@enderror
    </label>
    <label class="crud-field">
        Aktif
        <input type="checkbox" name="is_active" value="1" class="crud-checkbox" @checked(old('is_active', '1'))>
        @error('is_active')<span class="form-error">{{ $message }}</span>@enderror
    </label>
        </div>
        <div class="crud-form-actions">
            <a href="{{ route('products.index') }}" class="button button-secondary">Batal</a>
            <button type="submit" class="button button-primary">Simpan</button>
        </div>
    </form>
</section>
@endsection
