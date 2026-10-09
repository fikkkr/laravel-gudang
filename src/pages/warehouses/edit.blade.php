@extends('layouts.app')

@section('content')
<section class="crud-shell narrow-shell">
    <header class="crud-form-heading">
        <a href="{{ route('warehouses.index') }}" class="back-link">&larr; Kembali ke daftar</a>
        <p class="eyebrow">Perbarui data</p>
        <h1 class="crud-title">Edit Warehouse</h1>
        <p class="crud-description">Ubah informasi Warehouse.</p>
    </header>

    <form method="POST" action="{{ route('warehouses.update', $warehouse->id) }}" class="barang-form">
        @csrf
        @method('PUT')
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
        Code
        <input type="text" name="code" value="{{ old('code', $warehouse->code) }}" required class="crud-input">
        @error('code')<span class="form-error">{{ $message }}</span>@enderror
    </label>
    <label class="crud-field">
        Name
        <input type="text" name="name" value="{{ old('name', $warehouse->name) }}" required class="crud-input">
        @error('name')<span class="form-error">{{ $message }}</span>@enderror
    </label>
    <label class="crud-field">
        Address
        <input type="text" name="address" value="{{ old('address', $warehouse->address) }}" class="crud-input">
        @error('address')<span class="form-error">{{ $message }}</span>@enderror
    </label>
    <label class="crud-field">
        Aktif
        <input type="checkbox" name="is_active" value="1" class="crud-checkbox" @checked(old('is_active', $warehouse->is_active))>
        @error('is_active')<span class="form-error">{{ $message }}</span>@enderror
    </label>
        </div>
        <div class="crud-form-actions">
            <a href="{{ route('warehouses.index') }}" class="button button-secondary">Batal</a>
            <button type="submit" class="button button-primary">Simpan perubahan</button>
        </div>
    </form>
</section>
@endsection
