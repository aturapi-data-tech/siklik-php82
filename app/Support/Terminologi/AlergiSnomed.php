<?php

namespace App\Support\Terminologi;

/**
 * Kode SNOMED alergi untuk anamnesa RJ (AllergyIntolerance SATUSEHAT).
 *
 * Diport dari sirus-php82 (app/Support/Terminologi/AlergiSnomed.php, commit b5049d88 →
 * a39c587c → 7a18501c). Semua kode diverifikasi sirus lewat terminology server
 * tx.fhir.org (CodeSystem/$lookup) — jangan menambah/mengubah dari hafalan.
 *
 * Node sumber siklik: `anamnesa.alergi.{adaAlergi, alergi, snomedCode, snomedDisplayEn,
 * snomedDisplayId}`. Node yang sama juga memuat alergi BPJS PCare
 * (`alergiMakan/alergiUdara/alergiObat` + `*Desc`) — kelas ini TIDAK MENYENTUHNYA:
 * itu kodifikasi BPJS yang berdiri sendiri, sedangkan di sini urusannya SNOMED/SATUSEHAT.
 *
 * KONSEP: "punya alergi atau tidak?" dan "alergi terhadap apa?" adalah DUA pertanyaan.
 * 716186003 "No known allergy" itu konsep SNOMED *situation*, BUKAN zat — ia ditolak
 * valueset `substance-code` yang dipakai LOV zat ("was not found in the value set"),
 * jadi kode itu TIDAK PERNAH bisa muncul di LOV. Karena itu jawabannya diambil lewat
 * radio Ya/Tidak dan kodenya dipasang di server (lihat terapkan()).
 *
 * RISIKO YANG DISADARI (keputusan sirus 2026-07-15, diikuti siklik):
 *   `adaAlergi` = 'Tidak' adalah PERNYATAAN KLINIS. Kalau petugas tak pernah menanyakan
 *   alergi lalu tetap menyimpan anamnesa, sistem melaporkan "pasien tidak punya alergi"
 *   ke SATUSEHAT padahal tak ada yang memastikannya. Mitigasi: default hanya diturunkan
 *   saat form DIBUKA (petugas melihat radionya & bisa mengubah), bukan disisipkan
 *   diam-diam saat simpan.
 */
class AlergiSnomed
{
    /** Tidak ada alergi (umum) — dipakai saat radio "Ada Alergi?" dijawab Tidak. */
    public const TIDAK_ADA = [
        'alergi' => 'Tidak ada alergi',
        'snomedCode' => '716186003',
        'snomedDisplayEn' => 'No known allergy',
        'snomedDisplayId' => 'Tidak ada alergi yang diketahui',
    ];

    /** Varian per kategori — belum dipakai UI, disiapkan bila nanti perlu dibedakan. */
    public const TIDAK_ADA_OBAT = [
        'alergi' => 'Tidak ada alergi obat',
        'snomedCode' => '409137002',
        'snomedDisplayEn' => 'No known history of drug allergy',
        'snomedDisplayId' => 'Tidak ada riwayat alergi obat',
    ];

    public const TIDAK_ADA_MAKANAN = [
        'alergi' => 'Tidak ada alergi makanan',
        'snomedCode' => '429625007',
        'snomedDisplayEn' => 'No known food allergy',
        'snomedDisplayId' => 'Tidak ada alergi makanan yang diketahui',
    ];

    public const TIDAK_ADA_LINGKUNGAN = [
        'alergi' => 'Tidak ada alergi lingkungan',
        'snomedCode' => '428607008',
        'snomedDisplayEn' => 'No known environmental allergy',
        'snomedDisplayId' => 'Tidak ada alergi lingkungan yang diketahui',
    ];

