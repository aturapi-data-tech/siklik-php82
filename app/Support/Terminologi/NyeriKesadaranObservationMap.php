<?php

namespace App\Support\Terminologi;

use App\Support\Options\NyeriOptions;

/**
 * Pemetaan Skala Nyeri & Tingkat Kesadaran ke Observation SATUSEHAT (RJ siklik).
 *
 * Sumber kode: koleksi Postman resmi Kemkes (Observation - NRS, Observation - BPS,
 * Observation - Total score NIPS, Observation - Tingkat Kesadaran). TIDAK ada kode
 * yang dikarang di sini — skala/nilai yang belum punya contoh resmi dibiarkan
 * kosong dan DILAPORKAN ke pengguna, bukan dikirim dengan kode tebakan. Salah kode
 * berarti kondisi klinis pasien tercatat sebagai konsep keliru di rekam medis
 * nasional; jauh lebih berbahaya daripada sekadar ditolak validator.
 *
 * BEDA DARI SIRUS — dua hal, keduanya sudah diperiksa ke data nyata siklik:
 *   1. Sumber kesadaran = `pemeriksaan.tandaVital.tingkatKesadaran`, BUKAN
 *      `screening.kesadaran` (siklik tidak punya node screening).
 *   2. Nilainya KODE BPJS PCare (kdSadar '01'..'04' = Compos mentis / Somnolence /
 *      Sopor / Coma) pada 200 dari 200 kunjungan terbaru — bukan teks AVPU yang
 *      masih terdaftar di `tingkatKesadaranOptions` form perawat. Keduanya
 *      didaftarkan di sini karena record lama bisa memuat teksnya.
 */
class NyeriKesadaranObservationMap
{
    /**
     * Kode per skala nyeri. Kunci = kode skala di NyeriOptions.
     *
     * 'nilai' menentukan bentuk pengiriman skor:
     *   'integer'  → valueInteger         (contoh resmi NRS)
     *   'quantity' → valueQuantity {score} (contoh resmi Total score NIPS)
     *
     * BELUM ADA CONTOH RESMI untuk VAS, FLACC, dan BPS — ketiganya tetap boleh
     * dipakai petugas di EMR, hanya tidak ikut terkirim, dan jumlahnya dimunculkan
     * di kartu serta disebut di toast supaya tidak hilang diam-diam.
     *
     * Catatan BPS: contoh resmi bernama "Observation - BPS" display-nya justru
     * Wong-Baker FACES (skala anak), BUKAN Behavioral Pain Scale yang dimaksud
     * dropdown siklik. Instrumennya berbeda sama sekali, jadi BPS siklik SENGAJA
     * tidak dipetakan ke sana — sirus memetakannya ke 'WBS' yang siklik tak punya.
     */
    private const SKALA = [
        'NRS' => [
            'system'  => 'http://snomed.info/sct',
            'code'    => '1172399009',
            'display' => 'Numeric rating scale score',
            'nilai'   => 'integer',
        ],
        'NIPS' => [
            'system'  => 'http://loinc.org',
            'code'    => '98012-8',
            'display' => 'Total score NIPS',
            'nilai'   => 'quantity',
        ],
    ];

    /** LOINC untuk tingkat kesadaran (contoh resmi "Observation - Tingkat Kesadaran"). */
    private const KESADARAN_CODE = [
        'system' => 'http://loinc.org',
        'code' => '67775-7',
        'display' => 'Level of responsiveness',
    ];

    /**
     * Label kode BPJS PCare kdSadar → teks yang dikirim.
     *
     * Diambil dari cache `ref_bpjs_table` kategori 'Kesadaran'
     * ([{"kdSadar":"01","nmSadar":"Compos mentis"}, ...]). Dipasang sebagai
     * konstanta supaya kelas terminologi tidak menyentuh basis data; bila BPJS
     * menambah kode, kartu tetap mengirim kodenya apa adanya sebagai teks.
     */
    private const LABEL_BPJS = [
        '01' => 'Compos mentis',
        '02' => 'Somnolence',
        '03' => 'Sopor',
        '04' => 'Coma',
    ];

