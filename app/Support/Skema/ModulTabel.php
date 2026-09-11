<?php

namespace App\Support\Skema;

/**
 * Pengelompokan tabel/view Oracle siklik ke modul aplikasi.
 *
 * Nama tabel sejak 11 Sep 2026 berprefix SKMST_/SKTXN_/SKACC_/SKVIEW_ (huruf modul
 * lama DI/IM/LB/RS/SC/TK dilebur), jadi modul tidak bisa dibaca dari prefix — harus
 * dari daftar pola di sini. Urutan pola menentukan: yang pertama cocok menang.
 *
 * Dipakai halaman /panduan-dev/struktur-tabel dan `php artisan siklik:dok-tabel`.
 */
class ModulTabel
{
    /** @var list<array{modul:string,pola:string,keterangan:string}> */
    private const POLA = [
        ['modul' => 'Rawat Jalan', 'pola' => '/^SKTXN_(RJ|SHIFTCTLS)|^SKVIEW_(RJ|ERMSTATUS|DRRJANTRIAN|DASHBOARD|DB_RJS|RADS|RSLABS|CHECKUPS)/', 'keterangan' => 'Kunjungan rawat jalan: header (CLOB EMR), rincian tindakan/obat/lab/rad, kasir, upload BPJS'],
        ['modul' => 'Laboratorium', 'pola' => '/^SKMST_CLAB|^SKTXN_CHECKUP/', 'keterangan' => 'Master item lab & transaksi pemeriksaan lab (rawat jalan maupun langsung)'],
        ['modul' => 'Pasien & Klinik', 'pola' => '/^SKMST_(PASIENS|DOCTORS|POLIS|RELIGIONS|EDUCATIONS|JOBS|ENTRYTYPES|OUTS|KLAIMTYPES|MEDIK|OTHERS|MSTDIAGS|MSTPROCEDURES|RADIOLOGIS|SCPOLIS|SCDAYS)$|^SKVIEW_SCPOLIS$/', 'keterangan' => 'Master pasien, dokter, poli, referensi kunjungan (cara masuk/keluar, klaim), ICD-10/ICD-9, radiologi, jadwal poli'],
        ['modul' => 'Tarif & Jasa', 'pola' => '/^SKMST_(ACCDOC|ACTE|ACTPAR)/', 'keterangan' => 'Tarif tindakan dokter (ACCDOC), pegawai (ACTE), paramedis (ACTPAR) beserta komponen obat/lain'],
        ['modul' => 'Wilayah', 'pola' => '/^SKMST_(PROPINSIS|KABUPATENS|KECAMATANS|DESAS)$/', 'keterangan' => 'Hirarki wilayah untuk alamat pasien (kode BPS = kode wilayah SATUSEHAT)'],
        ['modul' => 'Terminologi SATUSEHAT', 'pola' => '/^SKMST_(LOINC|SNOMED)_CODES$/', 'keterangan' => 'Kamus LOINC (lab/radiologi) & SNOMED untuk kirim FHIR'],
        ['modul' => 'Akuntansi', 'pola' => '/^SKACC_|^SKVIEW_ACC/', 'keterangan' => 'Bagan akun (SKACC_ACCOUNTSES), cara bayar, konfigurasi jurnal, template laba-rugi/neraca'],
        ['modul' => 'Kas & Hutang-Piutang', 'pola' => '/^SKTXN_(CASHIN|CASHOUT|TUCASH|SALDOAWALAKUNS|SALDOAWALHUTANGS|SALDOAWALPIUTANGS)|^SKVIEW_(IOHUTANGS|IOPIUTANGS|SALDOAWALHUTANG|SALDOAWALPIUTANG)/', 'keterangan' => 'Penerimaan/pengeluaran kas, kas TU, saldo awal akun/hutang/piutang'],
        ['modul' => 'Apotek & Gudang (master)', 'pola' => '/^SKMST_(PRODUCT|CATEGORIES|UOMS|SUPPLIERS|CUSTOMERS|KASIRS|KOTAS|PROVS|EX_IMS|CONTENTS|SIGNA_CATATANS)/', 'keterangan' => 'Master obat/barang (SKMST_PRODUCTS = master obat klinik), satuan, kategori, supplier, kasir, signa'],
        ['modul' => 'Apotek & Gudang (transaksi)', 'pola' => '/^SKTXN_(SLS|RCV|SOWHS|SALDOAWALSTOCKS)|^SKVIEW_(IOSTOCK|SALDOSTOCK|SALDOAKHIRSTOCK|SALDOAWALSTOCK|HPPES|LASTRCV|RCV|SLS)/', 'keterangan' => 'Penjualan bebas, penerimaan dari PBF/supplier, stock opname, kartu stok, HPP'],
        ['modul' => 'Aplikasi & Identitas', 'pola' => '/^SKMST_(APPLICATIONS|USERAPPLICATIONS|USERS|IDENTITASES|PARAMETERS)$|^SKVIEW_APPLICATIONS$/', 'keterangan' => 'Identitas klinik (kop), parameter sistem, menu/user aplikasi warisan'],
        ['modul' => 'BPJS & Log', 'pola' => '/^(PASIEN|REF_BPJS_TABLE|REFERENSI_MOBILEJKN_BPJS|WEB_LOG_STATUS)$/', 'keterangan' => 'Cache referensi PCare, antrean Mobile JKN, log pemanggilan API eksternal'],
        ['modul' => 'Sistem Laravel', 'pola' => '/^(USERS|ROLES|PERMISSIONS|MODEL_HAS_|ROLE_HAS_|SESSIONS|CACHE|JOBS|JOB_BATCHES|FAILED_JOBS|MIGRATIONS|PASSWORD_RESET_TOKENS|PERSONAL_ACCESS_TOKENS)/', 'keterangan' => 'Tabel bawaan Laravel & Spatie Permission (login, role, sesi, antrean job)'],
    ];

