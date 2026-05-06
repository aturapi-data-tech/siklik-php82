<?php

use Livewire\Component;
use Livewire\Attributes\Computed;

new class extends Component {
    public string $search = '';

    // ✅ Semua role user (lowercase) dipakai untuk filtering menu
    #[Computed]
    public function userRoles(): array
    {
        return auth()->user()->getRoleNames()->map(fn($r) => strtolower($r))->values()->toArray();
    }

    #[Computed]
    public function masterMenus(): array
    {
        // Helper: build entry hanya kalau route terdaftar — defensive vs route renames/deletions
        $entry = function (array $m): ?array {
            if (!\Illuminate\Support\Facades\Route::has($m['route'])) return null;
            $m['href'] = route($m['route']);
            return $m;
        };

        $rows = array_filter([
            // ── Master Klinik (data pasien & operasional) ─────────────
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 1,  'route' => 'master.poli',         'title' => 'Master Poli',          'desc' => 'Kelola data poli & ruangan klinik',                'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 2,  'route' => 'master.dokter',       'title' => 'Master Dokter',        'desc' => 'Kelola data dokter & spesialis',                   'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 3,  'route' => 'master.pasien',       'title' => 'Master Pasien',        'desc' => 'Kelola data pasien & rekam medis',                 'roles' => ['admin', 'mr'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 4,  'route' => 'master.diagnosa',     'title' => 'Master Diagnosa',      'desc' => 'Diagnosa ICD-10',                                  'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 5,  'route' => 'master.procedure',    'title' => 'Master Prosedur',      'desc' => 'Prosedur medis ICD-9',                             'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 6,  'route' => 'master.radiologis',   'title' => 'Master Radiologi',     'desc' => 'Kelola data radiologi',                            'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 7,  'route' => 'master.others',       'title' => 'Master Lain-lain',     'desc' => 'Item layanan lain-lain (admin, dll)',              'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 8,  'route' => 'master.agama',        'title' => 'Master Agama',         'desc' => 'Referensi agama pasien',                           'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 9,  'route' => 'master.pendidikan',   'title' => 'Master Pendidikan',    'desc' => 'Referensi pendidikan pasien',                      'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 10, 'route' => 'master.pekerjaan',    'title' => 'Master Pekerjaan',     'desc' => 'Referensi pekerjaan pasien',                       'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 11, 'route' => 'master.klaim',        'title' => 'Master Tipe Klaim',    'desc' => 'BPJS, Umum, Asuransi, dll',                        'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 12, 'route' => 'master.cara-masuk',   'title' => 'Master Cara Masuk',    'desc' => 'Datang sendiri, rujukan, emergency',               'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 12, 'route' => 'master.cara-bayar',   'title' => 'Master Cara Bayar',    'desc' => 'Tunai, Transfer, BPJS, dll (tkacc_carabayars)',     'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 13, 'route' => 'master.cara-keluar',  'title' => 'Master Cara Keluar',   'desc' => 'Sembuh, rujuk, pulang paksa, dll',                 'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 14, 'route' => 'master.parameter',    'title' => 'Master Parameter',     'desc' => 'Parameter sistem & konfigurasi',                   'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 15, 'route' => 'master.medik',           'title' => 'Master Alat Medis',      'desc' => 'Tracking alat medis (kondisi, sertifikat, izin)','roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 16, 'route' => 'master.ref-bpjs',        'title' => 'Master Ref BPJS',        'desc' => 'Cache reference BPJS PCare (alergi, kesadaran, prognosa)', 'roles' => ['admin'], 'badge' => 'BPJS']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 17, 'route' => 'master.jadwal-mingguan', 'title' => 'Jadwal Mingguan BPJS',   'desc' => 'Pull jadwal dokter per poli dari BPJS Antrean RS untuk 7 hari ke depan', 'roles' => ['admin', 'mr'], 'badge' => 'BPJS']),

            // ── Master Akuntansi ──────────────────────────────────────
            $entry(['group' => 'Master Akuntansi', 'groupOrder' => 4, 'order' => 1, 'route' => 'master.group-akun', 'title' => 'Master Group Akun', 'desc' => 'Pengelompokan akun (Aktiva/Pasiva/Modal/Pendapatan/Biaya)',     'roles' => ['admin'], 'badge' => 'Akuntansi']),
            $entry(['group' => 'Master Akuntansi', 'groupOrder' => 4, 'order' => 2, 'route' => 'master.akun',       'title' => 'Master Akun',       'desc' => 'Chart of accounts (tkacc_accountses)',                          'roles' => ['admin'], 'badge' => 'Akuntansi']),
            $entry(['group' => 'Master Akuntansi', 'groupOrder' => 4, 'order' => 3, 'route' => 'master.tucico',     'title' => 'Master TUCICO',     'desc' => 'Pos kas transit non-transaksi (setoran/ambil kas)',             'roles' => ['admin'], 'badge' => 'Akuntansi']),
            $entry(['group' => 'Master Akuntansi', 'groupOrder' => 4, 'order' => 4, 'route' => 'master.konf-akun-trans', 'title' => 'Master Konf. Akun Trx', 'desc' => 'Mapping akun-default per jenis transaksi (tkacc_confacctxns)', 'roles' => ['admin'], 'badge' => 'Akuntansi']),

            // ── Master Tarif Jasa ─────────────────────────────────────
            $entry(['group' => 'Master Tarif', 'groupOrder' => 2, 'order' => 1, 'route' => 'master.jasa-dokter',    'title' => 'Master Jasa Dokter',    'desc' => 'Tarif jasa dokter untuk billing',                'roles' => ['admin'], 'badge' => 'Tarif']),
            $entry(['group' => 'Master Tarif', 'groupOrder' => 2, 'order' => 2, 'route' => 'master.jasa-karyawan',  'title' => 'Master Jasa Karyawan',  'desc' => 'Tarif jasa karyawan (admin/RM/kasir)',           'roles' => ['admin'], 'badge' => 'Tarif']),
            $entry(['group' => 'Master Tarif', 'groupOrder' => 2, 'order' => 3, 'route' => 'master.jasa-paramedis', 'title' => 'Master Jasa Paramedis', 'desc' => 'Tarif jasa paramedis (perawat/bidan/injeksi)',   'roles' => ['admin'], 'badge' => 'Tarif']),

            // ── Master Laboratorium (group sendiri) ───────────────────
            $entry(['group' => 'Master Laboratorium', 'groupOrder' => 3, 'order' => 1, 'route' => 'master.laborat', 'title' => 'Master Laboratorium', 'desc' => 'Kategori lab & item pemeriksaan + nilai normal',  'roles' => ['admin', 'laboratorium'], 'badge' => 'Lab']),

            // ── Master Apotek ─────────────────────────────────────────
            $entry(['group' => 'Master Apotek', 'groupOrder' => 4, 'order' => 1, 'route' => 'master.product',   'title' => 'Master Produk Apotek',    'desc' => 'Inventory obat & alkes',                       'roles' => ['admin', 'apotek'], 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 4, 'order' => 2, 'route' => 'master.kategori',  'title' => 'Master Kategori Produk',  'desc' => 'Kategori obat/alkes/BHP',                      'roles' => ['admin', 'apotek'], 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 4, 'order' => 3, 'route' => 'master.uom',       'title' => 'Master Satuan (UOM)',     'desc' => 'Satuan ukur (PCS, BOX, TAB, dll)',             'roles' => ['admin', 'apotek'], 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 4, 'order' => 4, 'route' => 'master.kemasan',   'title' => 'Master Kemasan',          'desc' => 'Kemasan obat (Tab/Cap/Btl/Box)',               'roles' => ['admin', 'apotek'], 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 4, 'order' => 5, 'route' => 'master.kasir',     'title' => 'Master Kasir',            'desc' => 'Data kasir untuk transaksi penjualan',         'roles' => ['admin', 'apotek'], 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 4, 'order' => 6, 'route' => 'master.supplier',  'title' => 'Master Supplier',         'desc' => 'Supplier obat & alkes (PBF)',                  'roles' => ['admin', 'apotek'], 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 4, 'order' => 7, 'route' => 'master.customer',  'title' => 'Master Customer',         'desc' => 'Customer apotek (luar pasien klinik)',         'roles' => ['admin', 'apotek'], 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 4, 'order' => 8, 'route' => 'master.prov-toko', 'title' => 'Provinsi (Apotek)',       'desc' => 'Provinsi untuk address customer/supplier',     'roles' => ['admin', 'apotek'], 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 4, 'order' => 9, 'route' => 'master.kota-toko', 'title' => 'Kota (Apotek)',           'desc' => 'Kota untuk address customer/supplier',         'roles' => ['admin', 'apotek'], 'badge' => 'Apotek']),

            // ── Master Wilayah (Pasien) ───────────────────────────────
            $entry(['group' => 'Master Wilayah', 'groupOrder' => 5, 'order' => 1, 'route' => 'master.provinsi',  'title' => 'Master Provinsi',  'desc' => 'Provinsi (BPS 2-digit) — wilayah pasien', 'roles' => ['admin'], 'badge' => 'Wilayah']),
            $entry(['group' => 'Master Wilayah', 'groupOrder' => 5, 'order' => 2, 'route' => 'master.kabupaten', 'title' => 'Master Kabupaten', 'desc' => 'Kabupaten/kota (BPS 4-digit)',            'roles' => ['admin'], 'badge' => 'Wilayah']),
            $entry(['group' => 'Master Wilayah', 'groupOrder' => 5, 'order' => 3, 'route' => 'master.kecamatan', 'title' => 'Master Kecamatan', 'desc' => 'Kecamatan (BPS 7-digit)',                 'roles' => ['admin'], 'badge' => 'Wilayah']),
            $entry(['group' => 'Master Wilayah', 'groupOrder' => 5, 'order' => 4, 'route' => 'master.desa',      'title' => 'Master Desa',      'desc' => 'Desa/kelurahan (BPS 10-digit)',           'roles' => ['admin'], 'badge' => 'Wilayah']),

            // ── Rawat Jalan ────────────────────────────────────────────
            $entry(['group' => 'Rawat Jalan', 'groupOrder' => 6, 'order' => 1, 'route' => 'rawat-jalan.daftar',  'title' => 'Daftar Rawat Jalan', 'desc' => 'Pendaftaran & manajemen pasien rawat jalan', 'roles' => ['admin', 'mr', 'perawat', 'dokter'], 'badge' => 'RJ']),

            // ── Apotek ────────────────────────────────────────────────
            $entry(['group' => 'Apotek', 'groupOrder' => 7, 'order' => 1, 'route' => 'transaksi.apotek', 'title' => 'Antrian Apotek', 'desc' => 'Telaah resep & pelayanan kefarmasian', 'roles' => ['admin', 'apotek'], 'badge' => 'APT']),

            // ── Penunjang Lab ─────────────────────────────────────────
            $entry(['group' => 'Penunjang', 'groupOrder' => 8, 'order' => 1, 'route' => 'transaksi.penunjang.laborat', 'title' => 'Transaksi Laboratorium', 'desc' => 'Input hasil pemeriksaan laboratorium pasien', 'roles' => ['admin', 'laboratorium'], 'badge' => 'LAB']),

            // ── Gudang ────────────────────────────────────────────────
            $entry(['group' => 'Gudang', 'groupOrder' => 9, 'order' => 1, 'route' => 'gudang.penerimaan-medis', 'title' => 'Obat dari PBF', 'desc' => 'Penerimaan obat dari PBF / Supplier (Gudang Medis)', 'roles' => ['admin', 'apotek'], 'badge' => 'RCV']),
            $entry(['group' => 'Gudang', 'groupOrder' => 9, 'order' => 2, 'route' => 'gudang.kartu-stock',     'title' => 'Kartu Stock',  'desc' => 'Riwayat mutasi stok per produk per tahun',         'roles' => ['admin', 'apotek'], 'badge' => 'STK']),

            // ── Keuangan ──────────────────────────────────────────────
            $entry(['group' => 'Keuangan', 'groupOrder' => 10, 'order' => 1, 'route' => 'keuangan.penerimaan-kas-tu',     'title' => 'Penerimaan Kas TU',      'desc' => 'Catat penerimaan kas di luar transaksi pelayanan',          'roles' => ['admin', 'tu'], 'badge' => 'CI']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 10, 'order' => 2, 'route' => 'keuangan.pengeluaran-kas-tu',    'title' => 'Pengeluaran Kas TU',     'desc' => 'Catat pengeluaran kas di luar transaksi pelayanan',         'roles' => ['admin', 'tu'], 'badge' => 'CO']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 10, 'order' => 3, 'route' => 'keuangan.pembayaran-piutang-rj', 'title' => 'Pembayaran Piutang RJ', 'desc' => 'Pelunasan tagihan rawat jalan pasien yang masih piutang',   'roles' => ['admin', 'tu', 'kasir'], 'badge' => 'PYR']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 10, 'order' => 4, 'route' => 'keuangan.pembayaran-hutang-pbf', 'title' => 'Pembayaran Hutang PBF', 'desc' => 'Pelunasan / angsuran hutang ke supplier obat (PBF)',         'roles' => ['admin', 'tu'], 'badge' => 'HTG']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 10, 'order' => 5, 'route' => 'keuangan.saldo-kas',             'title' => 'Saldo Kas',              'desc' => 'Posisi saldo kas/bank per tanggal — admin bisa edit saldo awal tahun', 'roles' => ['admin', 'tu', 'kasir'], 'badge' => 'SLD']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 10, 'order' => 6, 'route' => 'keuangan.buku-besar',            'title' => 'Buku Besar',             'desc' => 'Riwayat mutasi per akun per periode dgn saldo berjalan',              'roles' => ['admin', 'tu'],          'badge' => 'BB']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 10, 'order' => 7, 'route' => 'keuangan.laba-rugi',            'title' => 'Laporan Laba Rugi',      'desc' => 'Laba/rugi bulanan & YTD — Penjualan − HPP − Biaya (template L1)',     'roles' => ['admin', 'tu'],          'badge' => 'LR']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 10, 'order' => 8, 'route' => 'keuangan.neraca',               'title' => 'Laporan Neraca',         'desc' => 'Posisi keuangan per tanggal — Aktiva = Hutang + Ekuitas + Laba YTD', 'roles' => ['admin', 'tu'],          'badge' => 'NRC']),

            // ── Sistem ────────────────────────────────────────────────
            $entry(['group' => 'Sistem', 'groupOrder' => 11, 'order' => 1, 'route' => 'database-monitor.monitoring-dashboard',     'title' => 'Oracle Session Monitor', 'desc' => 'Locks, long-running SQL & kill session',          'roles' => ['admin'], 'badge' => 'DB']),
            $entry(['group' => 'Sistem', 'groupOrder' => 11, 'order' => 2, 'route' => 'database-monitor.monitoring-mount-control', 'title' => 'Mounting Control',       'desc' => 'Mount/unmount share folder jaringan (CIFS/SMB)',  'roles' => ['admin'], 'badge' => 'MNT']),
            $entry(['group' => 'Sistem', 'groupOrder' => 11, 'order' => 3, 'route' => 'database-monitor.user-control',             'title' => 'User Control',           'desc' => 'Kelola user & hak akses sistem',                  'roles' => ['admin'], 'badge' => 'USR']),
            $entry(['group' => 'Sistem', 'groupOrder' => 11, 'order' => 4, 'route' => 'database-monitor.role-control',             'title' => 'Role Control',           'desc' => 'Kelola role & permission sistem',                 'roles' => ['admin'], 'badge' => 'ROL']),
        ]);

        return array_values($rows);
    }


    #[Computed]
    public function visibleMenus(): array
    {
        $userRoles = auth()->user()->getRoleNames()->map(fn($r) => strtolower($r))->toArray();

        return collect($this->masterMenus)->filter(fn($m) => !empty(array_intersect($m['roles'], $userRoles)))->values()->toArray();
    }

    #[Computed]
    public function groupedMenus()
    {
        return collect($this->visibleMenus)
            ->sortBy([['groupOrder', 'asc'], ['order', 'asc']])
            ->groupBy('group');
    }
};
?>

