<?php

namespace App\Support\Terminologi;

/**
 * Pemetaan telaah resep siklik -> QuestionnaireResponse Q0007 SATUSEHAT.
 *
 * Kuesioner baku: https://fhir.kemkes.go.id/Questionnaire/Q0007
 * Bentuk item MENGIKUTI PERSIS contoh resmi Postman V30062026 — grup 2, grup 3,
 * dan butir 4 BERSARANG di dalam grup 1, bukan bersaudara. Terlihat aneh, tapi
 * validator Kemkes itu kotak hitam: menyimpang dari contoh resmi berarti menebak.
 *
 * ================= PERBEDAAN SIKLIK vs SIRUS — WAJIB DIBACA =================
 * Telaah resep siklik hanya punya SEPULUH butir (defaultTelaahResep di
 * ⚡antrian-apotek-rj-actions), sirus lima belas. Lima pertanyaan Q0007 karena itu
 * TIDAK PUNYA sumber data di siklik dan SENGAJA TIDAK DIKIRIM — bukan dijawab
 * "Sesuai":
 *     1.2 identitas & paraf dokter · 1.3 tanggal resep · 1.4 ruangan asal resep
 *     2.3 stabilitas obat          · 3.1 ketepatan indikasi/dosis/waktu
 * Mengirimnya sebagai "Sesuai" berarti SIMRS mengarang jawaban atas pertanyaan
 * yang tak pernah diajukan ke apoteker, lalu menuliskannya ke rekam medis nasional.
 *
 * Sebaliknya siklik punya satu butir yang TIDAK ADA padanannya di Q0007:
 * `kejelasanTulisanResep`. Ia tidak ikut terkirim, dan kartu Kirim menyebutkannya
 * (lihat butirTanpaPadanan()) supaya kehilangan itu terlihat, bukan senyap.
 * ===========================================================================
 *
 * SATU KODE MASIH KOSONG: lihat TIDAK_SESUAI di bawah.
 */
class TelaahResepQ0007
{
    public const CANONICAL = 'https://fhir.kemkes.go.id/Questionnaire/Q0007';

    private const SISTEM = 'http://terminology.kemkes.go.id/CodeSystem/clinical-term';

    /** Jawaban "sudah sesuai" — satu-satunya kode Q0007 yang ada di contoh resmi. */
    private const SESUAI = ['system' => self::SISTEM, 'code' => 'OV000052', 'display' => 'Sesuai'];

    /**
     * Jawaban "TIDAK sesuai" — KODENYA BELUM DIKETAHUI.
     *
     * Seluruh koleksi Postman resmi hanya memuat tiga kode clinical-term
     * (OC000034, OI000020, OV000052); tidak ada padanan untuk "Tidak Sesuai".
     * Berbeda dari CodeableConcept, tipe `Coding` TIDAK punya field `text`, jadi
     * tidak bisa diakali dengan mengirim teksnya saja.
     *
     * CARA MENGISI: ganti null di bawah dengan
     *     ['system' => self::SISTEM, 'code' => '<KODE>', 'display' => 'Tidak Sesuai']
     * Tidak ada tempat lain yang perlu disentuh — penjagaan butirTanpaKode()
     * otomatis berhenti menolak begitu konstanta ini terisi.
     *
     * Selama masih null, telaah yang memuat jawaban "Tidak" pada butir ber-valueCoding
     * DITOLAK KIRIM. Sengaja: melewati butir yang dijawab "Tidak" berarti telaah
     * bermasalah terkirim TANPA masalahnya — rekam medis berbohong lewat kelalaian,
     * justru pada kasus yang paling penting.
     */
    private const TIDAK_SESUAI = null;

    /**
     * Butir ber-valueCoding yang PUNYA sumber data di siklik: [linkId => [teks, field]].
     * 'tepatRute' + 'tepatWaktu' sama-sama memberi makan linkId 2.4 (aturan & cara
     * penggunaan) — dua field kita, satu pertanyaan Kemkes; keduanya harus 'Ya'.
     */
    private const PILIHAN = [
        '1.1' => ['Apakah nama, umur, jenis kelamin, berat badan dan tinggi badan pasien sudah sesuai?', ['bbPasienAnak']],
        '2.1' => ['Apakah nama obat, bentuk dan kekuatan sediaan sudah sesuai?', ['tepatObat']],
        '2.2' => ['Apakah dosis dan jumlah obat sudah sesuai?', ['tepatDosis']],
        '2.4' => ['Apakah aturan dan cara penggunaan obat sudah sesuai?', ['tepatRute', 'tepatWaktu']],
    ];

