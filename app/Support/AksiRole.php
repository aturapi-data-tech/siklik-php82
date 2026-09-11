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
}
