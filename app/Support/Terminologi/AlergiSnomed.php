<?php

namespace App\Support\Terminologi;

/**
 * Kode SNOMED "tidak ada alergi" untuk anamnesa RJ (AllergyIntolerance SATUSEHAT).
 *
 * Diport dari sirus-php82 dan DIPANGKAS: siklik tidak punya key `adaAlergi`
 * (lihat default anamnesa di ⚡rm-anamnesa-rj-actions), jadi normalisasi radio
 * Ya/Tidak milik sirus TIDAK ikut diport — di sini keadaan "tidak ada alergi"
 * hanya bisa dikenali dari KODE-nya (atau dari teks lama, lihat adalahTeksTidakAda).
 *
 * Semua kode di sini berasal dari sirus yang memverifikasinya lewat terminology
 * server tx.fhir.org (CodeSystem/$lookup) — jangan menambah/mengubah dari hafalan.
 *
 * Node sumber siklik: anamnesa.alergi.{alergi, snomedCode, snomedDisplayEn,
 * snomedDisplayId} — snomedCode diisi LOV SNOMED (event lov.selected.alergiSnomed).
 */
class AlergiSnomed
{
    /** Tidak ada alergi (umum). */
    public const TIDAK_ADA = [
        'alergi' => 'Tidak ada alergi',
        'snomedCode' => '716186003',
        'snomedDisplayEn' => 'No known allergy',
        'snomedDisplayId' => 'Tidak ada alergi yang diketahui',
    ];

    public const TIDAK_ADA_OBAT = [
        'alergi' => 'Tidak ada alergi obat',
        'snomedCode' => '409137002',
        'snomedDisplayEn' => 'No known history of drug allergy',
        'snomedDisplayId' => 'Tidak ada riwayat alergi obat',
    ];

    public const TIDAK_ADA_MAKANAN = [
        'alergi' => 'Tidak ada alergi makanan',
        'snomedCode' => '429625007',
        'snomedDisplayEn' => 'No known food allergy',
        'snomedDisplayId' => 'Tidak ada alergi makanan yang diketahui',
    ];

    public const TIDAK_ADA_LINGKUNGAN = [
        'alergi' => 'Tidak ada alergi lingkungan',
        'snomedCode' => '428607008',
        'snomedDisplayEn' => 'No known environmental allergy',
        'snomedDisplayId' => 'Tidak ada alergi lingkungan yang diketahui',
    ];

    /**
     * Ejaan "tidak ada alergi" yang beredar di data lama siklik. Dicocokkan atas
     * teks ternormalkan (huruf kecil, tanpa spasi & tanda baca).
     *
     * Dipakai HANYA untuk memberi keterangan di pratinjau — BUKAN untuk menurunkan
     * kode. Teks bebas tidak pernah diubah menjadi kode SNOMED secara diam-diam:
     * kode wajib dipilih petugas lewat LOV, kalau tidak Kirim ditolak.
     */
    private const TEKS_TIDAK_ADA = [
        'tidakada', 'tidakadaalergi', 'tidakadaalergiyangdiketahui',
        'tidaka', 'tidakda', 'tridakada', 'tdkada', 'tidak',
        'disangkal', 'nihil', 'none', '-',
    ];

    /**
     * Apakah kode ini pernyataan "tidak ada alergi" (bukan zat penyebab)?
     *
     * Dipakai sender SATUSEHAT untuk memutuskan `type`/`criticality` — keduanya
     * atribut alergi yang ADA, jadi dihilangkan pada pernyataan "no known allergy".
     * `category` TIDAK bisa ikut dihilangkan: SATUSEHAT menolaknya dengan
     * "Element not found: AllergyIntolerance.category (RuleNumber: 10075)" —
     * lihat kategoriFhir().
     */
    public static function adalahTidakAdaAlergi(?string $kode): bool
    {
        $k = trim((string) $kode);
        if ($k === '') {
            return false;
        }

        return in_array($k, [
            self::TIDAK_ADA['snomedCode'],
            self::TIDAK_ADA_OBAT['snomedCode'],
            self::TIDAK_ADA_MAKANAN['snomedCode'],
            self::TIDAK_ADA_LINGKUNGAN['snomedCode'],
        ], true);
    }

    /**
     * Kategori FHIR AllergyIntolerance (food | medication | environment | biologic).
     *
     * SATUSEHAT MEWAJIBKAN elemen ini — termasuk pada pernyataan "tidak ada alergi",
     * walau di sana ia janggal. Ditolak "Element not found: AllergyIntolerance.category
     * (RuleNumber: 10075)" kalau dikosongkan, jadi tidak ada pilihan selain mengisinya.
     *
     * Kode "tidak ada alergi" yang SPESIFIK dipetakan apa adanya; sisanya (termasuk
     * 716186003 yang umum dan semua zat penyebab) jatuh ke 'medication' karena kolom
     * alergi di anamnesa siklik dipakai untuk alergi obat. Kalau kelak alergi
     * makanan/lingkungan mau dibedakan, sumber datanya harus MENYIMPAN kategorinya
     * dulu — jangan ditebak dari teks bebas.
     */
    public static function kategoriFhir(?string $kode): string
    {
        return match (trim((string) $kode)) {
            self::TIDAK_ADA_MAKANAN['snomedCode'] => 'food',
            self::TIDAK_ADA_LINGKUNGAN['snomedCode'] => 'environment',
            default => 'medication',
        };
    }

    /**
     * Apakah TEKS alergi berbunyi "tidak ada"? Hanya untuk keterangan di layar —
     * tidak boleh dipakai memutuskan isi payload.
     */
    public static function adalahTeksTidakAda(?string $teks): bool
    {
        $t = trim((string) $teks);
        if ($t === '') {
            return false;
        }

        return in_array(self::norm($t), self::TEKS_TIDAK_ADA, true);
    }

    /** Normalkan teks bebas: huruf kecil, buang spasi & tanda baca. */
    private static function norm(string $teks): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower(trim($teks))) ?? '';
    }
}
