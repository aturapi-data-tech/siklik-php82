<?php

namespace App\Support;

/**
 * Konversi node `penilaian` EMR RJ dari bentuk LEGACY siklik-lite (objek assoc per skala) ke bentuk
 * BARU form siklik-php82 (list entri ber-tanggal). Satu sumber untuk:
 *   - php artisan siklik:migrasi-json-emr  (migrasi massal CLOB, dengan salinan legacy untuk rollback)
 *   - ⚡rm-penilaian-rj-actions::rendering() (record lama yang dibuka sebelum migrasi tidak boleh
 *     menulis balik bentuk legacy)
 *
 * Fakta data (11 Sep 2026, 24.001 kunjungan dev): 13.761 punya node penilaian, SEMUA legacy; hanya 12 yang
 * benar-benar berisi (2 risiko jatuh, 11 VAS nyeri). Sisanya boilerplate default siklik-lite — TIDAK boleh
 * dijadikan entri "Rendah"/"Tidak nyeri" (itu klaim klinis palsu), cukup jadi list kosong.
 *
 * Aturan (lihat docs/migrasi-skema-data.md §2):
 *   - Morse: teks & skor opsi identik dengan form baru → pemetaan key lossless.
 *   - Humpty Dumpty: rentang skor beda (legacy maks 23, baru 19; item responTerhadapOperasi tak ada) → skor total
 *     legacy dipakai apa adanya, item dipetakan lewat skor bila ada padanannya.
 *   - kategoriResiko dihitung ULANG dengan ambang form baru (Morse ≥45 Tinggi ≥25 Sedang; HD ≥16 Tinggi ≥12 Sedang);
 *     label legacy (`skalaMorseDesc`) diabaikan karena ambangnya beda (45 = "Risiko Rendah" di legacy).
 *   - Nyeri legacy hanya VAS (input lain dikomentari di form lama) → entri VAS + nyeriKet dari ambang form baru.
 *   - Dekubitus/statusPediatrik/fisik/diagnosis legacy: bila kosong → list kosong; bila berisi → dibiarkan dan
 *     dilaporkan (skala Norton ≠ Braden, tidak bisa dipetakan otomatis).
 */
final class PenilaianLegacy
{
    public const VERSI = 1;

    public const PETUGAS_CODE_MIGRASI = 'MIGRASI';

    private const MORSE_PETA = [
        // legacy key => [key baru, opsi teks legacy => skor]
        'riwayatJatuh3blnTerakhir' => ['riwayatJatuh', ['Ya' => 25, 'Tidak' => 0]],
        'diagSekunder' => ['diagnosisSekunder', ['Ya' => 15, 'Tidak' => 0]],
        'alatBantu' => ['alatBantu', ['Tidak Ada / Bed Rest' => 0, 'Tongkat / Alat Penopang / Walker' => 15, 'Furnitur' => 30]],
        'heparin' => ['terapiIV', ['Ya' => 20, 'Tidak' => 0]],
        'gayaBerjalan' => ['gayaBerjalan', ['Normal / Tirah Baring / Tidak Bergerak' => 0, 'Lemah' => 10, 'Terganggu' => 20]],
        'kesadaran' => ['statusMental', ['Baik' => 0, 'Lupa / Pelupa' => 15]],
    ];