    /**
     * Ejaan "tidak ada alergi" yang beredar di data lama — dipakai menurunkan `adaAlergi`
     * pada record yang belum punya key itu, sehingga record lama aman TANPA migrasi.
     * Dicocokkan atas teks ternormalkan (huruf kecil, tanpa spasi & tanda baca).
     *
     * Daftar ini diambil dari DATA NYATA siklik (3.749 record RJ ber-node alergi), bukan
     * disalin mentah dari sirus — sebarannya memang beda:
     *   2.454 "-"  |  682 KOSONG  |  132 "dingin"  |  55 "taa"  |  14 "tidak ada"
     * Beda penting dgn sirus: di siklik alergi NYATA cukup banyak (dingin/telur/udang/
     * coklat/ibuprofen...), jadi jangan menganggap populasi ini "99% tidak ada".
     *
     * SENGAJA TIDAK DIMASUKKAN — teks yang berarti "tidak tahu", bukan "tidak ada":
     *   "?" (14 record) dan "tak" (12, ambigu/terpotong). Keduanya jatuh ke 'Ya' sehingga
     *   petugas MELIHAT teks janggal itu di layar saat anamnesa dibuka dan terpaksa
     *   menjawab eksplisit. Memetakannya ke 'Tidak' = mengarang pernyataan klinis.
     */
    private const TEKS_TIDAK_ADA = [
        'tidakada', 'tidakadaalergi', 'tidakadaalergiyangdiketahui',
        'tidaka', 'tidakda', 'tridakada', 'tdkada', 'tidak',
        'disangkal', 'nihil', 'none',
        'taa', // singkatan lazim "tidak ada alergi" — 55 record siklik
    ];

    /**
     * Apakah teks bebas ini BERBUNYI "tidak ada alergi"?
     *
     * ⚠️ Menangkap juga teks yang isinya HANYA tanda baca ("-", "--", ".", " - "): itu
     * bentuk terbanyak di siklik (2.454 dari 3.749 record). Di sirus, '-' terdaftar di
     * TEKS_TIDAK_ADA tapi entri itu MATI — norm() membuang tanda baca sehingga "-" jadi
     * string kosong dan tak pernah cocok dgn '-'. Akibatnya "-" ditafsir 'Ya' lalu
     * tercetak "-" lagi, persis keambiguan yang untukCetak() mau dihapus. Di sirus
     * dampaknya kecil, di siklik dua pertiga data. Karena itu norm kosong diperiksa
     * TERPISAH, bukan lewat daftar.
     */
    private static function berbunyiTidakAda(string $teks): bool
    {
        // "?" = TIDAK TAHU, bukan tidak ada. Tanpa penjagaan ini ia ikut terhisap jalur
        // "tanda baca saja" di bawah (norm('?') juga '') lalu jadi klaim "tidak ada alergi"
        // atas 14 record yang justru menandai ketidaktahuan. Dibiarkan 'Ya' supaya teks
        // janggalnya terlihat petugas & dijawab ulang.
        if (str_contains($teks, '?')) {
            return false;
        }

        $ternormalkan = self::norm($teks);

        return $ternormalkan === '' || in_array($ternormalkan, self::TEKS_TIDAK_ADA, true);
    }

    /**
     * Nama key node alergi RJ. Siklik hanya punya rawat jalan (tidak ada UGD/RI seperti
     * sirus), tapi peta key tetap dipertahankan supaya logikanya satu sumber dan mudah
     * dipinjam kalau kelak ada modul lain.
     */
    private const KEYS_RJ = [
        'ada' => 'adaAlergi', 'teks' => 'alergi',
        'code' => 'snomedCode', 'en' => 'snomedDisplayEn', 'id' => 'snomedDisplayId',
    ];

    /**
     * Apakah kode ini pernyataan "tidak ada alergi" (bukan zat penyebab)?
     *
     * Dipakai sender SATUSEHAT untuk memutuskan `type`/`criticality` — keduanya atribut
     * alergi yang ADA, jadi dihilangkan pada pernyataan "no known allergy".
     * `category` TIDAK bisa ikut dihilangkan: SATUSEHAT menolaknya dengan
     * "Element not found: AllergyIntolerance.category (RuleNumber: 10075)" —
     * lihat kategoriFhir().
     */
    public static function adalahTidakAdaAlergi(?string $kode): bool
    {
        $kodeBersih = trim((string) $kode);
        if ($kodeBersih === '') {
            return false;
        }

        return in_array($kodeBersih, [
            self::TIDAK_ADA['snomedCode'],
            self::TIDAK_ADA_OBAT['snomedCode'],
            self::TIDAK_ADA_MAKANAN['snomedCode'],
            self::TIDAK_ADA_LINGKUNGAN['snomedCode'],
        ], true);
    }

