<?php

namespace App\Support\Terminologi;

use App\Support\EresepJson;
use App\Support\KolomSatuSehat;
use Illuminate\Support\Facades\DB;

/**
 * Menyiapkan grup racikan e-resep untuk dikirim ke SATUSEHAT sebagai compound.
 *
 * SATUSEHAT menuntut kode KFA PER BAHAN (Medication.ingredient[]), sedangkan JSON
 * e-resep tidak menyimpan kode KFA sama sekali. Di siklik bahkan lebih sempit:
 * baris `eresepRacikan[]` TIDAK punya `productId` sama sekali (probe 11/09/2026 atas
 * RJ 23977 dkk — yang ada cuma `productName`, `dosis`, `qty`, `noRacikan`). Pemetaannya
 * karena itu dua jalur:
 *
 *   1. `productId` terisi  → ambil kode KFA dari skmst_products lewat product_id.
 *   2. `productId` kosong  → cocokkan `productName` ke master obat, dan HANYA diterima
 *      bila ada TEPAT SATU produk ber-KFA dengan nama itu.
 *
 * Aturan (2) sengaja ketat: begitu ada dua produk bernama sama (beda kekuatan/merek),
 * menebak berarti salah obat. Kandidat ganda DITOLAK, bukan diambil yang pertama.
 *
 * Grup yang tidak lolos WAJIB dilaporkan pemanggil, jangan dibuang diam-diam
 * (lihat catatan [[EresepJson]]).
 */
class RacikanKfa
{
    /**
     * Grup racikan siap-kirim beserta alasan untuk yang gagal.
     *
     * @return array<int, array{noRacikan:string, jumlahBahan:int, siap:bool, alasan:string,
     *                          bahanList:array<int, array{code:string, display:string}>}>
     */
    public static function grupList(array $data): array
    {
        $grupList = [];
        foreach (EresepJson::lembar($data) as $lembar) {
            foreach ($lembar['racikan'] as $noRacikan => $bahanList) {
                $grupList[] = ['noRacikan' => (string) $noRacikan, 'bahanList' => $bahanList];
            }
        }
        if ($grupList === []) {
            return [];
        }

        // Kolom KFA belum dibuat → tak ada grup yang bisa siap, tapi daftarnya tetap
        // dipulangkan lengkap dengan alasannya supaya kartu bisa menghitungnya.
        if (!KolomSatuSehat::produkPunyaKfa()) {
            return array_map(fn ($grup) => [
                'noRacikan' => $grup['noRacikan'],
                'jumlahBahan' => count($grup['bahanList']),
                'siap' => false,
                'alasan' => 'Master Obat belum punya kolom pemetaan KFA (skmst_products.'
                    . KolomSatuSehat::PRODUK_KFA_KODE . ')',
                'bahanList' => [],
            ], $grupList);
        }

        [$kfaByProductId, $kfaByNama] = self::petaKfa($grupList);

        $hasil = [];
        foreach ($grupList as $grup) {
            $bahanSiap = [];
            $gagal = [];

            foreach ($grup['bahanList'] as $bahan) {
                $productId = trim((string) ($bahan['productId'] ?? ''));
                $productName = trim((string) ($bahan['productName'] ?? ''));

                $master = $productId !== ''
                    ? ($kfaByProductId[$productId] ?? null)
                    : ($kfaByNama[mb_strtoupper($productName)] ?? null);

                if ($master === null) {
                    $gagal[] = $productName !== '' ? $productName : '(tanpa nama)';
                    continue;
                }

                $bahanSiap[] = ['code' => $master['code'], 'display' => $master['display'] ?: $productName];
            }

            $hasil[] = [
                'noRacikan' => $grup['noRacikan'],
                'jumlahBahan' => count($grup['bahanList']),
                'siap' => $gagal === [] && $bahanSiap !== [],
                'alasan' => $gagal === [] ? '' : 'bahan tanpa padanan KFA: ' . implode(', ', $gagal),
                'bahanList' => $bahanSiap,
            ];
        }

        return $hasil;
    }

