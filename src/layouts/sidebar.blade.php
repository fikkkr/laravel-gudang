@php
    $navigationGroups = [
        [
            'label' => null,
            'items' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'dashboard', 'active' => ['dashboard']],
            ],
        ],
        [
            'label' => 'Master Data',
            'adminOnly' => true,
            'items' => [
                ['label' => 'Kategori', 'route' => 'categories.index', 'icon' => 'tag', 'active' => ['categories.*']],
                ['label' => 'Barang', 'route' => 'products.index', 'icon' => 'box', 'active' => ['products.*']],
                ['label' => 'Gudang', 'route' => 'warehouses.index', 'icon' => 'warehouse', 'active' => ['warehouses.*']],
                ['label' => 'Pelanggan', 'route' => 'customers.index', 'icon' => 'users', 'active' => ['customers.*']],
            ],
        ],
        [
            'label' => 'Transaksi',
            'items' => [
                ['label' => 'Barang Masuk', 'route' => 'inbounds.create', 'icon' => 'inbound', 'active' => ['inbounds.*']],
                ['label' => 'Penjualan', 'route' => 'sales.create', 'icon' => 'sales', 'active' => ['sales.*']],
                ['label' => 'Transfer Antar-Gudang', 'route' => 'transfers.create', 'icon' => 'transfer', 'active' => ['transfers.*']],
                ['label' => 'Riwayat Transaksi', 'route' => 'transactions.index', 'icon' => 'history', 'active' => ['transactions.*']],
            ],
        ],
        [
            'label' => 'Laporan',
            'adminOnly' => true,
            'items' => [
                ['label' => 'Ringkasan Laporan', 'route' => 'reports.index', 'icon' => 'reports', 'active' => ['reports.index']],
                ['label' => 'Laporan Stok', 'route' => 'reports.stock', 'icon' => 'stock', 'active' => ['reports.stock', 'reports.stocks', 'reports.stocks.export']],
                ['label' => 'Laporan Barang Masuk', 'route' => 'reports.inbound', 'icon' => 'inbound', 'active' => ['reports.inbound', 'reports.inbounds', 'reports.inbounds.export']],
                ['label' => 'Laporan Penjualan', 'route' => 'reports.sales', 'icon' => 'sales', 'active' => ['reports.sales', 'reports.sales.export']],
            ],
        ],
    ];
@endphp

