<?php

namespace App\Support\Terminologi;

/**
 * Peta item MedicationRequest: pasangan "obat mana → MedicationRequest yang mana".
 *
 * Dicatat saat resep dikirim (`satusehat.medicationRequestItems`) supaya
 * MedicationDispense bisa menyebut `authorizingPrescription` yang benar tanpa menebak
 * lewat urutan daftar — geser satu item saja, obat tertaut ke resep yang salah.
 *
 * Untuk kunjungan yang resepnya dikirim SEBELUM peta ini ada, `bangunUlang()`
 * menyusunnya kembali: pengirimnya selalu memproses non-racikan dulu (urutan
 * ObatKfa::nonRacikanList) baru grup racikan yang siap (urutan RacikanKfa::grupList),
 * jadi urutannya deterministik. Tetap DITOLAK bila jumlahnya tak cocok — lebih baik
 * minta dikirim ulang daripada memasangkan obat ke resep yang salah.
 */
class MedicationRequestItem
{
    /** @return array<int, array{id:string, jenis:string, kunci:string, kode:string, display:string, qty:int}> */
    public static function ambil(array $satuSehat, array $data): array
    {
        $itemList = $satuSehat['medicationRequestItems'] ?? [];
        if (!empty($itemList)) {
            return $itemList;
        }

        return self::bangunUlang($satuSehat['medicationRequestIds'] ?? [], $data);
    }

    /** [] bila tak bisa disusun ulang dengan yakin. */
    public static function bangunUlang(array $idList, array $data): array
    {
        if ($idList === []) {
            return [];
        }

        $urutan = [];
        foreach (ObatKfa::nonRacikanList($data) as $obat) {
            $urutan[] = [
                'jenis' => 'nonRacikan',
                'kunci' => $obat['productId'],
                'kode' => $obat['code'],
                'display' => $obat['display'],
                'qty' => $obat['qty'],
            ];
        }
        foreach (RacikanKfa::grupList($data) as $grup) {
            if (!$grup['siap']) {
                continue;
            }
            $urutan[] = [
                'jenis' => 'racikan',
                'kunci' => $grup['noRacikan'],
                'kode' => '',
                'display' => 'Racikan ' . $grup['noRacikan'] . ' (' . $grup['jumlahBahan'] . ' bahan)',
                'qty' => 1,
            ];
        }

        // Jumlah tak sama = daftar obat berubah sesudah resep dikirim → jangan tebak.
        if (count($urutan) !== count($idList)) {
            return [];
        }

        foreach ($urutan as $indeks => $item) {
            $urutan[$indeks] = ['id' => $idList[$indeks]] + $item;
        }

        return $urutan;
    }
}
