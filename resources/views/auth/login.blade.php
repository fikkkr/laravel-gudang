@extends('layouts.app', ['guest' => true])

@section('content')
<section class="crud-shell" style="max-width: 28rem; margin: 4rem auto;">
    <header class="crud-form-heading">
        <p class="eyebrow">PT Sinar Nusantara</p>
        <h1 class="crud-title">Masuk</h1>
        <p class="crud-description">Silakan login dengan akun internal.</p>
    </header>

    <form method="POST" action="{{ route('login') }}" class="barang-form">
        @csrf

        @if ($errors->any())
            <div class="form-error-summary" role="alert">
                <strong>Login gagal.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <label class="crud-field">
            Email
            <input type="email" name="email" value="{{ old('email') }}" required
                   autofocus autocomplete="username" class="crud-input">
            @error('email')<span class="form-error">{{ $message }}</span>@enderror
        </label>

        <label class="crud-field">
            Password
            <input type="password" name="password" required
                   autocomplete="current-password" class="crud-input">
            @error('password')<span class="form-error">{{ $message }}</span>@enderror
        </label>

        <label class="crud-field" style="display:flex;align-items:center;gap:.5rem;">
            <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
            <span>Ingat saya</span>
        </label>

        @if (Route::has('password.request'))
            <a href="{{ route('password.request') }}" style="font-size:.875rem;">
                Lupa password?
            </a>
        @endif

        <div class="crud-form-actions">
            <button type="submit" class="button button-primary">Masuk</button>
        </div>
    </form>
</section>
@endsection