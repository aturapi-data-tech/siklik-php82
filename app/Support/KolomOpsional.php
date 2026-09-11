<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Penjaga kolom Oracle yang ditambahkan lewat SQL manual, bukan migration.
 *
 * DB Oracle siklik adalah source of truth dan dipakai bareng siklik-lite legacy, jadi
 * kolom baru datang dari skrip di `database/sql/` yang dijalankan DBA — bukan dari
 * `php artisan migrate`. Akibatnya ada jendela waktu ketika kode sudah terpasang tapi
 * kolomnya belum ada: tanpa penjaga ini halaman yang menyebut kolom itu langsung
 * ORA-00904 dan mati total.
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
class KolomOpsional
{
    /**
     * Diagnosis/Keterangan Klinis order penunjang.
     * Header order lab = SKTXN_CHECKUPHDRS, order radiologi RJ = SKTXN_RJRADS.
     * SQL: database/sql/2026_09_11_alter_penunjang_add_klinis_desc.sql
     */
    public const KLINIS_DESC = 'klinis_desc';

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

    /** Semua kolom dalam daftar sudah ada? Dipakai fitur yang butuh beberapa kolom sekaligus. */
    public static function semuaAda(string $tabel, array $kolomList): bool
    {
        foreach ($kolomList as $kolom) {
            if (!self::ada($tabel, $kolom)) {
                return false;
            }
        }

        return true;
    }

    /** Header order lab sudah punya kolom Diagnosis/Keterangan Klinis? */
    public static function laboratPunyaKlinisDesc(): bool
    {
        return self::ada('sktxn_checkuphdrs', self::KLINIS_DESC);
    }

    /** Order radiologi rawat jalan sudah punya kolom Diagnosis/Keterangan Klinis? */
    public static function radiologiRjPunyaKlinisDesc(): bool
    {
        return self::ada('sktxn_rjrads', self::KLINIS_DESC);
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
