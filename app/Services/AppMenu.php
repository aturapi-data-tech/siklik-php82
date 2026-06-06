<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * AppMenu — sumber tunggal definisi menu aplikasi (dashboard + sidebar).
 *
 * - `all()` mengembalikan semua entri (defensive: skip route yang tidak terdaftar)
 * - `forRoles($roles)` filter by role names (lowercase)
 * - `grouped(array $roles)` filter + sort + group by 'group' untuk render UI.
 *
 * Tambah/edit menu di method `definitions()` saja. Dashboard & sidebar otomatis ikut.
 */
class AppMenu
{
    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        $entry = function (array $m): ?array {
            if (!Route::has($m['route'])) {
                return null;
            }
            $m['href'] = route($m['route']);
            return $m;
        };

        return array_values(array_filter(self::definitions($entry)));
    }

    /**
     * Filter entries by role names (case-insensitive).
     *
     * @param  array<int, string>  $userRoles
     * @return array<int, array<string, mixed>>
     */
    public static function forRoles(array $userRoles): array
    {
        $roles = array_map('strtolower', $userRoles);
        return array_values(array_filter(self::all(), fn($m) => !empty(array_intersect($m['roles'], $roles))));
    }

    /**
     * Sorted + grouped by 'group'. Cocok untuk render sidebar/dashboard.
     *
     * @param  array<int, string>  $userRoles
     */
    public static function grouped(array $userRoles): Collection
    {
        return collect(self::forRoles($userRoles))
            ->sortBy([['groupOrder', 'asc'], ['order', 'asc']])
            ->groupBy('group');
    }

    /**
     * Definisi semua menu. Helper $entry guard route existence + inject 'href'.
     * Setiap entry: ['group','groupOrder','order','route','title','desc','roles','badge'].
     *
     * @param  callable(array):?array  $entry
     * @return array<int, ?array<string, mixed>>
     */
    private static function definitions(callable $entry): array
    {
        return [
            // ── Master Klinik (data pasien & operasional) ─────────────
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 1,  'route' => 'master.poli',            'title' => 'Master Poli',          'desc' => 'Kelola data poli & ruangan klinik',                'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 2,  'route' => 'master.dokter',          'title' => 'Master Dokter',        'desc' => 'Kelola data dokter & spesialis',                   'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 3,  'route' => 'master.pasien',          'title' => 'Master Pasien',        'desc' => 'Kelola data pasien & rekam medis',                 'roles' => ['admin', 'mr'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 4,  'route' => 'master.diagnosa',        'title' => 'Master Diagnosa',      'desc' => 'Diagnosa ICD-10',                                  'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 5,  'route' => 'master.procedure',       'title' => 'Master Prosedur',      'desc' => 'Prosedur medis ICD-9',                             'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 6,  'route' => 'master.radiologis',      'title' => 'Master Radiologi',     'desc' => 'Kelola data radiologi',                            'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 7,  'route' => 'master.others',          'title' => 'Master Lain-lain',     'desc' => 'Item layanan lain-lain (admin, dll)',              'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 8,  'route' => 'master.agama',           'title' => 'Master Agama',         'desc' => 'Referensi agama pasien',                           'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 9,  'route' => 'master.pendidikan',      'title' => 'Master Pendidikan',    'desc' => 'Referensi pendidikan pasien',                      'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 10, 'route' => 'master.pekerjaan',       'title' => 'Master Pekerjaan',     'desc' => 'Referensi pekerjaan pasien',                       'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 11, 'route' => 'master.klaim',           'title' => 'Master Tipe Klaim',    'desc' => 'BPJS, Umum, Asuransi, dll',                        'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 12, 'route' => 'master.cara-masuk',      'title' => 'Master Cara Masuk',    'desc' => 'Datang sendiri, rujukan, emergency',               'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 13, 'route' => 'master.cara-bayar',      'title' => 'Master Cara Bayar',    'desc' => 'Tunai, Transfer, BPJS, dll (tkacc_carabayars)',    'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 14, 'route' => 'master.cara-keluar',     'title' => 'Master Cara Keluar',   'desc' => 'Sembuh, rujuk, pulang paksa, dll',                 'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 15, 'route' => 'master.parameter',       'title' => 'Master Parameter',     'desc' => 'Parameter sistem & konfigurasi',                   'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 16, 'route' => 'master.medik',           'title' => 'Master Alat Medis',    'desc' => 'Tracking alat medis (kondisi, sertifikat, izin)',  'roles' => ['admin'], 'badge' => 'Klinik']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 17, 'route' => 'master.ref-bpjs',        'title' => 'Master Ref BPJS',      'desc' => 'Cache reference BPJS PCare (alergi, kesadaran, prognosa)', 'roles' => ['admin'], 'badge' => 'BPJS']),
            $entry(['group' => 'Master Klinik', 'groupOrder' => 1, 'order' => 18, 'route' => 'master.jadwal-mingguan', 'title' => 'Jadwal Mingguan BPJS', 'desc' => 'Pull jadwal dokter per poli dari BPJS Antrean untuk 7 hari ke depan', 'roles' => ['admin', 'mr'], 'badge' => 'BPJS']),

            // ── Master Tarif Jasa ─────────────────────────────────────
            $entry(['group' => 'Master Tarif', 'groupOrder' => 2, 'order' => 1, 'route' => 'master.jasa-dokter',    'title' => 'Master Jasa Dokter',    'desc' => 'Tarif jasa dokter untuk billing',              'roles' => ['admin'], 'badge' => 'Tarif']),
            $entry(['group' => 'Master Tarif', 'groupOrder' => 2, 'order' => 2, 'route' => 'master.jasa-karyawan',  'title' => 'Master Jasa Karyawan',  'desc' => 'Tarif jasa karyawan (admin/RM/kasir)',         'roles' => ['admin'], 'badge' => 'Tarif']),
            $entry(['group' => 'Master Tarif', 'groupOrder' => 2, 'order' => 3, 'route' => 'master.jasa-paramedis', 'title' => 'Master Jasa Paramedis', 'desc' => 'Tarif jasa paramedis (perawat/bidan/injeksi)', 'roles' => ['admin'], 'badge' => 'Tarif']),

            // ── Master Laboratorium ───────────────────────────────────
            $entry(['group' => 'Master Laboratorium', 'groupOrder' => 3, 'order' => 1, 'route' => 'master.laborat', 'title' => 'Master Laboratorium', 'desc' => 'Kategori lab & item pemeriksaan + nilai normal', 'roles' => ['admin', 'laboratorium'], 'badge' => 'Lab']),

            // ── Master Apotek ─────────────────────────────────────────
            $entry(['group' => 'Master Apotek', 'groupOrder' => 4, 'order' => 1,  'route' => 'master.product',       'title' => 'Master Produk Apotek',        'desc' => 'Inventory obat & alkes',                          'roles' => ['admin', 'apotek'], 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 4, 'order' => 2,  'route' => 'master.kategori',      'title' => 'Master Kategori Produk',      'desc' => 'Kategori obat/alkes/BHP',                         'roles' => ['admin', 'apotek'], 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 4, 'order' => 3,  'route' => 'master.uom',           'title' => 'Master Satuan (UOM)',         'desc' => 'Satuan ukur (PCS, BOX, TAB, dll)',                'roles' => ['admin', 'apotek'], 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 4, 'order' => 4,  'route' => 'master.kemasan',       'title' => 'Master Kemasan',              'desc' => 'Kemasan obat (Tab/Cap/Btl/Box)',                  'roles' => ['admin', 'apotek'], 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 4, 'order' => 5,  'route' => 'master.kasir',         'title' => 'Master Kasir',                'desc' => 'Data kasir untuk transaksi penjualan',            'roles' => ['admin', 'apotek'], 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 4, 'order' => 6,  'route' => 'master.supplier',      'title' => 'Master Supplier',             'desc' => 'Supplier obat & alkes (PBF)',                     'roles' => ['admin', 'apotek'], 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 4, 'order' => 7,  'route' => 'master.customer',      'title' => 'Master Customer',             'desc' => 'Customer apotek (luar pasien klinik)',            'roles' => ['admin', 'apotek'], 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 4, 'order' => 8,  'route' => 'master.signa-catatan', 'title' => 'Master Catatan Khusus Signa', 'desc' => 'LOV catatan khusus signa untuk e-resep',          'roles' => ['admin', 'apotek'], 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 4, 'order' => 9,  'route' => 'master.prov-toko',     'title' => 'Provinsi (Apotek)',           'desc' => 'Provinsi untuk address customer/supplier',        'roles' => ['admin', 'apotek'], 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 4, 'order' => 10, 'route' => 'master.kota-toko',     'title' => 'Kota (Apotek)',               'desc' => 'Kota untuk address customer/supplier',            'roles' => ['admin', 'apotek'], 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 4, 'order' => 11, 'route' => 'master.product-non',   'title' => 'Master Produk Non-Medis',     'desc' => 'Barang non-medis (ATK/RT) — stok tunggal',        'roles' => ['admin', 'apotek', 'tu'], 'badge' => 'Apotek']),

            // ── Master Akuntansi ──────────────────────────────────────
            $entry(['group' => 'Master Akuntansi', 'groupOrder' => 5, 'order' => 1, 'route' => 'master.group-akun',      'title' => 'Master Group Akun',     'desc' => 'Pengelompokan akun (Aktiva/Pasiva/Modal/Pendapatan/Biaya)',    'roles' => ['admin'], 'badge' => 'Akuntansi']),
            $entry(['group' => 'Master Akuntansi', 'groupOrder' => 5, 'order' => 2, 'route' => 'master.akun',            'title' => 'Master Akun',           'desc' => 'Chart of accounts (tkacc_accountses)',                         'roles' => ['admin'], 'badge' => 'Akuntansi']),
            $entry(['group' => 'Master Akuntansi', 'groupOrder' => 5, 'order' => 3, 'route' => 'master.tucico',          'title' => 'Master TUCICO',         'desc' => 'Pos kas transit non-transaksi (setoran/ambil kas)',            'roles' => ['admin'], 'badge' => 'Akuntansi']),
            $entry(['group' => 'Master Akuntansi', 'groupOrder' => 5, 'order' => 4, 'route' => 'master.konf-akun-trans', 'title' => 'Master Konf. Akun Trx', 'desc' => 'Mapping akun-default per jenis transaksi (tkacc_confacctxns)', 'roles' => ['admin'], 'badge' => 'Akuntansi']),

            // ── Master Wilayah (Pasien) ───────────────────────────────
            $entry(['group' => 'Master Wilayah', 'groupOrder' => 6, 'order' => 1, 'route' => 'master.provinsi',  'title' => 'Master Provinsi',  'desc' => 'Provinsi (BPS 2-digit) — wilayah pasien', 'roles' => ['admin'], 'badge' => 'Wilayah']),
            $entry(['group' => 'Master Wilayah', 'groupOrder' => 6, 'order' => 2, 'route' => 'master.kabupaten', 'title' => 'Master Kabupaten', 'desc' => 'Kabupaten/kota (BPS 4-digit)',            'roles' => ['admin'], 'badge' => 'Wilayah']),
            $entry(['group' => 'Master Wilayah', 'groupOrder' => 6, 'order' => 3, 'route' => 'master.kecamatan', 'title' => 'Master Kecamatan', 'desc' => 'Kecamatan (BPS 7-digit)',                 'roles' => ['admin'], 'badge' => 'Wilayah']),
            $entry(['group' => 'Master Wilayah', 'groupOrder' => 6, 'order' => 4, 'route' => 'master.desa',      'title' => 'Master Desa',      'desc' => 'Desa/kelurahan (BPS 10-digit)',           'roles' => ['admin'], 'badge' => 'Wilayah']),

            // ── Rawat Jalan ────────────────────────────────────────────
            $entry(['group' => 'Rawat Jalan', 'groupOrder' => 7, 'order' => 1, 'route' => 'rawat-jalan.booking',            'title' => 'Booking Rawat Jalan',   'desc' => 'Pasien booking poli via Mobile JKN — siap diaktivasi jadi pendaftaran',  'roles' => ['admin', 'mr', 'perawat'], 'badge' => 'BKG']),
            $entry(['group' => 'Rawat Jalan', 'groupOrder' => 7, 'order' => 2, 'route' => 'rawat-jalan.daftar',             'title' => 'Daftar Rawat Jalan',    'desc' => 'Pendaftaran & manajemen pasien poli rawat jalan harian',                 'roles' => ['admin', 'mr'], 'badge' => 'RJ']),
            $entry(['group' => 'Rawat Jalan', 'groupOrder' => 7, 'order' => 3, 'route' => 'rawat-jalan.pelayanan',          'title' => 'Pelayanan Rawat Jalan', 'desc' => 'EMR poli — anamnesis, pemeriksaan, diagnosa & resep oleh dokter/perawat', 'roles' => ['admin', 'dokter', 'perawat'], 'badge' => 'PEL-RJ']),
            $entry(['group' => 'Rawat Jalan', 'groupOrder' => 7, 'order' => 4, 'route' => 'transaksi.rj.antrian-kasir-rj',  'title' => 'Kasir Rawat Jalan',     'desc' => 'Antrian kasir — administrasi & pembayaran pasien rawat jalan',           'roles' => ['admin', 'tu', 'kasir'], 'badge' => 'KSR-RJ']),
            $entry(['group' => 'Rawat Jalan', 'groupOrder' => 7, 'order' => 5, 'route' => 'transaksi.rj.antrian-apotek-rj', 'title' => 'Antrian Apotek RJ',     'desc' => 'Antrian apotek khusus pasien rawat jalan',                               'roles' => ['admin', 'apotek'], 'badge' => 'APT-RJ']),

            // ── Apotek (transaksi) ────────────────────────────────────
            $entry(['group' => 'Apotek', 'groupOrder' => 8, 'order' => 1, 'route' => 'transaksi.apotek', 'title' => 'Antrian Apotek', 'desc' => 'Telaah resep & pelayanan kefarmasian', 'roles' => ['admin', 'apotek'], 'badge' => 'APT']),

            // ── Penunjang ─────────────────────────────────────────────
            $entry(['group' => 'Penunjang', 'groupOrder' => 9, 'order' => 1, 'route' => 'transaksi.penunjang.laborat', 'title' => 'Transaksi Laboratorium', 'desc' => 'Input hasil pemeriksaan laboratorium pasien', 'roles' => ['admin', 'laboratorium'], 'badge' => 'LAB']),

            // ── Gudang ────────────────────────────────────────────────
            $entry(['group' => 'Gudang', 'groupOrder' => 10, 'order' => 1, 'route' => 'gudang.penerimaan-medis', 'title' => 'Obat dari PBF', 'desc' => 'Penerimaan obat dari PBF / Supplier (Gudang Medis)', 'roles' => ['admin', 'apotek'], 'badge' => 'RCV']),
            $entry(['group' => 'Gudang', 'groupOrder' => 10, 'order' => 2, 'route' => 'gudang.penerimaan-non-medis', 'title' => 'Barang Non-Medis dari Supplier', 'desc' => 'Penerimaan barang non-medis (ATK/RT) — stok tunggal tanpa transfer', 'roles' => ['admin', 'tu'], 'badge' => 'RCV-N']),
            $entry(['group' => 'Gudang', 'groupOrder' => 10, 'order' => 3, 'route' => 'gudang.kartu-stock',     'title' => 'Kartu Stock',   'desc' => 'Riwayat mutasi stok per produk per tahun',           'roles' => ['admin', 'apotek'], 'badge' => 'STK']),
            $entry(['group' => 'Gudang', 'groupOrder' => 10, 'order' => 4, 'route' => 'gudang.kartu-stock-non', 'title' => 'Kartu Stock — Non-Medis', 'desc' => 'Riwayat mutasi stok barang non-medis per produk per tahun', 'roles' => ['admin', 'tu'], 'badge' => 'STK-N']),

            // ── Keuangan ──────────────────────────────────────────────
            $entry(['group' => 'Keuangan', 'groupOrder' => 11, 'order' => 1, 'route' => 'keuangan.penerimaan-kas-tu',     'title' => 'Penerimaan Kas TU',     'desc' => 'Catat penerimaan kas di luar transaksi pelayanan',                     'roles' => ['admin', 'tu'], 'badge' => 'CI']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 11, 'order' => 2, 'route' => 'keuangan.pengeluaran-kas-tu',    'title' => 'Pengeluaran Kas TU',    'desc' => 'Catat pengeluaran kas di luar transaksi pelayanan',                    'roles' => ['admin', 'tu'], 'badge' => 'CO']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 11, 'order' => 3, 'route' => 'keuangan.pembayaran-piutang-rj', 'title' => 'Pembayaran Piutang RJ', 'desc' => 'Pelunasan tagihan rawat jalan pasien yang masih piutang',              'roles' => ['admin', 'tu', 'kasir'], 'badge' => 'PYR']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 11, 'order' => 4, 'route' => 'keuangan.pembayaran-hutang-pbf', 'title' => 'Pembayaran Hutang PBF', 'desc' => 'Pelunasan / angsuran hutang ke supplier obat (PBF)',                   'roles' => ['admin', 'tu'], 'badge' => 'HTG']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 11, 'order' => 5, 'route' => 'keuangan.pembayaran-hutang-non-medis', 'title' => 'Pembayaran Hutang Non-Medis', 'desc' => 'Pelunasan / angsuran hutang ke supplier non-medis',              'roles' => ['admin', 'tu'], 'badge' => 'HTG-N']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 11, 'order' => 6, 'route' => 'keuangan.saldo-kas',             'title' => 'Saldo Kas',             'desc' => 'Posisi saldo kas/bank per tanggal — admin bisa edit saldo awal tahun', 'roles' => ['admin', 'tu', 'kasir'], 'badge' => 'SLD']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 11, 'order' => 7, 'route' => 'keuangan.buku-besar',            'title' => 'Buku Besar',            'desc' => 'Riwayat mutasi per akun per periode dgn saldo berjalan',               'roles' => ['admin', 'tu'], 'badge' => 'BB']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 11, 'order' => 8, 'route' => 'keuangan.laba-rugi',             'title' => 'Laporan Laba Rugi',     'desc' => 'Laba/rugi bulanan & YTD — Penjualan − HPP − Biaya (template L1)',      'roles' => ['admin', 'tu'], 'badge' => 'LR']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 11, 'order' => 9, 'route' => 'keuangan.neraca',                'title' => 'Laporan Neraca',        'desc' => 'Posisi keuangan per tanggal — Aktiva = Hutang + Ekuitas + Laba YTD',   'roles' => ['admin', 'tu'], 'badge' => 'NRC']),

            // ── Sistem ────────────────────────────────────────────────
            $entry(['group' => 'Sistem', 'groupOrder' => 12, 'order' => 1, 'route' => 'database-monitor.monitoring-dashboard',     'title' => 'Oracle Session Monitor', 'desc' => 'Locks, long-running SQL & kill session',         'roles' => ['admin'], 'badge' => 'DB']),
            $entry(['group' => 'Sistem', 'groupOrder' => 12, 'order' => 2, 'route' => 'database-monitor.monitoring-mount-control', 'title' => 'Mounting Control',       'desc' => 'Mount/unmount share folder jaringan (CIFS/SMB)', 'roles' => ['admin'], 'badge' => 'MNT']),
            $entry(['group' => 'Sistem', 'groupOrder' => 12, 'order' => 3, 'route' => 'database-monitor.user-control',             'title' => 'User Control',           'desc' => 'Kelola user & hak akses sistem',                 'roles' => ['admin'], 'badge' => 'USR']),
            $entry(['group' => 'Sistem', 'groupOrder' => 12, 'order' => 4, 'route' => 'database-monitor.role-control',             'title' => 'Role Control',           'desc' => 'Kelola role & permission sistem',                'roles' => ['admin'], 'badge' => 'ROL']),
        ];
    }
}