<div>
    {{-- HEADER (harus di sini, jangan di dalam div) --}}
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Dashboard
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Pusat menu aplikasi —
                <span class="font-medium">
                    Role Aktif : {{ auth()->user()->getRoleNames()->implode(', ') }}
                </span>
            </p>
        </div>
    </header>

    {{-- BODY WRAPPER: SAMA kayak Master Poli --}}
    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR: mirip sticky toolbar poli (optional) --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                    {{-- SEARCH --}}
                    <div class="w-full lg:max-w-md">
                        <x-input-label value="Cari Menu" class="sr-only" />
                        <x-text-input wire:model.live.debounce.250ms="search" placeholder="Cari menu..."
                            class="block w-full" />
                    </div>

                    {{-- (optional) right side action kalau mau nanti --}}
                    <div class="hidden lg:block"></div>
                </div>
            </div>

            {{-- GRID MENU — Accordion --}}
            <div x-data="{ activeGroup: null }">

                @forelse ($this->groupedMenus as $groupName => $menus)
                    <div x-data="{ group: '{{ $groupName }}' }">

                        {{-- GROUP HEADER --}}
                        <button type="button" @click="activeGroup = (activeGroup === group) ? null : group"
                            class="flex items-center gap-3 w-full mt-6 mb-3 group/header">
                            <h2 class="text-xs font-bold tracking-wider uppercase whitespace-nowrap transition-colors
                    text-gray-400 dark:text-gray-500
                    group-hover/header:text-gray-600 dark:group-hover/header:text-gray-300"
                                :class="activeGroup === group ? 'text-gray-700 dark:text-gray-200' : ''">
                                {{ $groupName }}
                            </h2>
                            <div class="flex-1 h-px bg-gray-200 dark:bg-gray-700"></div>
                            <svg class="w-3.5 h-3.5 text-gray-400 shrink-0 transition-transform duration-200"
                                :class="activeGroup === group ? 'rotate-0' : '-rotate-90'" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        {{-- GRID --}}
                        <div x-show="activeGroup === group" x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-2"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 -translate-y-2"
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">

                            @foreach ($menus as $m)
                                <a href="{{ $m['href'] }}" wire:navigate
                                    class="flex flex-col gap-3 p-4 transition-colors duration-200 bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                                    <div class="grid grid-cols-4 gap-2">
                                        @if (!empty($m['badge']))
                                            <span
                                                class="col-span-1 self-start inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full
                                    bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                                {{ $m['badge'] }}
                                            </span>
                                        @endif
                                        <div class="flex-1 min-w-0 col-span-3">
                                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                                                {{ $m['title'] }}</h3>
                                            <p class="mt-0.5 text-xs text-gray-500 truncate dark:text-gray-400">
                                                {{ $m['desc'] }}</p>
                                        </div>
                                    </div>
                                </a>
                            @endforeach

                        </div>
                    </div>

                @empty
                    <div class="py-10 text-center text-gray-500 dark:text-gray-400">
                        Menu tidak ditemukan / tidak ada akses.
                    </div>
                @endforelse

            </div>

        </div>
    </div>
</div>