    /**
     * Butir ber-valueBoolean: [linkId => [teks, field]].
     * Di sini 'Ya' berarti MASALAH ADA -> true. Tidak ada persoalan kode: boolean
     * tidak butuh terminologi, jadi keempat butir ini selalu bisa dikirim.
     */
    private const BOOLEAN = [
        '3.2' => ['Apakah terdapat duplikasi pengobatan?', 'duplikasi'],
        '3.3' => ['Apakah terdapat alergi dan reaksi obat yang tidak diinginkan?', 'alergi'],
        '3.4' => ['Apakah terdapat kontraindikasi pengobatan?', 'kontraIndikasiLain'],
        '3.5' => ['Apakah terdapat dampak interaksi obat?', 'interaksiObat'],
    ];

    /**
     * Butir telaah siklik yang TIDAK punya linkId Q0007 — ikut dinilai apoteker
     * tapi tak bisa dikirim. Didaftar eksplisit supaya kehilangannya dilaporkan.
     */
    private const TANPA_PADANAN = ['kejelasanTulisanResep'];

    /**
     * Pertanyaan Q0007 yang TIDAK punya sumber data di siklik. Hanya untuk
     * dilaporkan di kartu & dokumentasi — tidak pernah masuk payload.
     */
    public const LINKID_TAK_TERISI = ['1.2', '1.3', '1.4', '2.3', '3.1'];

    /** Label untuk pesan penolakan — supaya petugas tahu butir mana yang menghalangi. */
    private const LABEL = [
        'bbPasienAnak' => 'BB / Identitas Pasien',
        'tepatObat' => 'Tepat Obat',
        'tepatDosis' => 'Tepat Dosis',
        'tepatRute' => 'Tepat Rute',
        'tepatWaktu' => 'Tepat Waktu',
        'duplikasi' => 'Duplikasi Obat',
        'alergi' => 'Riwayat Alergi',
        'kontraIndikasiLain' => 'Kontra Indikasi Lain',
        'interaksiObat' => 'Interaksi Obat',
        'kejelasanTulisanResep' => 'Kejelasan Tulisan Resep',
    ];

    /**
     * Butir yang TIDAK ADA nilainya di telaah — penghalang kirim.
     *
     * Tanpa penjagaan ini, butir kosong lolos sebagai "Sesuai" (valueCoding) atau
     * "tidak ada masalah" (valueBoolean): SIMRS mengarang jawaban atas pertanyaan
     * yang tidak pernah diajukan ke apoteker. Jauh lebih buruk daripada tidak mengirim.
     *
     * Hanya butir yang PUNYA padanan Q0007 yang diperiksa — lima pertanyaan yang
     * tak punya sumber data di siklik memang tidak dikirim sama sekali, jadi bukan
     * "belum dijawab".
     *
     * @return array<int, string> label butir; kosong = semua terjawab
     */
    public static function butirBelumDijawab(array $telaah): array
    {
        $kosong = [];

        foreach (self::PILIHAN as [$teks, $fieldList]) {
            foreach ($fieldList as $field) {
                if (self::nilai($telaah, $field) === '') {
                    $kosong[] = self::LABEL[$field] ?? $field;
                }
            }
        }

        foreach (self::BOOLEAN as [$teks, $field]) {
            if (self::nilai($telaah, $field) === '') {
                $kosong[] = self::LABEL[$field] ?? $field;
            }
        }

        return array_values(array_unique($kosong));
    }

    /**
     * Butir telaah yang dinilai apoteker tapi tak punya tempat di Q0007 — dilaporkan
     * di kartu supaya petugas tahu apa yang TIDAK ikut berangkat.
     *
     * @return array<int, string> label butir yang benar-benar ada di telaah ini
     */
    public static function butirTanpaPadanan(array $telaah): array
    {
        $hilang = [];
        foreach (self::TANPA_PADANAN as $field) {
            if (self::nilai($telaah, $field) !== '') {
                $hilang[] = self::LABEL[$field] ?? $field;
            }
        }

        return $hilang;
    }

    public static function kodeTidakSesuaiTersedia(): bool
    {
        return self::TIDAK_SESUAI !== null;
    }

