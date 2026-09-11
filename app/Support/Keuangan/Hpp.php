<?php

namespace App\Support\Keuangan;

use Illuminate\Support\Facades\DB;

/**
 * Harga Pokok Penjualan tahunan — padanan view SKVIEW_HPPES + cabang HPP di SKVIEW_ACCOUNTS_LABARUGI,
 * dihitung di PHP supaya tidak bergantung pada SKVIEW_ACCOUNTS (hasil tak stabil di Oracle 10.2).
 *
 * Rumus (sama dengan view HPPES):
 *   HPP(tahun) = saldo awal persediaan (sktxn_saldoawalakuns akun conf:2, D − K)
 *              + arus persediaan tahun itu (Jurnal, akun conf:2, D − K, TANPA cabang HPP)
 *              − Σ hpp_product × stockwh_akhir (SKVIEW_SALDOAKHIRSTOCKS tahun itu)
 * Dijurnalkan 1 Desember tahun itu: akun HPP (conf:9) debit, persediaan (conf:2) kredit — plus baris cermin.
 *
 * Catatan data: stock opname yang belum rutin dan saldo awal yang belum lengkap membuat HPP bisa
 * menyimpang jauh (lihat memori proyek "Data integrity keuangan"); laporan menampilkan rumusnya
 * dan halaman Laba Rugi memberi opsi override manual bila diperlukan.
 */
final class Hpp
{
    public const CONF_PERSEDIAAN = '2';

    public const CONF_HPP = '9';

    private static array $cache = [];

    public static function nilai(int $tahun): float
    {
        if (isset(self::$cache[$tahun])) {
            return self::$cache[$tahun];
        }

        $akunPersediaan = Jurnal::akunKonfigurasiId(self::CONF_PERSEDIAAN);
        if ($akunPersediaan === null) {
            return self::$cache[$tahun] = 0.0;
        }

        $saldoAwal = Jurnal::saldoAwalPerAkun([$akunPersediaan], $tahun)[$akunPersediaan];
        $arus = Jurnal::arusPerAkun([$akunPersediaan], sprintf('%04d-01-01', $tahun), sprintf('%04d-12-31', $tahun), false)[$akunPersediaan];
        $stokAkhir = (float) (DB::table('skview_saldoakhirstocks')
            ->where('sa_year', (string) $tahun)
            ->selectRaw('sum(nvl(hpp_product,0) * nvl(stockwh_akhir,0)) nilai')
            ->value('nilai') ?? 0);

        return self::$cache[$tahun] = ($saldoAwal['debit'] - $saldoAwal['kredit']) + ($arus['debit'] - $arus['kredit']) - $stokAkhir;
    }

    /**
     * Kewajaran HPP: stok akhir tidak boleh melebihi saldo awal + arus persediaan (HPP negatif berarti
     * data stock opname / hpp_product rusak). Halaman memakai ini untuk mengganti HPP dengan 0 + override manual.
     */
    public static function wajar(int $tahun): bool
    {
        return self::nilai($tahun) >= 0;
    }

    /** Rincian komponen rumus untuk ditampilkan di laporan. */
    public static function rincian(int $tahun): array
    {
        $akunPersediaan = Jurnal::akunKonfigurasiId(self::CONF_PERSEDIAAN);
        $saldoAwal = $akunPersediaan ? Jurnal::saldoAwalPerAkun([$akunPersediaan], $tahun)[$akunPersediaan] : ['debit' => 0.0, 'kredit' => 0.0];
        $arus = $akunPersediaan
            ? Jurnal::arusPerAkun([$akunPersediaan], sprintf('%04d-01-01', $tahun), sprintf('%04d-12-31', $tahun), false)[$akunPersediaan]
            : ['debit' => 0.0, 'kredit' => 0.0];
        $stokAkhir = (float) (DB::table('skview_saldoakhirstocks')
            ->where('sa_year', (string) $tahun)
            ->selectRaw('sum(nvl(hpp_product,0) * nvl(stockwh_akhir,0)) nilai')
            ->value('nilai') ?? 0);

        return [
            'akunPersediaan' => $akunPersediaan,
            'saldoAwal' => $saldoAwal['debit'] - $saldoAwal['kredit'],
            'arus' => $arus['debit'] - $arus['kredit'],
            'stokAkhir' => $stokAkhir,
            'hpp' => self::nilai($tahun),
        ];
    }

    /**
     * Cabang semu jurnal HPP (`from dual`) untuk tahun-tahun yang 1 Desembernya masuk rentang.
     * Mengembalikan list [sql, bindings]; kosong bila akun HPP/persediaan tidak diminta.
     *
     * @return list<array{0:string,1:array}>
     */
    public static function cabangSemu(array $accIds, string $sisi, string $dari, string $sampai): array
    {
        $akunHpp = Jurnal::akunKonfigurasiId(self::CONF_HPP);
        $akunPersediaan = Jurnal::akunKonfigurasiId(self::CONF_PERSEDIAAN);
        if ($akunHpp === null || $akunPersediaan === null) {
            return [];
        }

        $pasangan = [
            // [akun, akunLawan, debit?, kredit?]
            [$akunHpp, $akunPersediaan, true, false],
            [$akunPersediaan, $akunHpp, false, true],
        ];

        $hasil = [];
        for ($tahun = (int) substr($dari, 0, 4); $tahun <= (int) substr($sampai, 0, 4); $tahun++) {
            $tanggal = sprintf('%04d-12-01', $tahun);
            if ($tanggal < $dari || $tanggal > $sampai) {
                continue;
            }
            $nilai = null;
            foreach ($pasangan as [$akun, $lawan, $debit, $kredit]) {
                $akunSisi = $sisi === Jurnal::SISI_ACCK ? $lawan : $akun;
                if (! in_array($akunSisi, $accIds, true)) {
                    continue;
                }
                $nilai ??= self::nilai($tahun);
                $hasil[] = [
                    // to_number(?): driver oci8 mem-bind semua placeholder sebagai VARCHAR2; tanpa cast, UNION ALL
                    // dengan cabang lain yang txn_d/txn_k-nya NUMBER melempar ORA-01790.
                    "select 'HPP' txn_name, ? txn_acc, ? txn_acc_k, '1' shift, TO_DATE(?,'YYYY-MM-DD') txn_date, to_number(?) txn_d, to_number(?) txn_k from dual",
                    [$akun, $lawan, $tanggal, $debit ? $nilai : 0, $kredit ? $nilai : 0],
                ];
            }
        }

        return $hasil;
    }
}
