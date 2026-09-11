<?php

namespace App\Support\Terminologi;

use App\Support\EresepJson;
use App\Support\KolomSatuSehat;
use Illuminate\Support\Facades\DB;

/**
 * Obat NON-RACIKAN e-resep yang siap dikirim ke SATUSEHAT, sudah ber-kode KFA.
 *
 * JSON e-resep tidak menyimpan kode KFA sama sekali — yang ada `productId`. Kodenya
 * diambil dari master obat (`skmst_products.product_id_satusehat`). Sender lama membaca
 * key `kfaCode` yang TAK PERNAH ADA di JSON, sehingga 0 item terkirim sambil melapor
 * "berhasil" — itu yang dicegah di sini.
 *
 * Urutan keluarannya PENTING: dipakai membangun ulang pasangan resep→penyerahan untuk
 * kunjungan yang dikirim sebelum peta item dicatat (lihat [[MedicationRequestItem]]).
 *
 * Kolom KFA ditambahkan lewat SQL manual (`database/sql/2026_09_11_alter_skmst_products_add_satusehat.sql`),
 * jadi bisa saja belum ada saat kode ini berjalan — dijaga [[KolomSatuSehat]], dan
 * seluruh item dilaporkan sebagai "tanpa KFA", bukan melempar ORA-00904.
 *
 * Untuk racikan, lihat [[RacikanKfa]].
 */
class ObatKfa
{
    /**
     * @param  int|null  $obatTanpaKfa  diisi jumlah item yang dilewati (productId kosong
     *                                  atau master belum punya KFA) — WAJIB dilaporkan
     *                                  pemanggil, jangan dibuang diam-diam.
     * @return array<int, array{code:string, display:string, productId:string, qty:int}>
     */
    public static function nonRacikanList(array $data, ?int &$obatTanpaKfa = null): array
    {
        $obatTanpaKfa = 0;
        $itemList = [];

        foreach (EresepJson::lembar($data) as $lembar) {
            foreach ($lembar['nonRacikan'] as $obat) {
                $productId = trim((string) ($obat['productId'] ?? ''));
                if ($productId === '') {
                    $obatTanpaKfa++;
                    continue;
                }
                $itemList[] = [
                    'productId' => $productId,
                    'productName' => trim((string) ($obat['productName'] ?? '')),
                    'qty' => (int) ($obat['qty'] ?? 1) ?: 1,
                ];
            }
        }

        if ($itemList === []) {
            return [];
        }

        // Kolom KFA belum dibuat → semua item dihitung "tanpa KFA". Jangan query
        // kolomnya: Oracle membalas ORA-00904 dan kartu mati sebelum sempat melapor.
        if (!KolomSatuSehat::produkPunyaKfa()) {
            $obatTanpaKfa += count($itemList);

            return [];
        }

        $petaKfa = DB::table('skmst_products')
            ->whereIn('product_id', array_values(array_unique(array_column($itemList, 'productId'))))
            ->get(['product_id', KolomSatuSehat::PRODUK_KFA_KODE, KolomSatuSehat::PRODUK_KFA_NAMA])
            ->keyBy('product_id');

        $obatKfaList = [];
        foreach ($itemList as $obat) {
            $master = $petaKfa->get($obat['productId']);
            $kodeKfa = trim((string) ($master->{KolomSatuSehat::PRODUK_KFA_KODE} ?? ''));
            if ($kodeKfa === '') {
                $obatTanpaKfa++;
                continue;
            }
            $obatKfaList[] = [
                'code' => $kodeKfa,
                'display' => trim((string) ($master->{KolomSatuSehat::PRODUK_KFA_NAMA} ?? '')) ?: $obat['productName'],
                'productId' => $obat['productId'],
                'qty' => $obat['qty'],
            ];
        }

        return $obatKfaList;
    }
}