    /**
     * Butir telaah yang dijawab "Tidak" tapi belum punya kode — penghalang kirim.
     *
     * @return array<int, string> label butir; kosong = tidak ada penghalang
     */
    public static function butirTanpaKode(array $telaah): array
    {
        if (self::kodeTidakSesuaiTersedia()) {
            return [];
        }

        $penghalang = [];
        foreach (self::PILIHAN as [$teks, $fieldList]) {
            foreach ($fieldList as $field) {
                if (self::nilai($telaah, $field) === 'Tidak') {
                    $penghalang[] = self::LABEL[$field] ?? $field;
                }
            }
        }

        return array_values(array_unique($penghalang));
    }

    /**
     * Pohon item Q0007 — bersarang persis seperti contoh resmi.
     *
     * @param  array       $telaah              node telaahResep dari JSON kunjungan
     * @param  string|null $medicationRequestId id MedicationRequest yang dikaji (butir 4)
     */
    public static function item(array $telaah, ?string $medicationRequestId = null): array
    {
        $grup3 = [];
        foreach (self::PILIHAN as $linkId => [$teks, $fieldList]) {
            if (!str_starts_with($linkId, '3.')) {
                continue;
            }
            $grup3[] = self::butirPilihan($linkId, $teks, $telaah, $fieldList);
        }
        foreach (self::BOOLEAN as $linkId => [$teks, $field]) {
            $grup3[] = [
                'linkId' => $linkId,
                'text'   => $teks,
                'answer' => [['valueBoolean' => self::nilai($telaah, $field) === 'Ya']],
            ];
        }

        $grup2 = [];
        foreach (self::PILIHAN as $linkId => [$teks, $fieldList]) {
            if (!str_starts_with($linkId, '2.')) {
                continue;
            }
            $grup2[] = self::butirPilihan($linkId, $teks, $telaah, $fieldList);
        }

        $grup1 = [];
        foreach (self::PILIHAN as $linkId => [$teks, $fieldList]) {
            if (!str_starts_with($linkId, '1.')) {
                continue;
            }
            $grup1[] = self::butirPilihan($linkId, $teks, $telaah, $fieldList);
        }

        // Grup hanya disertakan bila ada isinya — elemen kosong ditolak validator,
        // dan grup tanpa jawaban tak memberi tahu apa pun.
        if ($grup2 !== []) {
            $grup1[] = ['linkId' => '2', 'text' => 'Persyaratan Farmasetik', 'item' => $grup2];
        }
        if ($grup3 !== []) {
            $grup1[] = ['linkId' => '3', 'text' => 'Persyaratan Klinis', 'item' => $grup3];
        }

        // Butir 4 menunjuk resep yang dikaji; hanya disertakan bila MedicationRequest
        // memang sudah terbit di SATUSEHAT — reference ke resource yang tidak ada
        // akan ditolak validator.
        if (!empty($medicationRequestId)) {
            $grup1[] = [
                'linkId' => '4',
                'text'   => 'Resep yang dilakukan pengkajian resep',
                'answer' => [['valueReference' => ['reference' => 'MedicationRequest/' . $medicationRequestId]]],
            ];
        }

        return [[
            'linkId' => '1',
            'text'   => 'Persyaratan Administrasi',
            'item'   => $grup1,
        ]];
    }

    /**
     * Satu butir ber-valueCoding. Semua field pemberi makannya harus 'Ya' supaya
     * dijawab Sesuai — pada 2.4 satu saja yang 'Tidak' membuat jawabannya tidak sesuai.
     */
    private static function butirPilihan(string $linkId, string $teks, array $telaah, array $fieldList): array
    {
        $sesuai = true;
        foreach ($fieldList as $field) {
            if (self::nilai($telaah, $field) === 'Tidak') {
                $sesuai = false;
            }
        }

        $coding = $sesuai ? self::SESUAI : self::TIDAK_SESUAI;

        return [
            'linkId' => $linkId,
            'text'   => $teks,
            'answer' => [['valueCoding' => $coding]],
        ];
    }

    /** Nilai satu butir telaah; bentuknya ['<field>' => 'Ya'|'Tidak', 'desc' => '...']. */
    private static function nilai(array $telaah, string $field): string
    {
        return (string) ($telaah[$field][$field] ?? '');
    }
}
