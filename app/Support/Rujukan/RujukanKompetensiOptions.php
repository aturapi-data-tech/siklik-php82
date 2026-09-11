<?php

namespace App\Support\Rujukan;

/**
 * SUMBER TUNGGAL terminologi Rujukan Berbasis Kompetensi Layanan (SRBK) jalur FKTP.
 *
 * Dipakai bersama panel EMR Rawat Jalan dan cetak Surat Rujukan — jangan menyalin
 * daftar/aturan di bawah ke salah satu layar.
 *
 * Lingkupnya sengaja SEMPIT dibanding sirus (FKRTL): klinik pratama hanya punya
 * jalur RAWAT JALAN lewat gateway BPJS. Tidak ada kuesioner gawat darurat (Q100),
 * tidak ada Kelompok Layanan TK000562, dan tidak ada bundle FHIR yang kita kirim
 * sendiri — semua itu urusan rumah sakit.
 *
 * ACUAN: grup piloting SRBK (Apr–Sep 2026) + Skenario UAT Uji Coba SRBK (FKTP) v1.0.
 * Rinciannya di docs/rujukan-kompetensi.md.
 */
final class RujukanKompetensiOptions
{
    /** kdStatusPulang PCare untuk "Dirujuk" — penanda bahwa kunjungan harus lewat Sisrute/postKunjungan. */
    public const STATUS_PULANG_RUJUK = '4';

    /**
     * Tiga kriteria rujukan. Kunci = jenis internal; nilai = label layar.
     *
     * BPJS/SATUSEHAT mengembalikan ketiganya beserta `linkId` yang DINAMIS per
     * diagnosa, jadi daftar ini BUKAN sumber linkId — hanya sumber label, urutan,
     * dan aturan per jenis. linkId selalu diambil dari response GetKriteriaRujukan.
     */
    public const KRITERIA = [
        'terapi' => 'Terapi',
        'tindakan' => 'Tindakan Medis',
        'upaya' => 'Upaya Diagnosis',
    ];

    /** Penjelasan singkat tiap kriteria untuk petugas (ditampilkan di bawah pilihan). */
    public const PETUNJUK_KRITERIA = [
        'terapi' => 'Pasien dirujuk untuk menjalani terapi/pengobatan yang tidak tersedia di klinik.',
        'tindakan' => 'Pasien dirujuk untuk tindakan medis tertentu — wajib memilih kode ICD-9-CM yang valid.',
        'upaya' => 'Pasien dirujuk untuk upaya penegakan diagnosis (pemeriksaan penunjang lanjutan).',
    ];

    /** Aturan paling sering dilanggar; tampilkan apa adanya di panel. */
    public const PETUNJUK_UMUM = 'Pilih TEPAT SATU kriteria. Mengisi dua atau tiga kriteria akan ditolak SATUSEHAT ("hanya boleh mengisi salah satu").';

    /** Format tanggal yang diminta gateway Sisrute pada estimasiRujuk & tglEstRujuk. */
    public const FORMAT_TANGGAL_BPJS = 'd-m-Y';

    /** Format tanggal yang dipakai node JSON internal (CLOB) & tampilan. */
    public const FORMAT_TANGGAL_TAMPIL = 'd/m/Y';

    /** Kriteria yang mewajibkan kode tindakan ICD-9-CM pada answer.valueString. */
    public const KRITERIA_WAJIB_ICD9 = 'tindakan';

    /**
     * Menebak jenis kriteria dari teks yang dikirim BPJS.
     *
     * Teksnya tidak konsisten antar rilis ("Terapi" pernah muncul sebagai "Terapy"
     * dan "Terapy/Pengobatan"), jadi pencocokan dilakukan longgar — jangan
     * membandingkan string persis di panel.
     */
    public static function jenisKriteria(string $teks): string
    {
        $teksKecil = mb_strtolower(trim($teks));

        if ($teksKecil === '') {
            return '';
        }
        if (str_contains($teksKecil, 'tindakan')) {
            return 'tindakan';
        }
        if (str_contains($teksKecil, 'upaya') || str_contains($teksKecil, 'diagnosis')) {
            return 'upaya';
        }
        if (str_starts_with($teksKecil, 'terap')) {
            return 'terapi';
        }

        return '';
    }

    /** Kriteria ini menuntut kode ICD-9-CM? */
    public static function butuhIcd9(string $teks): bool
    {
        return self::jenisKriteria($teks) === self::KRITERIA_WAJIB_ICD9;
    }

    /**
     * Menyusun blok `kriteriaRujukan` yang dikirim ke GetFaskesRujukan maupun
     * postKunjungan — SATU item saja, sesuai validasi SATUSEHAT sejak 3 Jul 2026.
     *
     * Dipusatkan di sini supaya aturan "tepat satu" dan pemilihan
     * valueBoolean vs valueString tidak ditulis ulang di dua tempat.
     *
     * @param array  $kriteria satu elemen kriteriaRujukan dari response GetKriteriaRujukan
     *                         (minimal punya linkId & text)
     * @param string $icd9     kode ICD-9-CM; hanya dipakai bila kriterianya Tindakan Medis
     */
    public static function bangunKriteriaRujukan(array $kriteria, string $icd9 = ''): array
    {
        $linkId = (string) ($kriteria['linkId'] ?? '');
        $teks = (string) ($kriteria['text'] ?? '');

        $jawaban = self::butuhIcd9($teks)
            ? ['valueString' => trim($icd9)]
            : ['valueBoolean' => true];

        return [
            'item' => [[
                'linkId' => $linkId,
                'text' => $teks,
                'answer' => [$jawaban],
            ]],
        ];
    }
}