    /** Humpty Dumpty: legacy key => [key baru, skor => teks opsi form baru] (null = tak ada padanan). */
    private const HD_PETA = [
        'umur' => ['umur', [4 => '< 3 tahun', 3 => '3-7 tahun', 2 => '7-13 tahun', 1 => '13-18 tahun']],
        'sex' => ['jenisKelamin', [2 => 'Laki-laki', 1 => 'Perempuan']],
        'diagnosa' => ['diagnosis', [4 => 'Diagnosis neurologis atau perkembangan', 3 => 'Diagnosis ortopedi', 2 => 'Diagnosis lainnya', 1 => 'Tidak ada diagnosis khusus']],
        'gangguanKognitif' => ['gangguanKognitif', [3 => 'Gangguan kognitif berat', 2 => 'Gangguan kognitif sedang', 1 => 'Gangguan kognitif ringan']],
        'faktorLingkungan' => ['faktorLingkungan', [3 => 'Lingkungan berisiko tinggi', 2 => 'Lingkungan berisiko sedang', 1 => 'Lingkungan berisiko rendah']],
        'penggunaanObat' => ['responObat', [3 => 'Efek samping obat yang meningkatkan risiko jatuh', 2 => 'Efek samping obat ringan', 1 => 'Tidak ada efek samping obat']],
        'responTerhadapOperasi' => [null, []],
    ];

    /** Entri terakhir sebuah node list (nyeri/resikoJatuh/dekubitus/gizi); null bila kosong atau masih objek legacy. */
    public static function entriTerakhir(mixed $node): ?array
    {
        if (! is_array($node) || $node === [] || ! array_is_list($node)) {
            return null;
        }
        $akhir = end($node);

        return is_array($akhir) ? $akhir : null;
    }

    /** Jumlah entri nyata sebuah node list; objek legacy (boilerplate siklik-lite) dihitung 0. */
    public static function jumlahEntri(mixed $node): int
    {
        return is_array($node) && array_is_list($node) ? count($node) : 0;
    }