    /** Ringkasan untuk kartu: berapa grup siap kirim, berapa yang tidak. */
    public static function ringkas(array $data): array
    {
        $grupList = self::grupList($data);
        $siap = array_values(array_filter($grupList, fn ($grup) => $grup['siap']));

        return [
            'total' => count($grupList),
            'siap' => count($siap),
            'takSiap' => count($grupList) - count($siap),
        ];
    }

    /**
     * Dua peta lookup master obat, diambil sekali untuk semua bahan:
     * productId → KFA, dan NAMA (huruf besar) → KFA bila namanya tak kembar.
     */
    private static function petaKfa(array $grupList): array
    {
        $kolomKode = KolomSatuSehat::PRODUK_KFA_KODE;
        $kolomNama = KolomSatuSehat::PRODUK_KFA_NAMA;

        $productIdList = [];
        $namaList = [];
        foreach ($grupList as $grup) {
            foreach ($grup['bahanList'] as $bahan) {
                $productId = trim((string) ($bahan['productId'] ?? ''));
                if ($productId !== '') {
                    $productIdList[$productId] = true;
                    continue;
                }
                $nama = trim((string) ($bahan['productName'] ?? ''));
                if ($nama !== '') {
                    $namaList[mb_strtoupper($nama)] = true;
                }
            }
        }

        $kfaByProductId = [];
        if ($productIdList !== []) {
            $kfaByProductId = DB::table('skmst_products')
                ->whereIn('product_id', array_keys($productIdList))
                // Oracle: '' identik NULL, jadi jangan pakai <> '' (lihat skill oracle-quirks).
                ->whereRaw("{$kolomKode} IS NOT NULL AND LENGTH(TRIM({$kolomKode})) > 0")
                ->get(['product_id', $kolomKode, $kolomNama, 'product_name'])
                ->mapWithKeys(fn ($baris) => [(string) $baris->product_id => [
                    'code' => trim((string) $baris->{$kolomKode}),
                    'display' => trim((string) ($baris->{$kolomNama} ?: $baris->product_name)),
                ]])
                ->all();
        }

        $kfaByNama = [];
        if ($namaList !== []) {
            $kandidatList = DB::table('skmst_products')
                ->whereIn(DB::raw('UPPER(TRIM(product_name))'), array_keys($namaList))
                ->whereRaw("{$kolomKode} IS NOT NULL AND LENGTH(TRIM({$kolomKode})) > 0")
                ->get([$kolomKode, $kolomNama, 'product_name']);

            $perNama = [];
            foreach ($kandidatList as $baris) {
                $perNama[mb_strtoupper(trim((string) $baris->product_name))][] = [
                    'code' => trim((string) $baris->{$kolomKode}),
                    'display' => trim((string) ($baris->{$kolomNama} ?: $baris->product_name)),
                ];
            }
            foreach ($perNama as $nama => $kandidat) {
                // Nama kembar = ambigu → tidak dipakai, bahan itu dilaporkan gagal.
                if (count($kandidat) === 1) {
                    $kfaByNama[$nama] = $kandidat[0];
                }
            }
        }

        return [$kfaByProductId, $kfaByNama];
    }

    /**
     * Bentuk FHIR Medication.ingredient[] untuk satu grup.
     *
     * Kekuatan (strength) sengaja tidak diisi: JSON hanya menyimpan dosis sebagai teks
     * bebas ("1/2", "500mg", "sesuai bb"), menebak angkanya berisiko salah takar.
     */
    public static function fhirIngredient(array $bahanList): array
    {
        return array_map(fn ($bahan) => [
            'itemCodeableConcept' => [
                'coding' => [[
                    'system' => 'http://sys-ids.kemkes.go.id/kfa',
                    'code' => $bahan['code'],
                    'display' => $bahan['display'],
                ]],
            ],
            'isActive' => true,
        ], $bahanList);
    }
}