    /**
     * Padanan SNOMED tiap nilai kesadaran.
     *
     * HANYA "Berespon Dengan Kata-Kata / Voice" yang punya padanan dari contoh
     * resmi (300202002 Response to voice = pasien merespons saat dipanggil) — dan
     * itu pun opsi TEKS lama; keempat kode BPJS belum satu pun punya padanan resmi.
     * Sisanya dikirim sebagai teks saja: CodeableConcept dengan `text` tanpa
     * `coding` itu sah di FHIR dan jujur. Begitu kodenya didapat dari Lampiran
     * Terminologi SATUSEHAT, cukup isi 'code' di sini — tidak ada tempat lain yang
     * perlu disentuh.
     */
    private const KESADARAN = [
        // Kode BPJS PCare — bentuk yang benar-benar tersimpan di siklik.
        'Compos mentis' => ['code' => null, 'display' => null],
        'Somnolence'    => ['code' => null, 'display' => null],
        'Sopor'         => ['code' => null, 'display' => null],
        'Coma'          => ['code' => null, 'display' => null],

        // Opsi teks AVPU yang masih terdaftar di form perawat (record lama).
        'Sadar Baik / Alert'                          => ['code' => null, 'display' => null],
        'Berespon Dengan Kata-Kata / Voice'           => ['code' => '300202002', 'display' => 'Response to voice'],
        'Hanya Beresponse Jika Dirangsang Nyeri / Pain' => ['code' => null, 'display' => null],
        'Pasien Tidak Sadar / Unresponsive'           => ['code' => null, 'display' => null],
        'Gelisah Atau Bingung'                        => ['code' => null, 'display' => null],
        'Acute Confusional States'                    => ['code' => null, 'display' => null],
    ];

    public static function surveyCategory(): array
    {
        return [[
            'coding' => [[
                'system'  => 'http://terminology.hl7.org/CodeSystem/observation-category',
                'code'    => 'survey',
                'display' => 'Survey',
            ]],
        ]];
    }

    public static function examCategory(): array
    {
        return [[
            'coding' => [[
                'system'  => 'http://terminology.hl7.org/CodeSystem/observation-category',
                'code'    => 'exam',
                'display' => 'Exam',
            ]],
        ]];
    }

    /** Skala yang dipakai petugas tapi belum bisa dikirim — untuk dilaporkan, bukan disembunyikan. */
    public static function skalaTanpaKode(): array
    {
        return array_values(array_diff(array_keys(NyeriOptions::SKALA), array_keys(self::SKALA)));
    }

    public static function skalaDidukung(string $kode): bool
    {
        return isset(self::SKALA[$kode]);
    }

    /**
     * Teks kesadaran yang dibaca manusia: kode BPJS diterjemahkan, teks lama
     * dibiarkan apa adanya. Kode asing dikembalikan apa adanya — lebih baik
     * mengirim '05' daripada menghilangkannya.
     */
    public static function labelKesadaran(?string $nilai): string
    {
        $nilai = trim((string) $nilai);

        return self::LABEL_BPJS[$nilai] ?? $nilai;
    }

    /**
     * Satu entri nyeri → 0..1 Observation.
     *
     * Kosong bila: pasien menjawab tidak nyeri, skala belum dipilih, skalanya
     * belum punya kode resmi, atau skornya bukan angka.
     */
    public static function nyeri(mixed $entri): array
    {
        $baku = NyeriOptions::normalisasiEntri($entri);
        if ($baku === []) {
            return [];
        }

        $node = $baku['nyeri'] ?? [];
        // Record lama menyimpan 'nyeri' sebagai string 'Ya'/'Tidak'; yang baru array.
        $adaNyeri = is_string($node['nyeri'] ?? null) ? $node['nyeri'] : ($baku['nyeri']['nyeri'] ?? '');
        if (is_string($adaNyeri) && $adaNyeri !== '' && strcasecmp(trim($adaNyeri), 'Ya') !== 0) {
            return [];
        }

        $kodeSkala = (string) ($node['nyeriMetode']['nyeriMetode'] ?? '');
        $skor = $node['nyeriMetode']['nyeriMetodeScore'] ?? null;

        if (!self::skalaDidukung($kodeSkala) || $skor === null || $skor === '' || !is_numeric($skor)) {
            return [];
        }

        $skala = self::SKALA[$kodeSkala];
        $observation = [
            'category' => self::surveyCategory(),
            'code'     => ['system' => $skala['system'], 'code' => $skala['code'], 'display' => $skala['display']],
        ];

        if ($skala['nilai'] === 'quantity') {
            $observation['valueQuantity'] = [
                'value'  => (float) $skor,
                'unit'   => '{score}',
                'system' => 'http://unitsofmeasure.org',
                'code'   => '{score}',
            ];
        } else {
            $observation['valueInteger'] = (int) $skor;
        }

        return [$observation];
    }

    /** Tingkat kesadaran → 0..1 Observation. Kosong bila belum diisi. */
    public static function kesadaran(?string $nilai): array
    {
        $label = self::labelKesadaran($nilai);
        if ($label === '') {
            return [];
        }

        $konsep = ['text' => $label];
        $padanan = self::KESADARAN[$label] ?? null;
        if ($padanan && $padanan['code'] !== null) {
            $konsep['system'] = 'http://snomed.info/sct';
            $konsep['code'] = $padanan['code'];
            $konsep['display'] = $padanan['display'];
        }

        return [[
            'category' => self::examCategory(),
            'code'     => self::KESADARAN_CODE,
            'valueCodeableConcept' => $konsep,
        ]];
    }
}
