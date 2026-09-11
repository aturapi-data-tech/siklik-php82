<?php

namespace App\Support;

/**
 * SUMBER TUNGGAL daftar role untuk aksi yang dibatasi — diklaster per maksud.
 *
 * Menggantikan literal berulang di blade/komponen:
 *   SEBELUM  @hasanyrole('Admin|Mr')                (tersebar, mudah melenceng)
 *   SESUDAH  @can('emr.logAktivitas')               (1 sumber)
 *
 * Konstanta dikonsumsi lewat Gate yang didaftarkan di
 * App\Providers\AppServiceProvider::boot(). Pemakaian:
 *   - Blade  : @can('dokumen.hapus') ... @endcan
 *   - Server : auth()->user()?->can('dokumen.hapus')  ← WAJIB juga, karena wire:click
 *              memanggil method publik dan guard blade saja bisa ditembus.
 *
 * ATURAN — SATU KONSTANTA PER MAKSUD, BUKAN PER DAFTAR ROLE. Dua aksi yang kebetulan
 * daftar role-nya sama tetap dipisah, supaya kelak bisa diberi role berbeda tanpa
 * menyentuh pemanggil.
 *
 * Role nyata di Oracle siklik (tabel ROLES): Admin, Apoteker, Dokter, Mr, Perawat.
 * Beberapa literal lama di kode menyebut 'Tu', 'Kasir', 'Apotek', 'Laboratorium' yang
 * TIDAK ada di tabel — dibiarkan apa adanya sampai ada keputusan kebijakan, sengaja
 * tidak diklaster di sini.
 *
 * Pola ditiru dari sirus-php82 (App\Support\AksiRole); klinik tidak punya role
 * "Manager", jadi padanan manajemen dokumen = Mr (rekam medis).
 */
class AksiRole
{
    /* ─────────────── MODUL DOKUMEN (formulir bertanda tangan) ─────────────── */

    /** Boleh MENGHAPUS entri dokumen (draft maupun terkunci). */
    public const DOKUMEN_HAPUS = ['Admin', 'Mr'];

    /** Boleh MEMBUKA KUNCI (mencabut TTD petugas) entri dokumen yang sudah final. */
    public const DOKUMEN_BUKA_KUNCI = ['Admin', 'Mr', 'Perawat'];

    /** Boleh membuka Modul Dokumen dari titik-3 Pelayanan RJ & footer EMR. */
    public const DOKUMEN_BUKA = ['Admin', 'Perawat', 'Dokter', 'Mr'];

    /* ─────────────────────────────── EMR RJ ─────────────────────────────── */

    /** Melihat Log Aktivitas EMR/Administrasi (jejak audit). */
    public const EMR_LOG_AKTIVITAS = ['Admin', 'Mr'];

    /** Membuka layar EMR pasien. */
    public const EMR_BUKA = ['Perawat', 'Dokter', 'Admin', 'Mr'];

    /** Membuka Administrasi pasien (kasir/biaya) dari EMR & titik-3. */
    public const ADMINISTRASI_BUKA = ['Admin', 'Perawat', 'Tu'];

    /** Cetak e-resep dari footer EMR. */
    public const EMR_CETAK_ERESEP = ['Perawat', 'Dokter', 'Admin', 'Mr'];

    /** Tombol i-Care BPJS di header EMR. */
    public const EMR_ICARE = ['Dokter', 'Admin'];

    /* ──────────────── RUJUKAN BERBASIS KOMPETENSI (FKTP) ──────────────── */

    /**
     * Menerbitkan rujukan ke BPJS/SATUSEHAT dari panel Rujukan Kompetensi.
     * Keputusan merujuk adalah keputusan klinis, jadi dibatasi dokter (+Admin
     * untuk menambal kasus operasional).
     */
    public const RUJUKAN_KIRIM = ['Dokter', 'Admin'];

