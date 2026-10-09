@php
    $pageTitle = match (true) {
        request()->routeIs('dashboard') => 'Dashboard',
        request()->routeIs('categories.*') => 'Kategori',
        request()->routeIs('products.*') => 'Barang',
        request()->routeIs('warehouses.*') => 'Gudang',
        request()->routeIs('customers.*') => 'Pelanggan',
        request()->routeIs('inbounds.*') => 'Barang Masuk',
        request()->routeIs('sales.*') => 'Penjualan',
        request()->routeIs('transfers.*') => 'Transfer Antar-Gudang',
        request()->routeIs('transactions.*') => 'Riwayat Transaksi',
        request()->routeIs('reports.stock*', 'reports.stocks*') => 'Laporan Stok',
        request()->routeIs('reports.inbound*', 'reports.inbounds*') => 'Laporan Barang Masuk',
        request()->routeIs('reports.sales*') => 'Laporan Penjualan',
        request()->routeIs('reports.*') => 'Laporan',
        request()->routeIs('profile.*') => 'Profil',
        default => 'Inventory',
    };
    $currentUser = auth()->user();
@endphp

<header class="dashboard-header">
    <div class="dashboard-header-start">
        <button class="mobile-menu-toggle" type="button" aria-label="Buka menu navigasi" aria-controls="dashboard-sidebar" aria-expanded="false" data-sidebar-open>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        <div class="dashboard-page-heading">
            <p class="dashboard-breadcrumb">Inventory <span aria-hidden="true">/</span></p>
            <h1>{{ $pageTitle }}</h1>
        </div>
    </div>

    <div class="dashboard-user">
        <div class="dashboard-user-details">
            <span class="dashboard-user-name">{{ $currentUser?->name }}</span>
            <span class="dashboard-user-role">{{ ucfirst($currentUser?->role ?? '') }}</span>
        </div>
        <a class="dashboard-profile-link" href="{{ route('profile.edit') }}">Profil</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="dashboard-logout-button">Keluar</button>
        </form>
    </div>
</header>
