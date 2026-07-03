<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;



Route::livewire('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/dashboard', 'dashboard')->name('dashboard');
});

// Route::middleware(['auth', 'verified'])->group(function () {
//     Route::livewire('/master/poli', 'pages::master.poli.index')
//         ->name('master.poli');
// });


Route::middleware(['auth'])->group(function () {
    Route::livewire('/master/identitas-klinik', 'pages::master.master-klinik.master-identitas.master-identitas')
        ->name('master.identitas-klinik');

    Route::livewire('/master/poli', 'pages::master.master-klinik.master-poli.master-poli')
        ->name('master.poli');

    Route::livewire('/master/dokter', 'pages::master.master-klinik.master-dokter.master-dokter')
        ->name('master.dokter');

    Route::livewire('/master/pasien', 'pages::master.master-klinik.master-pasien.master-pasien')
        ->name('master.pasien');

    Route::livewire('/master/diagnosa', 'pages::master.master-klinik.master-diagnosa.master-diagnosa')
        ->name('master.diagnosa');

    Route::livewire('/master/ref-bpjs', 'pages::master.master-klinik.master-ref-bpjs.master-ref-bpjs')
        ->name('master.ref-bpjs');

    Route::livewire('/master/jadwal-mingguan', 'pages::master.master-klinik.master-jadwal-mingguan.master-jadwal-mingguan')
        ->name('master.jadwal-mingguan');

    Route::livewire('/master/laborat', 'pages::master.master-lab.master-laborat.clab.master-clab')
        ->name('master.laborat');

    Route::livewire('/master/agama', 'pages::master.master-klinik.master-agama.master-agama')
        ->name('master.agama');

    Route::livewire('/master/pendidikan', 'pages::master.master-klinik.master-pendidikan.master-pendidikan')
        ->name('master.pendidikan');

    Route::livewire('/master/pekerjaan', 'pages::master.master-klinik.master-pekerjaan.master-pekerjaan')
        ->name('master.pekerjaan');

    Route::livewire('/master/klaim', 'pages::master.master-klinik.master-klaim.master-klaim')
        ->name('master.klaim');

    Route::livewire('/master/cara-masuk', 'pages::master.master-klinik.master-cara-masuk.master-cara-masuk')
        ->name('master.cara-masuk');

    Route::livewire('/master/cara-bayar', 'pages::master.master-klinik.master-cara-bayar.master-cara-bayar')
        ->name('master.cara-bayar');

    Route::livewire('/master/group-akun', 'pages::master.master-akuntansi.master-group-akun.master-group-akun')
        ->name('master.group-akun');

    Route::livewire('/master/akun', 'pages::master.master-akuntansi.master-akun.master-akun')
        ->name('master.akun');

    Route::livewire('/master/tucico', 'pages::master.master-akuntansi.master-tucico.master-tucico')
        ->name('master.tucico');

    Route::livewire('/master/konf-akun-trans', 'pages::master.master-akuntansi.master-konf-akun-trans.master-konf-akun-trans')
        ->name('master.konf-akun-trans');

    Route::livewire('/master/cara-keluar', 'pages::master.master-klinik.master-cara-keluar.master-cara-keluar')
        ->name('master.cara-keluar');

    Route::livewire('/master/procedure', 'pages::master.master-klinik.master-procedure.master-procedure')
        ->name('master.procedure');

    Route::livewire('/master/parameter', 'pages::master.master-klinik.master-parameter.master-parameter')
        ->name('master.parameter');

    Route::livewire('/master/kemasan', 'pages::master.master-apotek.master-kemasan.master-kemasan')
        ->name('master.kemasan');

    // ===========================================
    // MASTER WILAYAH RS (RSMST_*)
    // ===========================================
    Route::livewire('/master/provinsi', 'pages::master.master-wilayah.master-provinsi.master-provinsi')
        ->name('master.provinsi');

    Route::livewire('/master/kabupaten', 'pages::master.master-wilayah.master-kabupaten.master-kabupaten')
        ->name('master.kabupaten');

    Route::livewire('/master/kecamatan', 'pages::master.master-wilayah.master-kecamatan.master-kecamatan')
        ->name('master.kecamatan');

    Route::livewire('/master/desa', 'pages::master.master-wilayah.master-desa.master-desa')
        ->name('master.desa');

    Route::livewire('/master/jasa-dokter', 'pages::master.master-tarif.master-jasa-dokter.master-jasa-dokter')
        ->name('master.jasa-dokter');

    Route::livewire('/master/jasa-karyawan', 'pages::master.master-tarif.master-jasa-karyawan.master-jasa-karyawan')
        ->name('master.jasa-karyawan');

    Route::livewire('/master/jasa-paramedis', 'pages::master.master-tarif.master-jasa-paramedis.master-jasa-paramedis')
        ->name('master.jasa-paramedis');

    // ===========================================
    // MASTER TOKO / APOTEK (TKMST_*)
    // ===========================================
    Route::livewire('/master/kategori', 'pages::master.master-apotek.master-kategori.master-kategori')
        ->name('master.kategori');

    Route::livewire('/master/uom', 'pages::master.master-apotek.master-uom.master-uom')
        ->name('master.uom');

    Route::livewire('/master/kasir', 'pages::master.master-apotek.master-kasir.master-kasir')
        ->name('master.kasir');

    Route::livewire('/master/prov-toko', 'pages::master.master-apotek.master-prov-toko.master-prov-toko')
        ->name('master.prov-toko');

    Route::livewire('/master/kota-toko', 'pages::master.master-apotek.master-kota-toko.master-kota-toko')
        ->name('master.kota-toko');

    Route::livewire('/master/supplier', 'pages::master.master-apotek.master-supplier.master-supplier')
        ->name('master.supplier');

    Route::livewire('/master/customer', 'pages::master.master-apotek.master-customer.master-customer')
        ->name('master.customer');

    Route::livewire('/master/product', 'pages::master.master-apotek.master-product.master-product')
        ->name('master.product');

    Route::livewire('/master/product-non', 'pages::master.master-apotek.master-product-non.master-product-non')
        ->name('master.product-non');

    Route::livewire('/master/signa-catatan', 'pages::master.master-apotek.master-signa-catatan.master-signa-catatan')
        ->name('master.signa-catatan');

    Route::livewire('/master/medik', 'pages::master.master-klinik.master-medik.master-medik')
        ->name('master.medik');

    Route::livewire('/master/others', 'pages::master.master-klinik.master-others.master-others')
        ->name('master.others');

    Route::livewire('/master/radiologis', 'pages::master.master-klinik.master-radiologis.master-radiologis')
        ->name('master.radiologis');

    // ===========================================
    // RAWAT JALAN (RJ) - DAFTAR RAWAT JALAN
    // ===========================================
    Route::livewire('/rawat-jalan/daftar', 'pages::transaksi.rj.daftar-rj.daftar-rj')
        ->name('rawat-jalan.daftar');

    // ===========================================
    // RAWAT JALAN (RJ) - PELAYANAN (EMR dokter/perawat)
    // ===========================================
    Route::livewire('/rawat-jalan/pelayanan', 'pages::transaksi.rj.pelayanan-rj.pelayanan-rj')
        ->name('rawat-jalan.pelayanan');

    // ===========================================
    // RAWAT JALAN (RJ) - BOOKING (Mobile JKN)
    // ===========================================
    Route::livewire('/rawat-jalan/booking', 'pages::transaksi.rj.booking-rj.booking-rj')
        ->name('rawat-jalan.booking');

    // ===========================================
    // TRANSAKSI RJ - ANTRIAN APOTEK
    // ===========================================
    Route::livewire('/transaksi/rj/antrian-apotek-rj', 'pages::transaksi.rj.antrian-apotek-rj.antrian-apotek-rj')
        ->name('transaksi.rj.antrian-apotek-rj');

    // ===========================================
    // TRANSAKSI RJ - ANTRIAN KASIR
    // ===========================================
    Route::livewire('/transaksi/rj/antrian-kasir-rj', 'pages::transaksi.rj.antrian-kasir-rj.antrian-kasir-rj')
        ->name('transaksi.rj.antrian-kasir-rj');


    // ===========================================
    // TRANSAKSI APOTEK (RJ only — klinik pratama tidak ada UGD)
    // ===========================================
    Route::livewire('/transaksi/apotek', 'pages::transaksi.apotek.apotek')
        ->name('transaksi.apotek');


    // ===========================================
    // KEUANGAN - PENERIMAAN KAS TU
    // ===========================================
    Route::livewire('/keuangan/penerimaan-kas-tu', 'pages::transaksi.keuangan.penerimaan-kas-tu.penerimaan-kas-tu')
        ->name('keuangan.penerimaan-kas-tu');

    // ===========================================
    // KEUANGAN - PENGELUARAN KAS TU
    // ===========================================
    Route::livewire('/keuangan/pengeluaran-kas-tu', 'pages::transaksi.keuangan.pengeluaran-kas-tu.pengeluaran-kas-tu')
        ->name('keuangan.pengeluaran-kas-tu');

    // ===========================================
    // KEUANGAN - PEMBAYARAN PIUTANG RJ
    // ===========================================
    Route::livewire('/keuangan/pembayaran-piutang-rj', 'pages::transaksi.keuangan.pembayaran-piutang-rj.pembayaran-piutang-rj')
        ->name('keuangan.pembayaran-piutang-rj');

    // ===========================================
    // KEUANGAN - PEMBAYARAN HUTANG PBF
    // ===========================================
    Route::livewire('/keuangan/pembayaran-hutang-pbf', 'pages::transaksi.keuangan.pembayaran-hutang-pbf.pembayaran-hutang-pbf')
        ->name('keuangan.pembayaran-hutang-pbf');

    Route::livewire('/keuangan/pembayaran-hutang-non-medis', 'pages::transaksi.keuangan.pembayaran-hutang-non-medis.pembayaran-hutang-non-medis')
        ->name('keuangan.pembayaran-hutang-non-medis');

    Route::livewire('/keuangan/saldo-kas', 'pages::transaksi.keuangan.saldo-kas.saldo-kas')
        ->name('keuangan.saldo-kas');

    Route::livewire('/keuangan/buku-besar', 'pages::transaksi.keuangan.buku-besar.buku-besar')
        ->name('keuangan.buku-besar');

    Route::livewire('/keuangan/laba-rugi', 'pages::transaksi.keuangan.laba-rugi.laba-rugi')
        ->name('keuangan.laba-rugi');

    Route::livewire('/keuangan/neraca', 'pages::transaksi.keuangan.neraca.neraca')
        ->name('keuangan.neraca');

    // ===========================================
    // LAPORAN MANAJEMEN (RJ & Keuangan)
    // ===========================================
    Route::livewire('/manajemen/rj/laporan-kunjungan', 'pages::manajemen.rs.rj.laporan-kunjungan-rj.laporan-kunjungan-rj')
        ->name('manajemen.laporan-kunjungan-rj');

    Route::livewire('/manajemen/keuangan/pendapatan-klinik', 'pages::manajemen.rs.tu.pendapatan-klinik.pendapatan-klinik')
        ->name('manajemen.pendapatan-klinik');

    // ===========================================
    // GUDANG - PENERIMAAN MEDIS
    // ===========================================
    Route::livewire('/gudang/penerimaan-medis', 'pages::transaksi.gudang.penerimaan-medis.penerimaan-medis')
        ->name('gudang.penerimaan-medis');

    // ===========================================
    // GUDANG - PENERIMAAN NON-MEDIS (ATK/RT)
    // ===========================================
    Route::livewire('/gudang/penerimaan-non-medis', 'pages::transaksi.gudang.penerimaan-non-medis.penerimaan-non-medis')
        ->name('gudang.penerimaan-non-medis');

    // ===========================================
    // GUDANG - KARTU STOCK
    // ===========================================
    Route::livewire('/gudang/kartu-stock', 'pages::transaksi.gudang.kartu-stock.kartu-stock')
        ->name('gudang.kartu-stock');

    Route::livewire('/gudang/kartu-stock-non', 'pages::transaksi.gudang.kartu-stock-non.kartu-stock-non')
        ->name('gudang.kartu-stock-non');

    // ===========================================
    // TRANSAKSI PENUNJANG - LABORATORIUM
    // ===========================================
    Route::livewire('/transaksi/penunjang/laborat', 'pages::transaksi.penunjang.laborat.daftar-laborat')
        ->name('transaksi.penunjang.laborat');

    // ===========================================
    // DATABASE MONITOR - MONITORING DASHBOARD
    // ===========================================
    Route::livewire('/database-monitor/monitoring-dashboard', 'pages::database-monitor.monitoring-dashboard.monitoring-dashboard')
        ->name('database-monitor.monitoring-dashboard');

    // ===========================================
    // DATABASE MONITOR - MONITORING MOUNT CONTROL
    // ===========================================
    Route::livewire('/database-monitor/monitoring-mount-control', 'pages::database-monitor.monitoring-mount-control.monitoring-mount-control')
        ->name('database-monitor.monitoring-mount-control');

    // ===========================================
    // DATABASE MONITOR - USER CONTROL
    // ===========================================
    Route::livewire('/database-monitor/user-control', 'pages::database-monitor.user-control.user-control')
        ->name('database-monitor.user-control');

    // ===========================================
    // DATABASE MONITOR - ROLE CONTROL
    // ===========================================
    Route::livewire('/database-monitor/role-control', 'pages::database-monitor.role-control.role-control')
        ->name('database-monitor.role-control');

    Route::livewire('/database-monitor/user-online', 'pages::database-monitor.user-online.user-online')
        ->name('database-monitor.user-online');

    Route::livewire('/database-monitor/log-bpjs', 'pages::database-monitor.log-bpjs.log-bpjs')
        ->name('database-monitor.log-bpjs');

});


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
