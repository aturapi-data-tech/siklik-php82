<?php

namespace App\Support;

/**
 * Penjaga kolom SATUSEHAT yang ditambahkan lewat SQL manual, bukan migration.
 *
 * Mesin pengeceknya (cache per-request + pembacaan `user_tab_columns`) ada di
 * {@see KolomOpsional}; kelas ini tinggal menamai kolom-kolom SATUSEHAT-nya.
 * Alasan lengkap kenapa penjaga ini perlu ada tertulis di KolomOpsional.
 */
class KolomSatuSehat extends KolomOpsional
{
    /** Kolom pemetaan Master Obat → kode & nama KFA SATUSEHAT. */
    public const PRODUK_KFA_KODE = 'product_id_satusehat';
    public const PRODUK_KFA_NAMA = 'product_name_satusehat';

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
}
