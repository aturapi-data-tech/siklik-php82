<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Petugas penanggung jawab unit penunjang (Laboratorium / Radiologi) untuk
 * `ServiceRequest.performer` — field yang WAJIB ada (RuleNumber 10377).
 *
 * Menurut koleksi Postman resmi SATUSEHAT, performer adalah praktisi yang MENGERJAKAN
 * pemeriksaan, bukan dokter pengirim. Di siklik (klinik pratama) tidak ada poli
 * Laboratorium/Radiologi tersendiri — `skmst_polis` hanya berisi POLI UMUM dan POLI
 * GIGI (dicek 11/09/2026) — jadi konvensi sirus "dokter aktif pada poli unit itu"
 * TIDAK bisa diport apa adanya. Yang tersedia di siklik:
 *
 *   1. `dr_id` yang memang tercatat pada ordernya (`sktxn_checkuphdrs.dr_id` untuk lab);
 *   2. penunjukan manual lewat `config('satusehat.pj_lab_dr_id')` /
 *      `config('satusehat.pj_radiologi_dr_id')` (env `SATUSEHAT_PJ_LAB_DR_ID` / `..._PJ_RADIOLOGI_DR_ID`);
 *   3. NAMA petugas yang tersimpan sebagai teks bebas pada order radiologi
 *      (`sktxn_rjrads.dr_radiologi`), diterima HANYA bila cocok tepat satu dokter aktif —
 *      nama kembar berarti menebak orang, dan itu ditolak.
 *
 * Memulangkan array KOSONG bila tak satu pun jalur menghasilkan `dr_uuid`. Pemanggil
 * yang menerima array kosong sebaiknya TIDAK mengirim performer sama sekali:
 * `ServiceRequestTrait` lalu memakai dokter pengirim sebagai pengganti, sehingga
 * kiriman tetap jalan (walau nilainya belum akurat) alih-alih ditolak validator.
 *
 * Begitu ada master penunjukan PJ penunjang yang sesungguhnya, hanya berkas ini yang
 * perlu diubah.
 */
class PenanggungJawabPenunjang
{
    public const UNIT_LABORATORIUM = 'lab';
    public const UNIT_RADIOLOGI = 'radiologi';

    /** Cache per-request: satu kunci pencarian cukup ditanyakan sekali per permintaan. */
    private static array $cache = [];

    /**
     * Baris dokter PJ, atau null bila tak ada yang bisa dipastikan.
     *
     * @param  string       $unit           self::UNIT_LABORATORIUM | self::UNIT_RADIOLOGI
     * @param  string|null  $drId           dr_id yang tercatat pada order (kalau ada)
     * @param  string|null  $namaPetugas    nama teks bebas pada order (kalau ada)
     * @return object|null {dr_id, dr_name, dr_uuid}
     */
    public static function dokter(string $unit, ?string $drId = null, ?string $namaPetugas = null): ?object
    {
        // Whitelist unit: nilai di luar dugaan tidak boleh diam-diam jatuh ke cabang
        // config unit lain dan menyebut petugas dari unit yang salah.
        if (!in_array($unit, [self::UNIT_LABORATORIUM, self::UNIT_RADIOLOGI], true)) {
            return null;
        }

        $drId = trim((string) $drId);
        if ($drId !== '') {
            $dokter = self::cariPerDrId($drId);
            if ($dokter !== null) {
                return $dokter;
            }
        }

        $drIdConfig = trim((string) config('satusehat.pj_' . $unit . '_dr_id', ''));
        if ($drIdConfig !== '') {
            $dokter = self::cariPerDrId($drIdConfig);
            if ($dokter !== null) {
                return $dokter;
            }
        }

        $namaPetugas = trim((string) $namaPetugas);
        if ($namaPetugas !== '') {
            return self::cariPerNama($namaPetugas);
        }

        return null;
    }

    /**
     * Referensi FHIR Practitioner untuk dipakai sebagai ServiceRequest.performer.
     *
     * @return array{reference?: string, display?: string}  kosong = biarkan trait memakai requester
     */
    public static function practitionerRef(string $unit, ?string $drId = null, ?string $namaPetugas = null): array
    {
        $dokter = self::dokter($unit, $drId, $namaPetugas);
        $uuid = trim((string) ($dokter->dr_uuid ?? ''));

        if ($uuid === '') {
            return [];
        }

        return [
            'reference' => 'Practitioner/' . $uuid,
            'display' => (string) ($dokter->dr_name ?? ''),
        ];
    }

    /** Dokter ber-IHS untuk satu dr_id. null bila tak ada atau dr_uuid-nya kosong. */
    private static function cariPerDrId(string $drId): ?object
    {
        $kunci = 'id:' . $drId;

        if (!array_key_exists($kunci, self::$cache)) {
            self::$cache[$kunci] = DB::table('skmst_doctors')
                ->where('dr_id', $drId)
                // Oracle: '' identik NULL — jangan pakai <> '' (skill oracle-quirks §1).
                ->whereRaw('dr_uuid IS NOT NULL AND LENGTH(TRIM(dr_uuid)) > 0')
                ->first(['dr_id', 'dr_name', 'dr_uuid']);
        }

        return self::$cache[$kunci];
    }

    /**
     * Dokter ber-IHS untuk satu NAMA teks bebas — diterima hanya bila cocok TEPAT SATU
     * dokter aktif. Dua nama yang sama berarti menebak orang; lebih baik jatuh ke
     * dokter pengirim daripada menyebut petugas yang salah pada rekam nasional.
     */
    private static function cariPerNama(string $nama): ?object
    {
        $kunci = 'nama:' . mb_strtoupper($nama);

        if (!array_key_exists($kunci, self::$cache)) {
            $kandidatList = DB::table('skmst_doctors')
                ->whereRaw('UPPER(TRIM(dr_name)) = ?', [mb_strtoupper($nama)])
                ->where('active_status', '1')
                ->whereRaw('dr_uuid IS NOT NULL AND LENGTH(TRIM(dr_uuid)) > 0')
                ->get(['dr_id', 'dr_name', 'dr_uuid']);

            self::$cache[$kunci] = $kandidatList->count() === 1 ? $kandidatList->first() : null;
        }

        return self::$cache[$kunci];
    }
}
