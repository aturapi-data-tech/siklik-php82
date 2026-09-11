<?php

namespace App\Support\Terminologi;

/**
 * Key alergi BPJS PCare di node `anamnesa.alergi` (jenis 01 makanan / 02 udara / 03 obat).
 *
 * Masalah: siklik-lite menulis alergi makanan sebagai `alergiMakanan`/`alergiMakananDesc`
 * (20.787 kunjungan), sedangkan kode baru & payload PCare memakai `alergiMakan`/`alergiMakanDesc`
 * (420 kunjungan). Tanpa jembatan ini record lama terbaca "00 Tidak Ada" di form & PCare.
 *
 * Kebijakan: TIDAK migrasi massal (siklik-lite masih membaca key lama). Pembaca menerima
 * dua key (key baru menang bila sudah diisi), penulis mencerminkan nilai ke key lama bila
 * record memang punya key lama — sehingga keduanya tetap konsisten selama masa transisi.
 * Lihat docs/migrasi-skema-data.md §2.
 */
final class AlergiPcare
{
    public const KEY = 'alergiMakan';

    public const KEY_LEGACY = 'alergiMakanan';

    public const TIDAK_ADA = '00';

    /** Kode alergi makanan — terima dua key; key baru menang bila sudah diisi selain default. */
    public static function kodeMakan(array $node): string
    {
        $baru = trim((string) ($node[self::KEY] ?? ''));
        $legacy = trim((string) ($node[self::KEY_LEGACY] ?? ''));

        if ($baru !== '' && ($baru !== self::TIDAK_ADA || $legacy === '' || $legacy === self::TIDAK_ADA)) {
            return $baru;
        }

        return $legacy !== '' ? $legacy : ($baru !== '' ? $baru : self::TIDAK_ADA);
    }

    /** Deskripsi alergi makanan yang sepadan dengan kodeMakan(). */
    public static function descMakan(array $node): string
    {
        $kode = self::kodeMakan($node);
        $dariBaru = trim((string) ($node[self::KEY] ?? ''));

        if ($dariBaru === $kode && isset($node[self::KEY.'Desc'])) {
            return (string) $node[self::KEY.'Desc'];
        }

        return (string) ($node[self::KEY_LEGACY.'Desc'] ?? $node[self::KEY.'Desc'] ?? ($kode === self::TIDAK_ADA ? 'Tidak Ada' : ''));
    }

    /**
     * Saat form DIBUKA: isi key baru dari key lama supaya petugas melihat nilai yang sebenarnya.
     * Dipanggil SEBELUM default anamnesa di-merge (default menyetel alergiMakan = '00').
     */
    public static function normalisasiKey(array $node): array
    {
        if (! array_key_exists(self::KEY_LEGACY, $node)) {
            return $node;
        }

        // Hitung keduanya dari node ASLI dulu — kalau kode ditimpa lebih dahulu, descMakan()
        // akan mengira key baru yang berlaku dan mengembalikan "Tidak Ada" bawaan default.
        [$kode, $desc] = [self::kodeMakan($node), self::descMakan($node)];
        $node[self::KEY] = $kode;
        $node[self::KEY.'Desc'] = $desc;

        return $node;
    }

    /** Saat SIMPAN: bila record punya key lama, samakan isinya dengan key baru (siklik-lite tetap membaca benar). */
    public static function cerminLegacy(array $node): array
    {
        if (! array_key_exists(self::KEY_LEGACY, $node) || ! array_key_exists(self::KEY, $node)) {
            return $node;
        }

        $node[self::KEY_LEGACY] = (string) $node[self::KEY];
        $node[self::KEY_LEGACY.'Desc'] = (string) ($node[self::KEY.'Desc'] ?? '');

        return $node;
    }
}