    /**
     * Kategori FHIR AllergyIntolerance (food | medication | environment | biologic).
     *
     * SATUSEHAT MEWAJIBKAN elemen ini — termasuk pada pernyataan "tidak ada alergi",
     * walau di sana ia janggal. Ditolak "Element not found: AllergyIntolerance.category
     * (RuleNumber: 10075)" kalau dikosongkan, jadi tidak ada pilihan selain mengisinya.
     *
     * Kode "tidak ada alergi" yang SPESIFIK dipetakan apa adanya; sisanya (termasuk
     * 716186003 yang umum dan semua zat penyebab) jatuh ke 'medication' karena kolom
     * alergi di anamnesa siklik dipakai untuk alergi obat. Kalau kelak alergi
     * makanan/lingkungan mau dibedakan, sumber datanya harus MENYIMPAN kategorinya
     * dulu — jangan ditebak dari teks bebas.
     */
    public static function kategoriFhir(?string $kode): string
    {
        return match (trim((string) $kode)) {
            self::TIDAK_ADA_MAKANAN['snomedCode'] => 'food',
            self::TIDAK_ADA_LINGKUNGAN['snomedCode'] => 'environment',
            default => 'medication',
        };
    }

    /**
     * Apakah TEKS alergi berbunyi "tidak ada"? Hanya untuk keterangan di layar —
     * tidak boleh dipakai memutuskan isi payload.
     *
     * Masih dipakai kartu ⚡kirim-allergy untuk record LAMA yang belum pernah dibuka
     * ulang di anamnesa (teks "tidak ada" tapi snomedCode kosong): kartu menjelaskan
     * sebab penolakannya di muka, bukan mengarang kodenya.
     */
    public static function adalahTeksTidakAda(?string $teks): bool
    {
        $teksBersih = trim((string) $teks);
        if ($teksBersih === '') {
            return false;
        }

        return self::berbunyiTidakAda($teksBersih);
    }

    /**
     * Seragamkan node alergi RJ (`anamnesa.alergi`) + turunkan radio `adaAlergi`.
     *
     * @param  array $node  node anamnesa.alergi (key BPJS di dalamnya dibiarkan utuh)
     */
    public static function normalisasi(array $node): array
    {
        return self::terapkan($node, self::KEYS_RJ);
    }

    /**
     * Inti logika. Dipanggil saat anamnesa DIBUKA & saat radio "Ada Alergi?" diubah.
     *
     *   - Sudah punya `adaAlergi` -> hormati apa adanya (jawaban petugas).
     *     ⚠️ Karena itu struktur DEFAULT anamnesa TIDAK BOLEH preset `adaAlergi => 'Tidak'`:
     *     nilai itu tak bisa dibedakan dari jawaban asli, sehingga akan MENGHAPUS alergi
     *     yang baru di-prefill dari master pasien (mis. "allopurinol" jadi "Tidak ada
     *     alergi"). Biarkan kosong — biar diturunkan dari teks.
     *   - Belum punya (record lama / pasien baru) -> turunkan dari teks:
     *       teks kosong / salah satu ejaan "tidak ada" -> 'Tidak'
     *       teks lain (mis. "allopurinol")             -> 'Ya'
     *   - 'Tidak' -> teks & kode dipaksa ke TIDAK_ADA (716186003), kode zat dibuang.
     *   - 'Ya'    -> teks & kode zat dibiarkan; kalau teksnya masih berbunyi "tidak ada",
     *                dikosongkan supaya petugas mengisi zat yang sebenarnya.
     *
     * Key lain di node (alergi BPJS PCare) selalu dibawa utuh lewat spread.
     *
     * @param  array<string,string> $petaKey  peta nama key modul terkait
     */
    private static function terapkan(array $node, array $petaKey): array
    {
        $teks = trim((string) ($node[$petaKey['teks']] ?? ''));
        $ada = trim((string) ($node[$petaKey['ada']] ?? ''));

        if ($ada !== 'Ya' && $ada !== 'Tidak') {
            $ada = self::berbunyiTidakAda($teks) ? 'Tidak' : 'Ya';
        }

        if ($ada === 'Tidak') {
            return [...$node, ...self::sebagai(self::TIDAK_ADA, $petaKey), $petaKey['ada'] => 'Tidak'];
        }

        // 'Ya' tapi teksnya masih "tidak ada" (mis. petugas baru mengubah radio) -> kosongkan
        // supaya tak ada kontradiksi "ada alergi = tidak ada alergi".
        if (self::berbunyiTidakAda($teks)) {
            return [
                ...$node,
                $petaKey['ada'] => 'Ya',
                $petaKey['teks'] => '',
                $petaKey['code'] => '',
                $petaKey['en'] => '',
                $petaKey['id'] => '',
            ];
        }

        return [...$node, $petaKey['ada'] => 'Ya'];
    }