    /**
     * Membatalkan rujukan yang sudah terbit. Dipisah dari RUJUKAN_KIRIM karena
     * akibatnya jauh lebih besar: pembatalan FKTP ikut MENGHAPUS pendaftaran
     * PCare pasien, bukan cuma rujukannya.
     */
    public const RUJUKAN_BATAL = ['Admin', 'Mr'];

    /* ─────────────────── PENDAFTARAN & BPJS PCare (daftar RJ) ─────────────────── */

    /** Edit data pendaftaran RJ dari titik-3. */
    public const DAFTAR_EDIT = ['Mr', 'Admin'];

    /** Kirim pendaftaran ke PCare (retry manual). */
    public const PCARE_KIRIM_PENDAFTARAN = ['Admin', 'Mr', 'Perawat'];

    /** Kirim / edit / hapus kunjungan PCare. */
    public const PCARE_KELOLA_KUNJUNGAN = ['Admin', 'Dokter'];

    /** Lihat riwayat kunjungan peserta di PCare. */
    public const PCARE_LIHAT_RIWAYAT = ['Admin', 'Dokter', 'Mr', 'Perawat'];

    /** Tombol Task ID antrean di Pelayanan RJ. */
    public const ANTREAN_TASK_ID = ['Perawat', 'Admin'];

    /* ─────────────────────────── EMR: RESEP & PENUNJANG ─────────────────────────── */

    /** Menulis / mengubah e-resep (racikan & non racikan, tab Terapi). */
    public const ERESEP_TULIS = ['Dokter', 'Admin'];

    /** Salin resep dari riwayat rekam medis. */
    public const RM_SALIN_RESEP = ['Dokter', 'Admin', 'Perawat'];

    /** Membuka berkas hasil penunjang yang diunggah. */
    public const PENUNJANG_LIHAT_BERKAS = ['Perawat', 'Admin', 'Dokter'];

    /** Mengunggah / menghapus berkas penunjang. */
    public const PENUNJANG_UNGGAH = ['Perawat', 'Admin'];

    /** Unduh hasil radiologi di display rekam medis. */
    public const RADIOLOGI_LIHAT_HASIL = ['Dokter', 'Admin', 'Perawat', 'Radiologi'];

    /** Buka rincian hasil lab di display rekam medis. */
    public const LAB_LIHAT_HASIL = ['Dokter', 'Admin', 'Perawat', 'Laboratorium'];

    /** Cetak hasil laboratorium. */
    public const LAB_CETAK = ['Dokter', 'Admin', 'Laboratorium'];

    /* ────────────────────────── GUDANG, KAS, LAPORAN ────────────────────────── */

    /** Kartu stok obat (gudang medis). */
    public const GUDANG_MEDIS = ['Admin', 'Apotek'];

    /** Gudang non-medis: penerimaan & kartu stok ATK/RT. */
    public const GUDANG_NON_MEDIS = ['Admin', 'Tu'];

    /** Hapus penerimaan obat dari PBF. */
    public const GUDANG_HAPUS_PENERIMAAN = ['Admin', 'Tu'];

    /** Hapus transaksi penerimaan / pengeluaran kas TU. */
    public const KAS_HAPUS_TRANSAKSI = ['Admin', 'Tu'];

    /** Laporan pendapatan klinik. */
    public const LAPORAN_PENDAPATAN = ['Admin', 'Tu'];

    /** Batal transfer di kasir RJ. */
    public const ADMINISTRASI_BATAL_TRANSFER = ['Admin', 'Tu'];

    /* ──────────────────────────── ADMIN SAJA (pendaftaran) ──────────────────────────── */

    /** Kirim/lihat data kunjungan ke SATUSEHAT dari titik-3 Daftar RJ. */
    public const SATUSEHAT_KIRIM = ['Admin'];

    /** Batal antrean (task-id 99) di Daftar RJ & Antrian Apotek. */
    public const ANTREAN_BATAL = ['Admin'];

    /** Hapus pendaftaran RJ. */
    public const DAFTAR_HAPUS = ['Admin'];
}