    public const LAINNYA = 'Lain-lain';

    public static function dari(string $namaTabel): string
    {
        $nama = strtoupper($namaTabel);
        foreach (self::POLA as $p) {
            if (preg_match($p['pola'], $nama)) {
                return $p['modul'];
            }
        }

        return self::LAINNYA;
    }

    /** Urutan tampil modul + keterangan singkatnya. @return array<string,string> modul → keterangan */
    public static function daftar(): array
    {
        $hasil = [];
        foreach (self::POLA as $p) {
            $hasil[$p['modul']] = $p['keterangan'];
        }
        $hasil[self::LAINNYA] = 'Tabel yang belum dipetakan ke modul mana pun';

        return $hasil;
    }

    /** Aturan prefix baru → penjelasan. @return array<string,string> */
    public static function prefix(): array
    {
        return [
            'SKMST_' => 'Master / referensi (jarang berubah, dirawat lewat menu Master)',
            'SKTXN_' => 'Transaksi (bertambah tiap hari: kunjungan, penjualan, penerimaan, kas)',
            'SKACC_' => 'Akuntansi (bagan akun, cara bayar, konfigurasi jurnal)',
            'SKVIEW_' => 'View Oracle (hasil gabungan untuk layar/laporan, tidak ditulis aplikasi)',
        ];
    }

    /** Huruf modul lama yang dilebur, untuk dijelaskan di panduan. @return array<string,string> */
    public static function prefixLama(): array
    {
        return [
            'RS' => 'Rumah sakit / klinik (pasien, dokter, poli, rawat jalan) — warisan sirus',
            'TK' => 'Toko / apotek (produk, penjualan, penerimaan, kas, akuntansi) — warisan Tokoku',
            'LB' => 'Laboratorium',
            'DI' => 'Dictionary aplikasi (menu, user, identitas)',
            'IM' => 'Inventory medis (kemasan/isi obat)',
            'SC' => 'Schedule (jadwal poli & hari)',
        ];
    }
}
