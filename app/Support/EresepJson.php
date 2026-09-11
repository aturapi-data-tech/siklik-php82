<?php

namespace App\Support;

/**
 * Pembaca node e-resep di JSON EMR Rawat Jalan.
 *
 * siklik = klinik pratama RAWAT JALAN saja: satu kunjungan, satu lembar resep. Cabang
 * berlembar milik rawat inap sirus (`eresepHdr[]`) SENGAJA tidak diport — tidak ada
 * modul RI/UGD di sini, dan menyalinnya hanya menambah cabang mati.
 *
 *   eresep[]        → obat non-racikan
 *                     { productId, productName, qty, signaX, signaHari, catatanKhusus,
 *                       jenisKeterangan, productPrice, rjObatDtl, rjNo }
 *   eresepRacikan[] → baris BAHAN racikan (satu obat racikan = beberapa baris)
 *                     { productName, dosis, qty, noRacikan, catatanKhusus, … }
 *
 * Racikan dikelompokkan per `noRacikan` ("R1", "R2"): satu grup = satu obat racikan,
 * anggotanya = bahan-bahannya. Baris tanpa noRacikan dikumpulkan ke grup '-'.
 *
 * CATATAN DATA (probe 11/09/2026, RJ 23977 dkk): baris `eresepRacikan[]` siklik TIDAK
 * menyimpan `productId` sama sekali — hanya `productName` berupa teks. Pemetaan ke KFA
 * karena itu bertumpu pada pencocokan nama ke master obat (lihat [[RacikanKfa]]), dan
 * bahan yang tak terpetakan WAJIB dilaporkan pemanggil, jangan dibuang diam-diam.
 */
class EresepJson
{
    /**
     * Normalkan node e-resep jadi satu lembar seragam.
     *
     * Memulangkan array lembar (0 atau 1 elemen) supaya pemanggil berbentuk sama dengan
     * sirus dan tidak perlu cabang khusus saat resepnya kosong.
     *
     * @return array<int, array{nonRacikan: array, racikan: array<string, array<int, array>>}>
     */
    public static function lembar(array $data): array
    {
        $nonRacikan = $data['eresep'] ?? [];
        $racikan = $data['eresepRacikan'] ?? [];

        if (empty($nonRacikan) && empty($racikan)) {
            return [];
        }

        return [[
            'nonRacikan' => array_values(array_filter(is_array($nonRacikan) ? $nonRacikan : [], 'is_array')),
            'racikan' => self::kelompokkanRacikan($racikan),
        ]];
    }

    /**
     * Kelompokkan baris bahan per noRacikan.
     *
     * @return array<string, array<int, array>>
     */
    private static function kelompokkanRacikan(mixed $barisList): array
    {
        if (!is_array($barisList)) {
            return [];
        }

        $grup = [];
        foreach ($barisList as $bahan) {
            if (!is_array($bahan)) {
                continue;
            }
            $noRacikan = trim((string) ($bahan['noRacikan'] ?? ''));
            $grup[$noRacikan !== '' ? $noRacikan : '-'][] = $bahan;
        }

        return $grup;
    }

    /** Total grup racikan (= jumlah obat racikan) di resep kunjungan ini. */
    public static function jumlahRacikan(array $data): int
    {
        $jumlah = 0;
        foreach (self::lembar($data) as $lembar) {
            $jumlah += count($lembar['racikan']);
        }

        return $jumlah;
    }
}
