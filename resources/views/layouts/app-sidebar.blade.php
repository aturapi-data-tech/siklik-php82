@php
    use Illuminate\Support\Str;

    /**
     * Sidebar menu definition.
     *
     * Struktur per submenu:
     *   ['label' => '...', 'route' => 'route.name', 'roles' => ['admin', 'tu', ...]]
     *
     * - 'roles' kosong/missing  → semua user yg login bisa lihat
     * - Submenu yg route-nya tidak terdaftar otomatis di-skip
     * - Group header otomatis hilang kalau semua submenu ke-filter habis
     */
    $menus = [
        'Master Klinik' => [
            ['label' => 'Master Pasien',          'route' => 'master.pasien',          'roles' => ['admin', 'mr']],
            ['label' => 'Master Dokter',          'route' => 'master.dokter',          'roles' => ['admin']],
            ['label' => 'Master Poli',            'route' => 'master.poli',            'roles' => ['admin']],
            ['label' => 'Master Diagnosa',        'route' => 'master.diagnosa',        'roles' => ['admin']],
            ['label' => 'Master Prosedur',        'route' => 'master.procedure',       'roles' => ['admin']],
            ['label' => 'Master Radiologi',       'route' => 'master.radiologis',      'roles' => ['admin']],
            ['label' => 'Master Lain-lain',       'route' => 'master.others',          'roles' => ['admin']],
            ['label' => 'Master Agama',           'route' => 'master.agama',           'roles' => ['admin']],
            ['label' => 'Master Pendidikan',      'route' => 'master.pendidikan',      'roles' => ['admin']],
            ['label' => 'Master Pekerjaan',       'route' => 'master.pekerjaan',       'roles' => ['admin']],
            ['label' => 'Master Tipe Klaim',      'route' => 'master.klaim',           'roles' => ['admin']],
            ['label' => 'Master Cara Masuk',      'route' => 'master.cara-masuk',      'roles' => ['admin']],
            ['label' => 'Master Cara Bayar',      'route' => 'master.cara-bayar',      'roles' => ['admin']],
            ['label' => 'Master Cara Keluar',     'route' => 'master.cara-keluar',     'roles' => ['admin']],
            ['label' => 'Master Parameter',       'route' => 'master.parameter',       'roles' => ['admin']],
            ['label' => 'Master Alat Medis',      'route' => 'master.medik',           'roles' => ['admin']],
            ['label' => 'Master Ref BPJS',        'route' => 'master.ref-bpjs',        'roles' => ['admin']],
            ['label' => 'Jadwal Mingguan BPJS',   'route' => 'master.jadwal-mingguan', 'roles' => ['admin', 'mr']],
        ],
        'Master Tarif Jasa' => [
            ['label' => 'Master Jasa Dokter',     'route' => 'master.jasa-dokter',     'roles' => ['admin']],
            ['label' => 'Master Jasa Karyawan',   'route' => 'master.jasa-karyawan',   'roles' => ['admin']],
            ['label' => 'Master Jasa Paramedis',  'route' => 'master.jasa-paramedis',  'roles' => ['admin']],
        ],
        'Master Laboratorium' => [
            ['label' => 'Master Lab',             'route' => 'master.laborat',         'roles' => ['admin', 'laboratorium']],
        ],
        'Master Apotek' => [
            ['label' => 'Master Produk Apotek',   'route' => 'master.product',         'roles' => ['admin', 'apotek']],
            ['label' => 'Master Kategori Produk', 'route' => 'master.kategori',        'roles' => ['admin', 'apotek']],
            ['label' => 'Master Satuan (UOM)',    'route' => 'master.uom',             'roles' => ['admin', 'apotek']],
            ['label' => 'Master Kemasan',         'route' => 'master.kemasan',         'roles' => ['admin', 'apotek']],
            ['label' => 'Master Kasir',           'route' => 'master.kasir',           'roles' => ['admin', 'apotek']],
            ['label' => 'Master Supplier',        'route' => 'master.supplier',        'roles' => ['admin', 'apotek']],
            ['label' => 'Master Customer',        'route' => 'master.customer',        'roles' => ['admin', 'apotek']],
            ['label' => 'Master Catatan Signa',   'route' => 'master.signa-catatan',   'roles' => ['admin', 'apotek']],
            ['label' => 'Provinsi (Apotek)',      'route' => 'master.prov-toko',       'roles' => ['admin', 'apotek']],
            ['label' => 'Kota (Apotek)',          'route' => 'master.kota-toko',       'roles' => ['admin', 'apotek']],
        ],
        'Master Akuntansi' => [
            ['label' => 'Master Group Akun',      'route' => 'master.group-akun',      'roles' => ['admin']],
            ['label' => 'Master Akun',            'route' => 'master.akun',            'roles' => ['admin']],
            ['label' => 'Master TUCICO',          'route' => 'master.tucico',          'roles' => ['admin']],
            ['label' => 'Master Konf. Akun Trx',  'route' => 'master.konf-akun-trans', 'roles' => ['admin']],
        ],
        'Master Wilayah Pasien' => [
            ['label' => 'Master Provinsi',  'route' => 'master.provinsi',              'roles' => ['admin']],
            ['label' => 'Master Kabupaten', 'route' => 'master.kabupaten',             'roles' => ['admin']],
            ['label' => 'Master Kecamatan', 'route' => 'master.kecamatan',             'roles' => ['admin']],
            ['label' => 'Master Desa',      'route' => 'master.desa',                  'roles' => ['admin']],
        ],
        'Rawat Jalan' => [
            ['label' => 'Booking RJ',         'route' => 'rawat-jalan.booking',                 'roles' => ['admin', 'mr', 'perawat']],
            ['label' => 'Daftar RJ',          'route' => 'rawat-jalan.daftar',                  'roles' => ['admin', 'mr', 'perawat', 'dokter']],
            ['label' => 'Antrian Apotek',     'route' => 'transaksi.rj.antrian-apotek-rj',      'roles' => ['admin', 'apotek']],
        ],
        'Apotek & Penunjang' => [
            ['label' => 'Antrian Apotek',     'route' => 'transaksi.apotek',                'roles' => ['admin', 'apotek']],
            ['label' => 'Laboratorium',       'route' => 'transaksi.penunjang.laborat',     'roles' => ['admin', 'laboratorium']],
            ['label' => 'Penerimaan Medis',   'route' => 'gudang.penerimaan-medis',         'roles' => ['admin', 'apotek']],
            ['label' => 'Kartu Stock',        'route' => 'gudang.kartu-stock',              'roles' => ['admin', 'apotek']],
        ],
        'Keuangan' => [
            ['label' => 'Penerimaan Kas TU',     'route' => 'keuangan.penerimaan-kas-tu',     'roles' => ['admin', 'tu']],
            ['label' => 'Pengeluaran Kas TU',    'route' => 'keuangan.pengeluaran-kas-tu',    'roles' => ['admin', 'tu']],
            ['label' => 'Pembayaran Piutang RJ', 'route' => 'keuangan.pembayaran-piutang-rj', 'roles' => ['admin', 'tu', 'kasir']],
            ['label' => 'Pembayaran Hutang PBF', 'route' => 'keuangan.pembayaran-hutang-pbf', 'roles' => ['admin', 'tu']],
            ['label' => 'Saldo Kas',             'route' => 'keuangan.saldo-kas',             'roles' => ['admin', 'tu', 'kasir']],
            ['label' => 'Buku Besar',            'route' => 'keuangan.buku-besar',            'roles' => ['admin', 'tu']],
            ['label' => 'Laporan Laba Rugi',     'route' => 'keuangan.laba-rugi',             'roles' => ['admin', 'tu']],
            ['label' => 'Laporan Neraca',        'route' => 'keuangan.neraca',                'roles' => ['admin', 'tu']],
        ],
        'Database Monitor' => [
            ['label' => 'Monitoring Dashboard', 'route' => 'database-monitor.monitoring-dashboard',     'roles' => ['admin']],
            ['label' => 'Mount Control',        'route' => 'database-monitor.monitoring-mount-control', 'roles' => ['admin']],
            ['label' => 'User Control',         'route' => 'database-monitor.user-control',             'roles' => ['admin']],
            ['label' => 'Role Control',         'route' => 'database-monitor.role-control',             'roles' => ['admin']],
        ],
    ];

    // Role user (lowercase) — mengikuti pattern dashboard.
    $userRoles = auth()->check()
        ? auth()->user()->getRoleNames()->map(fn($r) => strtolower($r))->values()->toArray()
        : [];

    // Filter: route harus terdaftar AND (roles kosong OR ada irisan dgn role user)
    $menus = collect($menus)
        ->map(fn ($subs) => array_values(array_filter($subs, function ($s) use ($userRoles) {
            if (! \Illuminate\Support\Facades\Route::has($s['route'])) return false;
            if (empty($s['roles'] ?? [])) return true;
            return !empty(array_intersect($userRoles, $s['roles']));
        })))
        ->filter(fn ($subs) => count($subs) > 0)
        ->all();