    /** Apakah node penilaian ini bentuk legacy (ada sub-objek assoc), bukan bentuk baru (semua list)? */
    public static function adalahLegacy(array $penilaian): bool
    {
        foreach (['resikoJatuh', 'nyeri', 'dekubitus', 'statusPediatrik', 'fisik', 'diagnosis'] as $k) {
            if (isset($penilaian[$k]) && is_array($penilaian[$k]) && $penilaian[$k] !== [] && ! array_is_list($penilaian[$k])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Konversi. $konteks: ['tgl' => 'd/m/Y H:i:s', 'petugas' => string].
     * Mengembalikan ['penilaian' => array baru, 'laporan' => [key => keterangan]].
     */
    public static function konversi(array $penilaian, array $konteks): array
    {
        $laporan = [];
        $baru = $penilaian;
        $tgl = $konteks['tgl'] ?? now()->format('d/m/Y H:i:s');
        $petugas = $konteks['petugas'] ?? 'Migrasi Data';

        // ── risiko jatuh
        $rj = $penilaian['resikoJatuh'] ?? [];
        if (is_array($rj) && $rj !== [] && ! array_is_list($rj)) {
            $entri = self::entriRisikoJatuh($rj, $tgl, $petugas, $laporan);
            $baru['resikoJatuh'] = $entri ? [$entri] : [];
            if ($entri) {
                $baru['resikoJatuhLegacy'] = $rj;
            }
            $laporan['resikoJatuh'] = $entri ? 'dikonversi ('.$entri['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetode'].' '.$entri['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'].' → '.$entri['resikoJatuh']['kategoriResiko'].')' : 'kosong → []';
        }

        // ── nyeri
        $ny = $penilaian['nyeri'] ?? [];
        if (is_array($ny) && $ny !== [] && ! array_is_list($ny)) {
            $entri = self::entriNyeri($ny, $tgl, $petugas);
            $baru['nyeri'] = $entri ? [$entri] : [];
            if ($entri) {
                $baru['nyeriLegacy'] = $ny;
            }
            $laporan['nyeri'] = $entri ? 'dikonversi (VAS '.$entri['nyeri']['nyeriMetode']['nyeriMetodeScore'].')' : 'kosong → []';
        }

        // ── skala lain: kosong → list kosong; berisi → biarkan, laporkan
        foreach (['dekubitus', 'statusPediatrik', 'fisik', 'diagnosis'] as $k) {
            $node = $penilaian[$k] ?? [];
            if (! is_array($node) || $node === [] || array_is_list($node)) {
                continue;
            }
            if (self::objekKosong($node)) {
                $baru[$k] = [];
                $laporan[$k] = 'kosong → []';
            } else {
                $laporan[$k] = 'BERISI, dibiarkan (perlu tinjauan manual)';
            }
        }

        $baru['migrasiPenilaian'] = ['versi' => self::VERSI, 'tgl' => now()->format('d/m/Y H:i:s'), 'sumber' => 'siklik-lite'];

        return ['penilaian' => $baru, 'laporan' => $laporan];
    }

    /** Rollback: pulihkan salinan legacy bila ada; node tanpa salinan (dulu kosong) dikembalikan ke [] apa adanya. */
    public static function rollback(array $penilaian): array
    {
        foreach (['resikoJatuh', 'nyeri'] as $k) {
            if (isset($penilaian[$k.'Legacy'])) {
                $penilaian[$k] = $penilaian[$k.'Legacy'];
                unset($penilaian[$k.'Legacy']);
            }
        }
        unset($penilaian['migrasiPenilaian']);

        return $penilaian;
    }

    public static function kategoriMorse(int $skor): string
    {
        return $skor >= 45 ? 'Tinggi' : ($skor >= 25 ? 'Sedang' : 'Rendah');
    }

    public static function kategoriHumptyDumpty(int $skor): string
    {
        return $skor >= 16 ? 'Tinggi' : ($skor >= 12 ? 'Sedang' : 'Rendah');
    }

    public static function jenisNyeriVas(int $skor): string
    {
        return match (true) {
            $skor === 0 => 'Tidak Nyeri',
            $skor <= 3 => 'Nyeri Ringan',
            $skor <= 6 => 'Nyeri Sedang',
            default => 'Nyeri Berat',
        };
    }

    // ------------------------------------------------------------------

    private static function entriRisikoJatuh(array $rj, string $tgl, string $petugas, array &$laporan): ?array
    {
        $morse = $rj['skalaMorse'] ?? [];
        $hd = $rj['skalaHumptyDumpty'] ?? [];
        $morseIsi = self::jumlahTerisi($morse, array_keys(self::MORSE_PETA));
        $hdIsi = self::jumlahTerisi($hd, array_keys(self::HD_PETA));
        $morseSkor = (int) ($morse['skalaMorseScore'] ?? 0);
        $hdSkor = (int) ($hd['skalaHumptyDumptyScore'] ?? 0);

        if ($morseIsi === 0 && $hdIsi === 0 && $morseSkor === 0 && $hdSkor === 0) {
            return null;
        }

        // Metode: skala yang itemnya lebih banyak terisi; seri → Morse (data lebih lengkap).
        $pakaiMorse = $morseIsi >= $hdIsi && ($morseIsi > 0 || $morseSkor > 0) || ($hdIsi === 0 && $hdSkor === 0);
        if ($pakaiMorse) {
            $data = [];
            $hitung = 0;
            foreach (self::MORSE_PETA as $lama => [$baruKey, $opsi]) {
                $teks = (string) ($morse[$lama] ?? '');
                if ($teks !== '') {
                    $data[$baruKey] = $teks;
                    $hitung += $opsi[$teks] ?? (int) ($morse[$lama.'Score'] ?? 0);
                }
            }
            $skor = $morseSkor > 0 ? $morseSkor : $hitung;
            if ($morseSkor > 0 && $hitung !== $morseSkor) {
                $laporan['resikoJatuhCatatan'] = "skor Morse legacy $morseSkor ≠ hitung ulang $hitung, dipakai legacy";
            }
            $metode = 'Skala Morse';
            $kategori = self::kategoriMorse($skor);
        } else {
            $data = [];
            foreach (self::HD_PETA as $lama => [$baruKey, $petaSkor]) {
                $skorItem = (int) ($hd[$lama.'Score'] ?? 0);
                if ($baruKey === null || $skorItem === 0) {
                    continue;
                }
                if (isset($petaSkor[$skorItem])) {
                    $data[$baruKey] = $petaSkor[$skorItem];
                } else {
                    $laporan['resikoJatuhCatatan'] = "item HD $lama skor $skorItem tak punya padanan form baru";
                }
            }
            $skor = $hdSkor;
            $metode = 'Humpty Dumpty';
            $kategori = self::kategoriHumptyDumpty($skor);
            if ($hdIsi > 0 && $morseIsi > 0) {
                $laporan['resikoJatuhCatatan'] = ($laporan['resikoJatuhCatatan'] ?? '').' kedua skala terisi, dipilih HD';
            }
        }

        return [
            'tglPenilaian' => $tgl,
            'petugasPenilai' => $petugas,
            'petugasPenilaiCode' => self::PETUGAS_CODE_MIGRASI,
            'resikoJatuh' => [
                'resikoJatuh' => 'Ya',
                'resikoJatuhMetode' => ['resikoJatuhMetode' => $metode, 'resikoJatuhMetodeScore' => $skor, 'dataResikoJatuh' => $data],
                'kategoriResiko' => $kategori,
                'rekomendasi' => '',
            ],
        ];
    }

    private static function entriNyeri(array $ny, string $tgl, string $petugas): ?array
    {
        $vas = trim((string) ($ny['vas']['vas'] ?? ''));
        $skala = trim((string) ($ny['skalaNyeri'] ?? ''));
        $skorTeks = $vas !== '' ? $vas : $skala;
        $adaKet = trim((string) ($ny['pencetus'] ?? '')) !== '' || trim((string) ($ny['durasi'] ?? '')) !== '' || trim((string) ($ny['lokasi'] ?? '')) !== '';
        if ($skorTeks === '' && ! $adaKet) {
            return null;
        }
        $skor = (int) $skorTeks;
        $metode = trim((string) ($ny['nyeriMetode'] ?? '')) ?: 'VAS';
        $dataNyeri = [];
        for ($i = 0; $i <= 10; $i++) {
            $dataNyeri[] = ['vas' => (string) $i, 'active' => $i === $skor];
        }

        return [
            'tglPenilaian' => $tgl,
            'petugasPenilai' => $petugas,
            'petugasPenilaiCode' => self::PETUGAS_CODE_MIGRASI,
            'nyeri' => [
                'nyeri' => 'Ya',
                'nyeriMetode' => ['nyeriMetode' => $metode, 'nyeriMetodeScore' => $skor, 'dataNyeri' => $metode === 'VAS' ? $dataNyeri : []],
                'nyeriKet' => self::jenisNyeriVas($skor),
                'pencetus' => (string) ($ny['pencetus'] ?? ''),
                'durasi' => (string) ($ny['durasi'] ?? ''),
                'lokasi' => (string) ($ny['lokasi'] ?? ''),
                'waktuNyeri' => '',
                'tingkatKesadaran' => '',
                'tingkatAktivitas' => '',
                'ketIntervensiFarmakologi' => '',
                'ketIntervensiNonFarmakologi' => '',
                'catatanTambahan' => '',
            ],
        ];
    }

    private static function jumlahTerisi(array $skala, array $keys): int
    {
        $n = 0;
        foreach ($keys as $k) {
            if (trim((string) ($skala[$k] ?? '')) !== '') {
                $n++;
            }
        }

        return $n;
    }

    /** Objek legacy dianggap kosong bila semua nilai skalar kosong/0 dan tidak ada sub-array berisi nilai (selain *Options/*Tab). */
    private static function objekKosong(array $node): bool
    {
        foreach ($node as $k => $v) {
            if (str_ends_with((string) $k, 'Options') || str_ends_with((string) $k, 'Tab')) {
                continue;
            }
            if (is_array($v)) {
                if (! self::objekKosong($v)) {
                    return false;
                }
                continue;
            }
            if ($v !== null && $v !== '' && $v !== 0 && $v !== '0' && $v !== false) {
                return false;
            }
        }

        return true;
    }
}
