@extends('layouts.app')

@section('content')
<section class="crud-shell narrow-shell">
    <header class="crud-form-heading">
        <a href="{{ route('customers.index') }}" class="back-link">&larr; Kembali ke daftar</a>
        <p class="eyebrow">Data baru</p>
        <h1 class="crud-title">Tambah Customer</h1>
        <p class="crud-description">Isi informasi Customer.</p>
    </header>

    <form method="POST" action="{{ route('customers.store') }}" class="barang-form">
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
        Name
        <input type="text" name="name" value="{{ old('name') }}" required class="crud-input">
        @error('name')<span class="form-error">{{ $message }}</span>@enderror
    </label>
    <label class="crud-field">
        Phone
        <input type="text" name="phone" value="{{ old('phone') }}" class="crud-input">
        @error('phone')<span class="form-error">{{ $message }}</span>@enderror
    </label>
    <label class="crud-field">
        Address
        <textarea name="address" rows="4" class="crud-input">{{ old('address') }}</textarea>
        @error('address')<span class="form-error">{{ $message }}</span>@enderror
    </label>
        </div>
        <div class="crud-form-actions">
            <a href="{{ route('customers.index') }}" class="button button-secondary">Batal</a>
            <button type="submit" class="button button-primary">Simpan</button>
        </div>
    </form>
</section>
@endsection
