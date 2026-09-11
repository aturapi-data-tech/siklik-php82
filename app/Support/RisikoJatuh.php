<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Penanda risiko jatuh pasien — satu sumber logika "penilaian terakhir".
 *
 * Penilaian risiko jatuh disimpan sebagai DERET entri di CLOB EMR
 * (`sktxn_rjhdrs.datadaftarpolirj_json`, node `penilaian.resikoJatuh[]`).
 * Tiap entri berbentuk:
 *
 *   {
 *     "tglPenilaian": "05/06/2026 09:30:00",
 *     "petugasPenilai": "...",
 *     "resikoJatuh": {
 *       "resikoJatuh": "Ya|Tidak",
 *       "resikoJatuhMetode": { "resikoJatuhMetode": "Skala Morse|Humpty Dumpty",
 *                              "resikoJatuhMetodeScore": 45, "dataResikoJatuh": {...} },
 *       "kategoriResiko": "Rendah|Sedang|Tinggi",
 *       "rekomendasi": "..."
 *     }
 *   }
 *
 * Dipakai bersama oleh Display Pasien RJ, baris Daftar RJ, dan baris Pelayanan RJ
 * supaya tiga layar itu tidak pernah menampilkan kategori yang berbeda untuk pasien
 * yang sama. Fungsi ini MURNI — tidak menyentuh DB; pemanggil menyuapkan array EMR
 * yang sudah di-decode (di halaman list: `$json` per baris, tanpa query/baca CLOB
 * tambahan sehingga CLOB-defer halaman tetap utuh).
 */
class RisikoJatuh
{
    /** Format tanggal entri penilaian, sesuai yang ditulis form Penilaian RJ. */
    private const FORMAT_TGL = 'd/m/Y H:i:s';

    /** Hanya kategori ini yang memunculkan penanda; Rendah/kosong sengaja senyap. */
    public const KATEGORI_BERPENANDA = ['Sedang', 'Tinggi'];

    /**
     * Penilaian risiko jatuh TERAKHIR yang layak jadi penanda.
     *
     * "Terakhir" = `tglPenilaian` paling baru — bukan entri paling belakang: tanggal
     * diketik manual dan bisa diisi mundur, jadi urutan array tidak dijamin kronologis.
     * Kalau tanggal tak terparse (kosong/format lain), jatuh ke urutan input.
     *
     * @param  array  $dataEmr  isi CLOB EMR yang sudah di-decode
     * @return array{kategori:string,metode:string,skor:string,tgl:string}|array{}
     *         kosong bila belum dinilai atau kategorinya Rendah → penanda tidak tampil
     */
    public static function terakhir(array $dataEmr): array
    {
        $daftarPenilaian = $dataEmr['penilaian']['resikoJatuh'] ?? [];

        // Data lama siklik-lite menyimpan node ini sebagai OBJEK (skalaMorse /
        // skalaHumptyDumpty), bukan deret entri — bentuk itu tidak punya kategori,
        // jadi diabaikan alih-alih dipaksa dibaca.
        if (!is_array($daftarPenilaian) || !array_is_list($daftarPenilaian)) {
            return [];
        }

        $terakhir = null;
        $maxTimestamp = null;

        foreach ($daftarPenilaian as $entri) {
            if (!is_array($entri)) {
                continue;
            }

            $timestamp = self::timestamp($entri['tglPenilaian'] ?? '');

            // >= : tanggal sama / tak terparse → entri yang diinput belakangan menang
            if ($terakhir === null || $timestamp === null || $maxTimestamp === null || $timestamp >= $maxTimestamp) {
                $terakhir = $entri;
                $maxTimestamp = $timestamp ?? $maxTimestamp;
            }
        }

        $kategori = $terakhir['resikoJatuh']['kategoriResiko'] ?? '';
        if (!in_array($kategori, self::KATEGORI_BERPENANDA, true)) {
            return [];
        }

        return [
            'kategori' => $kategori,
            'metode' => $terakhir['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetode'] ?? '',
            'skor' => (string) ($terakhir['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] ?? ''),
            'tgl' => $terakhir['tglPenilaian'] ?? '',
        ];
    }

    /** Timestamp entri, atau null bila tanggal kosong / bukan format form Penilaian RJ. */
    private static function timestamp(mixed $tglPenilaian): ?int
    {
        if (!is_string($tglPenilaian) || trim($tglPenilaian) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat(self::FORMAT_TGL, trim($tglPenilaian))->getTimestamp();
        } catch (\Throwable) {
            return null;
        }
    }
}