@endphp

<aside x-cloak id="app-sidebar"
    class="fixed top-20 left-0 z-[60] h-[calc(100vh-5rem)] w-80 max-w-[85vw]
           bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700
           transform transition-transform duration-300 ease-out"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">

    {{-- header sidebar --}}
    <div class="flex items-center justify-between h-16 px-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center gap-2.5">
            <span class="relative flex h-2.5 w-2.5">
                <span class="absolute inline-flex w-full h-full rounded-full opacity-60 bg-brand-lime animate-ping"></span>
                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-brand-green dark:bg-brand-lime"></span>
            </span>

            <div class="leading-tight">
                @auth
                    <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                        {{ auth()->user()->name }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ auth()->user()->getRoleNames()->first() ?? 'User' }}
                    </div>
                @else
                    <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">Guest</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Silakan login</div>
                @endauth
            </div>
        </div>
    </div>

    {{-- menu --}}
    <nav class="p-4 space-y-3 overflow-y-auto h-[calc(100vh-5rem-4rem)]">
        @foreach ($menus as $label => $subs)
            @php
                $key = Str::slug($label);
                $isGroupActive = collect($subs)->contains(fn ($s) => request()->routeIs($s['route']));
            @endphp

            <div class="overflow-hidden bg-white border border-gray-200 rounded-xl dark:border-gray-700 dark:bg-gray-800"
                x-data="{ openOnLoad: @js($isGroupActive) }"
                x-init="if (openOnLoad) openMenus['{{ $key }}'] = true">

                {{-- Parent --}}
                <button type="button"
                    class="group flex items-center justify-between w-full px-4 py-2.5 rounded-xl
                           transition-colors duration-200
                           hover:bg-brand-green/10
                           dark:hover:bg-brand-lime/15"
                    x-on:click="toggleMenu('{{ $key }}')">

                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center justify-center transition-colors duration-200 rounded-lg w-7 h-7 bg-brand-green/10 text-brand-green group-hover:bg-brand-green group-hover:text-white dark:bg-brand-lime/15 dark:text-brand-lime dark:group-hover:bg-brand-lime dark:group-hover:text-slate-900">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 15L12 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                <path d="M21.6359 12.9579L21.3572 14.8952C20.8697 18.2827 20.626 19.9764 19.451 20.9882C18.2759 22 16.5526 22 13.1061 22H10.8939C7.44737 22 5.72409 22 4.54903 20.9882C3.37396 19.9764 3.13025 18.2827 2.64284 14.8952L2.36407 12.9579C1.98463 10.3208 1.79491 9.00229 2.33537 7.87495C2.87583 6.7476 4.02619 6.06234 6.32691 4.69181L7.71175 3.86687C9.80104 2.62229 10.8457 2 12 2C13.1543 2 14.199 2.62229 16.2882 3.86687L17.6731 4.69181C19.9738 6.06234 21.1242 6.7476 21.6646 7.87495" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </span>

                        <span class="font-medium text-gray-800 transition-colors duration-200 text-md group-hover:text-brand-green dark:text-gray-100 dark:group-hover:text-brand-lime">
                            {{ $label }}
                        </span>
                    </div>

                    <svg class="w-4 h-4 text-gray-400 transition-colors duration-200 group-hover:text-brand-green dark:text-gray-500 dark:group-hover:text-brand-lime"
                        :class="openMenus['{{ $key }}'] ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 10 6">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/>
                    </svg>
                </button>

                {{-- Children --}}
                <div x-cloak x-show="openMenus['{{ $key }}']" x-collapse class="px-4 pb-3">
                    <div class="pt-2 space-y-1">
                        @foreach ($subs as $sub)
                            @php $isActive = request()->routeIs($sub['route']); @endphp
                            <a wire:navigate href="{{ route($sub['route']) }}"
                                @class([
                                    'block px-3 py-1.5 text-md rounded-md transition-colors duration-200',
                                    'bg-brand-green/15 text-brand-green dark:bg-brand-lime/20 dark:text-brand-lime font-semibold' => $isActive,
                                    'text-gray-700 hover:bg-brand-green/10 hover:text-brand-green dark:text-gray-300 dark:hover:bg-brand-lime/15 dark:hover:text-brand-lime' => !$isActive,
                                ])>
                                {{ $sub['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach

        @if (empty($menus))
            <div class="p-4 text-sm text-center text-gray-500 dark:text-gray-400">
                Belum ada menu yang tersedia.
            </div>
        @endif
    </nav>
</aside>