    /**
     * Teks alergi untuk DISPLAY / CETAK (read-only) — RJ.
     *
     * Cetakan membedakan keadaan; sebelumnya semuanya jadi "-" sehingga pembaca tak bisa
     * tahu pasien memang tak punya alergi ATAU perawat lupa mengisi:
     *   1. dikaji, tidak ada alergi     -> "Tidak ada alergi"
     *   2. dikaji, ada alergi, dirinci  -> teksnya
     *   3. dikaji "Ya" tapi tak dirinci -> "Ada (belum dirinci)"
     *   4. record lama, teks terisi     -> teksnya APA ADANYA (JANGAN ditafsir)
     *   5. record lama, KOSONG          -> "Tidak ada alergi"
     *
     * Keadaan 5 = keputusan user (sirus 2026-07-15) atas dasar KONVENSI LAMA: di sistem
     * lama alergi kosong memang DIPERSEPSIKAN "tidak ada alergi" — perawat sudah bertanya
     * lalu mengosongkannya. Jadi menulis "Belum dikaji" justru salah ke arah sebaliknya
     * (seolah tak pernah ditanyakan).
     *
     * ⚠️ Risiko yang disadari: record yang benar-benar terlewat akan tercetak "Tidak ada
     * alergi" — klaim yang tak pernah dibuat siapa pun. Sinyal yang BERLAWANAN dengan
     * konvensi itu: dari 397 record RJ sirus, 294 MENGETIK "tidak ada"/"disangkal" secara
     * eksplisit sementara 90 dibiarkan kosong — kalau kosong sudah berarti "tidak ada",
     * 294 orang itu tak perlu repot mengetik.
     *
     * CARA MENGUBAH ke "Belum dikaji": ganti `self::TIDAK_ADA['alergi']` pada baris
     * `return` TERAKHIR teksCetak() (baris berkomentar "Kosong -> ... konvensi lama")
     * menjadi `'Belum dikaji'`. SATU baris, tak ada tempat lain. Keadaan 1–3 jangan
     * diikutkan: ketiganya jawaban eksplisit petugas, bukan tafsiran.
     *
     * Masalah ini hilang sendiri ke depan: record baru selalu punya `adaAlergi` Ya/Tidak.
     */
    public static function untukCetak(array $node): string
    {
        return self::teksCetak($node, self::KEYS_RJ);
    }

    /** @param array<string,string> $petaKey */
    private static function teksCetak(array $node, array $petaKey): string
    {
        $teks = trim((string) ($node[$petaKey['teks']] ?? ''));
        $ada = trim((string) ($node[$petaKey['ada']] ?? ''));

        if ($ada === 'Tidak') {
            return self::TIDAK_ADA['alergi'];
        }

        if ($ada === 'Ya') {
            return $teks !== '' ? $teks : 'Ada (belum dirinci)';
        }

        // Record lama (belum ada jawaban Ya/Tidak). Teks lama ditampilkan APA ADANYA —
        // jangan dinormalkan: cetakan harus memperlihatkan yang benar-benar dicatat waktu
        // itu, bukan tafsiran kita. Kosong -> "Tidak ada alergi" mengikuti konvensi lama
        // (lihat keadaan 5 di docblock untukCetak()).
        return $teks !== '' ? $teks : self::TIDAK_ADA['alergi'];
    }

    /**
     * Petakan konstanta (berkey RJ) ke nama key modul tujuan.
     *
     * @param  array<string,string> $petaKey
     * @return array<string,string>
     */
    private static function sebagai(array $preset, array $petaKey): array
    {
        return [
            $petaKey['teks'] => $preset['alergi'],
            $petaKey['code'] => $preset['snomedCode'],
            $petaKey['en'] => $preset['snomedDisplayEn'],
            $petaKey['id'] => $preset['snomedDisplayId'],
        ];
    }

    /** Normalkan teks bebas: huruf kecil, buang spasi & tanda baca. */
    private static function norm(string $teks): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower(trim($teks))) ?? '';
    }
}
