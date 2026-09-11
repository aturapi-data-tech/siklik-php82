<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Penjaga kolom SATUSEHAT yang ditambahkan lewat SQL manual, bukan migration.
 *
 * DB Oracle siklik adalah source of truth dan dipakai bareng siklik-lite legacy, jadi
 * kolom baru datang dari skrip di `database/sql/` yang dijalankan DBA — bukan dari
 * `php artisan migrate`. Akibatnya ada jendela waktu ketika kode sudah terpasang tapi
 * kolomnya belum ada: tanpa penjaga ini halaman Master Obat / Master Radiologi dan
 * kartu kirim langsung ORA-00904 dan mati total.
 *
 * `Schema::hasColumn()` menanyakan kamus data setiap kali dipanggil; di halaman list
 * yang memanggilnya per baris itu puluhan query untuk jawaban yang sama. Di sini
 * jawabannya di-cache PER REQUEST — cukup lama untuk satu render halaman, cukup pendek
 * supaya tak perlu dibersihkan sesudah DBA menjalankan SQL-nya (request berikutnya
 * sudah melihat kolomnya).
 *
 * Kegagalan apa pun (koneksi, hak akses) dibaca sebagai "kolom belum ada": halaman tetap
 * tampil apa adanya, bukan melempar exception.
 */
class KolomSatuSehat
{
    /** Kolom pemetaan Master Obat → kode & nama KFA SATUSEHAT. */
    public const PRODUK_KFA_KODE = 'product_id_satusehat';
    public const PRODUK_KFA_NAMA = 'product_name_satusehat';

    /** Cache per-request: "TABEL.KOLOM" → ada/tidak. */
    private static array $cache = [];

    /** Apakah satu kolom sudah ada di tabel Oracle? */
    public static function ada(string $tabel, string $kolom): bool
    {
        $kunci = strtoupper($tabel) . '.' . strtoupper($kolom);

        if (!array_key_exists($kunci, self::$cache)) {
            self::$cache[$kunci] = self::tanyakan(strtoupper($tabel), strtoupper($kolom));
        }

        return self::$cache[$kunci];
    }

    /** Semua kolom dalam daftar sudah ada? Dipakai fitur yang butuh kode DAN nama sekaligus. */
    public static function semuaAda(string $tabel, array $kolomList): bool
    {
        foreach ($kolomList as $kolom) {
            if (!self::ada($tabel, $kolom)) {
                return false;
            }
        }

        return true;
    }

    /** Master Obat sudah punya pasangan kolom KFA (kode + nama)? */
    public static function produkPunyaKfa(): bool
    {
        return self::semuaAda('skmst_products', [self::PRODUK_KFA_KODE, self::PRODUK_KFA_NAMA]);
    }

    /** Master Radiologi sudah punya pasangan kolom LOINC? */
    public static function radiologiPunyaLoinc(): bool
    {
        return self::semuaAda('skmst_radiologis', ['loinc_code', 'loinc_display']);
    }

    private static function tanyakan(string $tabel, string $kolom): bool
    {
        try {
            $baris = DB::selectOne(
                'select count(*) as jml from user_tab_columns where table_name = :tabel and column_name = :kolom',
                ['tabel' => $tabel, 'kolom' => $kolom]
            );

            return (int) ($baris->jml ?? 0) > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