<svg class="sidebar-icon-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <symbol id="sidebar-icon-dashboard" viewBox="0 0 24 24">
        <rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/>
        <rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>
    </symbol>
    <symbol id="sidebar-icon-tag" viewBox="0 0 24 24">
        <path d="M20 13.5 13.5 20a2.1 2.1 0 0 1-3 0L4 13.5V4h9.5l6.5 6.5a2.1 2.1 0 0 1 0 3Z"/>
        <circle cx="8.5" cy="8.5" r="1"/>
    </symbol>
    <symbol id="sidebar-icon-box" viewBox="0 0 24 24">
        <path d="m12 3 8.5 4.5v9L12 21l-8.5-4.5v-9L12 3Z"/><path d="m3.8 7.7 8.2 4.4 8.2-4.4M12 12.2V21"/>
    </symbol>
    <symbol id="sidebar-icon-warehouse" viewBox="0 0 24 24">
        <path d="m3 10 9-7 9 7v10.5H3V10Z"/><path d="M8 20.5v-7h8v7M8 9.5h.01M12 9.5h.01M16 9.5h.01"/>
    </symbol>
    <symbol id="sidebar-icon-users" viewBox="0 0 24 24">
        <circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 5a3.5 3.5 0 0 1 0 6.8M18 14a5.5 5.5 0 0 1 3.5 5.2"/>
    </symbol>
    <symbol id="sidebar-icon-inbound" viewBox="0 0 24 24">
        <path d="M12 3v12m-5-5 5 5 5-5"/><path d="M4 15.5v4A1.5 1.5 0 0 0 5.5 21h13a1.5 1.5 0 0 0 1.5-1.5v-4"/>
    </symbol>
    <symbol id="sidebar-icon-sales" viewBox="0 0 24 24">
        <path d="M4 4h2l2.2 11.2A2 2 0 0 0 10.2 17h7.6a2 2 0 0 0 2-1.6L21 8H7"/>
        <circle cx="10.5" cy="20" r="1"/><circle cx="18" cy="20" r="1"/>
    </symbol>
    <symbol id="sidebar-icon-transfer" viewBox="0 0 24 24">
        <path d="M4 7h15l-3-3m4 13H5l3 3"/><path d="M19 7v4M5 17v-4"/>
    </symbol>
    <symbol id="sidebar-icon-history" viewBox="0 0 24 24">
        <path d="M3.5 12a8.5 8.5 0 1 0 2.7-6.2L3.5 8.5"/><path d="M3.5 4.5v4h4M12 7v5l3.5 2"/>
    </symbol>
    <symbol id="sidebar-icon-reports" viewBox="0 0 24 24">
        <path d="M5 3.5h9l5 5v12H5V3.5Z"/><path d="M14 3.5v5h5M8 16l2.5-2.5 2 1.5 3.5-4"/>
    </symbol>
    <symbol id="sidebar-icon-stock" viewBox="0 0 24 24">
        <path d="M4 20V11m5 9V5m5 15v-7m5 7V8"/><path d="M2.5 20.5h19"/>
    </symbol>
    <symbol id="sidebar-icon-collapse" viewBox="0 0 24 24">
        <path d="m14.5 5-7 7 7 7"/>
    </symbol>
</svg>

<button class="sidebar-overlay" type="button" aria-label="Tutup menu navigasi" data-sidebar-overlay hidden></button>

<aside id="dashboard-sidebar" class="dashboard-sidebar" aria-label="Navigasi aplikasi" data-sidebar>
    <div class="sidebar-brand">
        <a class="sidebar-brand-link" href="{{ route('dashboard') }}" aria-label="PT Sinar Nusantara - Dashboard">
            <span class="sidebar-brand-mark" aria-hidden="true">SN</span>
            <span class="sidebar-brand-copy">
                <span class="sidebar-brand-name">PT Sinar Nusantara</span>
                <span class="sidebar-brand-caption">Inventory Management</span>
            </span>
        </a>
        <button class="sidebar-collapse-toggle" type="button" aria-label="Ciutkan sidebar" aria-controls="dashboard-sidebar" aria-expanded="true" title="Ciutkan sidebar" data-sidebar-collapse>
            <svg class="sidebar-collapse-icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#sidebar-icon-collapse"/></svg>
        </button>
    </div>

    <nav class="sidebar-navigation" aria-label="Menu utama">
        @foreach ($navigationGroups as $group)
            @if (! ($group['adminOnly'] ?? false) || auth()->user()?->role === 'admin')
                <section class="sidebar-group" aria-label="{{ $group['label'] ?? 'Dashboard' }}">
                    @if ($group['label'])
                        <h2 class="sidebar-group-label">{{ $group['label'] }}</h2>
                    @endif
                    <div class="sidebar-group-links">
                        @foreach ($group['items'] as $item)
                            @php($isActive = request()->routeIs(...$item['active']))
                            <a
                                class="sidebar-link{{ $isActive ? ' is-active' : '' }}"
                                href="{{ route($item['route']) }}"
                                aria-label="{{ $item['label'] }}"
                                title="{{ $item['label'] }}"
                                @if ($isActive) aria-current="page" @endif
                                data-sidebar-link
                            >
                                <svg class="sidebar-link-icon" viewBox="0 0 24 24" aria-hidden="true">
                                    <use href="#sidebar-icon-{{ $item['icon'] }}"/>
                                </svg>
                                <span class="sidebar-link-label">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </nav>
</aside>
