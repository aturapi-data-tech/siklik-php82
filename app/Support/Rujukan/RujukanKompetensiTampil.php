<?php

namespace App\Support\Rujukan;

/**
 * Perapian satu baris kandidat faskes rujukan untuk DITAMPILKAN & DICETAK.
 *
 * Dipisah dari trait dan dari panel karena dipakai tiga tempat: tabel kandidat di
 * EMR Rawat Jalan, layar pemantauan /rujukan/keluar, dan cetak Surat Rujukan.
 * Menyalin logikanya berarti satuan & ambangnya bisa berbeda diam-diam.
 */
final class RujukanKompetensiTampil
{
    /** Setengah keliling bumi — jarak di atas ini mustahil untuk rujukan pasien. */
    private const BATAS_KM = 20015.0;

    /** Seminggu perjalanan; di atas ini jelas bukan estimasi tempuh. */
    private const BATAS_MENIT = 10080.0;

    /**
     * Meratakan satu baris mentah response GetFaskesRujukan menjadi bentuk baku.
     *
     * Kunci yang tak dimiliki sumber dikembalikan sebagai string kosong (bukan absen)
     * supaya Blade tidak perlu `??` di mana-mana.
     *
     * PENTING: `orgId` dikembalikan TANPA prefix "Organization/". Response BPJS
     * mengirimnya berprefix, sedangkan payload postKunjungan
     * (kdppkSatuSehatTujuanRujukan) harus angkanya saja.
     *
     * @return array{orgId:string, kdppk:string, nama:string, strata:string, kelas:string,
     *               jarak:string, waktu:string, jadwal:string, jmlRujuk:string,
     *               kapasitas:string, persentase:string, alamat:string, kota:string,
     *               telp:string, beban:string}
     */
    public static function kandidatBaris(array $raw): array
    {
        $kdppk = trim((string) ($raw['kdppk'] ?? ''));
        // Gateway BPJS pernah mengirim string "null" — itu KOSONG, bukan kode.
        if (strtolower($kdppk) === 'null') {
            $kdppk = '';
        }

        $jmlRujuk = self::angkaTeks($raw['jmlRujuk'] ?? '');
        $kapasitas = self::angkaTeks($raw['kapasitas'] ?? '');

        // Sebagian alamat sudah memuat nama kota di ekornya — jangan ditempeli lagi.
        $alamat = trim((string) ($raw['alamatPpk'] ?? ($raw['alamat'] ?? '')));
        $kota = trim((string) ($raw['nmkc'] ?? ($raw['kota'] ?? '')));

        return [
            'orgId' => self::orgIdPolos((string) ($raw['kodeFaskesSatuSehat'] ?? '')),
            'kdppk' => $kdppk,
            'nama' => trim((string) ($raw['nmppk'] ?? ($raw['nama'] ?? ''))) ?: '-',
            // Strata kompetensi SATUSEHAT (Dasar/Madya/Utama/Paripurna). Server
            // mengirimnya tidak konsisten besar-kecilnya — dinormalkan; kosong tetap kosong.
            'strata' => self::strata($raw['strataSatuSehat'] ?? ($raw['strata'] ?? '')),
            'kelas' => trim((string) ($raw['kelas'] ?? '')),
            'jarak' => self::jarak($raw['distance'] ?? null),
            'waktu' => self::waktu($raw['estimatedTime'] ?? ($raw['waktuTempuh'] ?? null)),
            // Jadwal kerap null — itu "tidak diinformasikan", bukan "tutup".
            'jadwal' => trim((string) ($raw['jadwal'] ?? '')),
            'jmlRujuk' => $jmlRujuk,
            'kapasitas' => $kapasitas,
            'persentase' => self::angkaTeks($raw['persentase'] ?? ''),
            'alamat' => $alamat,
            'kota' => $kota,
            'telp' => trim((string) ($raw['telpPpk'] ?? ($raw['telp'] ?? ''))),
            // Beban hanya berarti bila kapasitasnya diketahui — "0/0" bukan informasi.
            'beban' => ($jmlRujuk !== '' && $kapasitas !== '' && $kapasitas !== '0')
                ? $jmlRujuk . '/' . $kapasitas
                : '',
        ];
    }

    /**
     * BPJS/SATUSEHAT kadang mengirim 1.7976931348623E+308 (float terbesar — penanda
     * "tak terhitung", bukan jarak) yang kalau dicetak apa adanya jadi sampah di layar.
     *
     * Yang disaring HANYA nilai mustahil. Angka yang cuma MENCURIGAKAN sengaja
     * dibiarkan tampil apa adanya — itu data pusat; menyembunyikannya dengan ambang
     * karangan justru menutupi masalah yang perlu dilaporkan.
     */
    public static function jarak($nilai): string
    {
        $angka = self::angkaWajar($nilai, self::BATAS_KM);

        return $angka === null ? '—' : rtrim(rtrim(number_format($angka, 1, ',', '.'), '0'), ',') . ' km';
    }

    public static function waktu($nilai): string
    {
        $angka = self::angkaWajar($nilai, self::BATAS_MENIT);

        return $angka === null ? '—' : number_format(round($angka), 0, ',', '.') . ' menit';
    }

    /** "dasar" / "DASAR" / " Dasar " → "Dasar"; kosong tetap kosong. */
    public static function strata($nilai): string
    {
        $teks = trim((string) $nilai);

        return $teks === '' ? '' : ucfirst(strtolower($teks));
    }

    /** Baris "Tujuan: …" di bawah tabel kandidat & di kepala surat rujukan. */
    public static function infoTujuan(array $raw): string
    {
        $baris = self::kandidatBaris($raw);
        $kode = array_filter([
            $baris['kdppk'] !== '' ? 'Kode BPJS ' . $baris['kdppk'] : null,
            $baris['orgId'] !== '' ? 'Org ID ' . $baris['orgId'] : null,
        ]);

        return 'Tujuan: ' . $baris['nama'] . ($kode ? ' (' . implode(' · ', $kode) . ')' : '');
    }

    /** "Organization/100006775" → "100006775". */
    public static function orgIdPolos(string $nilai): string
    {
        return trim(str_ireplace('Organization/', '', trim($nilai)));
    }

    private static function angkaTeks($nilai): string
    {
        if ($nilai === null || $nilai === '' || !is_numeric($nilai)) {
            return '';
        }

        return (string) (0 + $nilai);
    }

    private static function angkaWajar($nilai, float $batas): ?float
    {
        if ($nilai === null || $nilai === '' || !is_numeric($nilai)) {
            return null;
        }

        $angka = (float) $nilai;
        if (!is_finite($angka) || $angka < 0 || $angka > $batas) {
            return null;
        }

        return $angka;
    }
}
