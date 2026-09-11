<?php

namespace App\Support\Options;

/**
 * Definisi skala nyeri yang dipakai EMR RJ siklik + penyeragam bentuk entri.
 *
 * Diport dari sirus-php82 dan DIPANGKAS ke yang benar-benar dipakai siklik:
 * `nyeriMetodeOptions` di ⚡rm-penilaian-rj-actions hanya memuat NRS, BPS, NIPS,
 * FLACC, VAS (tanpa WBS/CPOT/PAINAD), dan tata laksana/badge milik sirus tidak
 * dipakai form siklik sehingga tidak ikut diport — menaruhnya di sini berarti
 * kode mati yang menyesatkan pembaca berikutnya.
 *
 * Kegunaan utamanya di siklik: normalisasiEntri()/daftarEntri(). DATA NYATA siklik
 * (24.001 baris sktxn_rjhdrs, diperiksa 11/09/2026) menyimpan `penilaian.nyeri`
 * dalam BENTUK LAMA — satu entri assoc, `nyeriMetode` berupa STRING, skor di
 * `skalaNyeri` atau `vas.vas`, dan `nyeriKet` berisi Akut/Kronis. Form baru
 * (defaultFormEntryNyeriState) menulis bentuk LIST dengan node `nyeri.nyeriMetode`
 * berupa array. Keduanya harus lewat sini supaya pembaca cukup mengenal satu bentuk.
 */
class NyeriOptions
{
    /**
     * Skala yang boleh dipilih petugas. Urutannya mengikuti dropdown EMR.
     *
     * 'tipe': 'angka' skor diketik · 'pilih' skor dipilih dari daftar ·
     *         'item'  skor = jumlah item yang dipilih.
     */
    public const SKALA = [
        'NRS' => ['nama' => 'Numeric Rating Scale', 'tipe' => 'angka', 'min' => 0, 'max' => 10],
        'BPS' => ['nama' => 'Behavioral Pain Scale', 'tipe' => 'item', 'min' => 3, 'max' => 12],
        'NIPS' => ['nama' => 'Neonatal Infant Pain Scale', 'tipe' => 'item', 'min' => 0, 'max' => 7],
        'FLACC' => ['nama' => 'Face, Legs, Activity, Cry, Consolability', 'tipe' => 'item', 'min' => 0, 'max' => 10],
        'VAS' => ['nama' => 'Visual Analogue Scale', 'tipe' => 'pilih', 'min' => 0, 'max' => 10],
    ];

    /** Definisi satu skala; null bila kode tidak dikenal (mis. entri lama). */
    public static function skala(?string $kode): ?array
    {
        return self::SKALA[$kode ?? ''] ?? null;
    }

    /**
     * Samakan satu entri penilaian ke bentuk baku ['nyeri' => [...]].
     *
     * Tiga bentuk yang beredar di JSON EMR siklik:
     *  1. entri baku  : ['nyeri' => ['nyeriMetode' => ['nyeriMetode' => 'NRS', 'nyeriMetodeScore' => 5], ...]]
     *  2. node saja   : ['nyeriMetode' => [...], ...]
     *  3. record lama : nyeriMetode masih STRING, skor di skalaNyeri / vas.vas —
     *                   inilah yang benar-benar ada di basis data siklik sekarang.
     *
     * Parameter sengaja mixed: record lama menyimpan `penilaian.nyeri` sebagai SATU
     * entri assoc, sehingga pemanggil bisa mengirim nilai bukan-array ke sini.
     */
    public static function normalisasiEntri(mixed $entri): array
    {
        if (!is_array($entri) || $entri === []) {
            return [];
        }

        // Entri baku punya key 'nyeri' berisi array; pada record lama key 'nyeri'
        // justru berisi string 'Ya'/'Tidak', jadi entri itu sendiri adalah node-nya.
        $adalahEntriBaku = is_array($entri['nyeri'] ?? null);
        $node = $adalahEntriBaku ? $entri['nyeri'] : $entri;

        if (!is_array($node['nyeriMetode'] ?? null)) {
            $skor = $node['skalaNyeri'] ?? null;
            if ($skor === null || $skor === '') {
                $skor = data_get($node, 'vas.vas');
            }
            $node['nyeriMetode'] = [
                'nyeriMetode' => is_string($node['nyeriMetode'] ?? null) ? $node['nyeriMetode'] : '',
                'nyeriMetodeScore' => $skor,
            ];
        }

        return ['nyeri' => $node] + ($adalahEntriBaku ? $entri : []);
    }

    /**
     * Riwayat penilaian nyeri sebagai DAFTAR entri baku.
     *
     * Record lama menyimpan `penilaian.nyeri` sebagai SATU entri (assoc), bukan
     * daftar; bila record itu dinilai lagi lewat EMR baru, entri baru ditambahkan
     * berkunci angka di samping key lama sehingga isinya campuran. Entri lama
     * ditaruh paling depan supaya urutannya tetap kronologis.
     */
    public static function daftarEntri(mixed $riwayat): array
    {
        if (!is_array($riwayat) || $riwayat === []) {
            return [];
        }

        $daftar = [];
        $entriLama = [];
        foreach ($riwayat as $kunci => $nilai) {
            if (is_int($kunci)) {
                if (is_array($nilai) && $nilai !== []) {
                    $daftar[] = self::normalisasiEntri($nilai);
                }
                continue;
            }
            $entriLama[$kunci] = $nilai;
        }

        if ($entriLama !== []) {
            array_unshift($daftar, self::normalisasiEntri($entriLama));
        }

        return $daftar;
    }
}
