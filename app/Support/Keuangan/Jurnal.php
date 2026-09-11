<?php

namespace App\Support\Keuangan;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Jurnal umum dibaca LANGSUNG dari tabel transaksi — pengganti view SKVIEW_ACCOUNTS
 * (dan turunannya _LABARUGI / _NERACA) untuk laporan keuangan web. Pola sirus-php82.
 *
 * Katalog cabangnya (JurnalCabang, dirawat di PHP; diturunkan dari DDL view) berbentuk SAMA
 * dengan view: TXN_NAME, TXN_ACC, TXN_ACC_K, TXN_DATE, TXN_D, TXN_K (+ SHIFT tetap '1' supaya
 * bentuk hasil sama dengan sirus). Bedanya, query dibangun PER AKUN:
 *   - cabang yang akunnya akun konfigurasi (skacc_confacctxns) dan bukan akun yang diminta
 *     dibuang di PHP, sama sekali tidak dikirim ke Oracle;
 *   - cabang yang akunnya kolom tabel sumber (b.acc_id) diberi predikat `kolom = ?`;
 *   - predikat tanggal sargable ditanam di TIAP cabang (bukan di luar);
 *   - HPP tahunan (dulu cabang view _LABARUGI yang membaca SKVIEW_HPPES) disisipkan sebagai
 *     cabang semu `from dual` per tahun dari App\Support\Keuangan\Hpp, tanggal 1 Desember.
 *
 * Kenapa bukan view: Oracle 10.2.0.1 memberi hasil TIDAK STABIL untuk UNION ALL + subquery skalar
 * (agregat berubah tergantung bentuk query) — lihat docs/jurnal-keuangan.md.
 *
 * Konvensi sisi: baris `txn_acc = akun` adalah baris MILIK akun itu (txn_d/txn_k = debit/kredit
 * akun itu sendiri); baris `txn_acc_k = akun` adalah baris cermin milik akun lawan.
 */
final class Jurnal
{
    public const SISI_ACC = 'txn_acc';

    public const SISI_ACCK = 'txn_acc_k';

    private static ?array $konfigurasi = null;

    /** conf_id => acc_id dari skacc_confacctxns (di-cache satu request). */
    public static function konfigurasiAkun(): array
    {
        return self::$konfigurasi ??= DB::table('skacc_confacctxns')
            ->pluck('acc_id', 'conf_id')
            ->map(fn ($accId) => (string) $accId)
            ->all();
    }

    /** acc_id untuk satu conf_id, null bila belum dikonfigurasi. */
    public static function akunKonfigurasiId(string $confId): ?string
    {
        return self::konfigurasiAkun()[$confId] ?? null;
    }

    private static function isKonfigurasi(string $ekspresi): bool
    {
        return str_starts_with($ekspresi, 'conf:');
    }

    private static function akunKonfigurasi(string $ekspresi, array $petaKonfigurasi): ?string
    {
        return $petaKonfigurasi[substr($ekspresi, 5)] ?? null;
    }

    /**
     * Baris jurnal satu akun pada rentang tanggal (inklusif, 'YYYY-MM-DD').
     * $sisi: SISI_ACC (baris milik akun) atau SISI_ACCK (baris lawan).
     * Kolom hasil: txn_name, txn_acc, txn_acc_k, shift, txn_date, txn_d, txn_k.
     */
    public static function query(string $accId, string $sisi, string $dari, string $sampai): Builder
    {
        return self::queryBanyak([$accId], $sisi, $dari, $sampai);
    }

    /**
     * Arus D/K per akun untuk SEKUMPULAN akun sekaligus (satu pemindaian) — dipakai laporan
     * ber-template (Laba Rugi, Neraca). Hasil: [acc_id => ['debit' => float, 'kredit' => float]],
     * akun tanpa transaksi tetap ada dengan nol. $denganHpp=false dipakai Hpp sendiri (cegah rekursi).
     */
    public static function arusPerAkun(array $accIds, string $dari, string $sampai, bool $denganHpp = true): array
    {
        $accIds = array_values(array_unique(array_map('strval', $accIds)));
        $arusList = array_fill_keys($accIds, ['debit' => 0.0, 'kredit' => 0.0]);

        foreach (array_chunk($accIds, 900) as $kelompokAkun) {   // batas IN-list Oracle 1000
            $rows = self::queryBanyak($kelompokAkun, self::SISI_ACC, $dari, $sampai, $denganHpp)
                ->selectRaw('txn_acc, sum(nvl(txn_d,0)) debit, sum(nvl(txn_k,0)) kredit')
                ->groupBy('txn_acc')
                ->get();
            foreach ($rows as $baris) {
                if (isset($arusList[(string) $baris->txn_acc])) {
                    $arusList[(string) $baris->txn_acc] = ['debit' => (float) $baris->debit, 'kredit' => (float) $baris->kredit];
                }
            }
        }

        return $arusList;
    }

    /**
     * Baris jurnal sekumpulan akun (lihat query()). Cabang ber-akun konfigurasi ikut hanya bila akunnya
     * ada di daftar; cabang ber-kolom akun mendapat predikat `kolom IN (...)`.
     */
    public static function queryBanyak(array $accIds, string $sisi, string $dari, string $sampai, bool $denganHpp = true): Builder
    {
        $accIds = array_values(array_unique(array_map('strval', $accIds)));
        $petaKonfigurasi = self::konfigurasiAkun();
        $cabangSql = [];
        $bindings = [];

        foreach (JurnalCabang::semua() as $cabang) {
            $akunSisi = $sisi === self::SISI_ACCK ? $cabang['akunLawan'] : $cabang['akun'];

            if (self::isKonfigurasi($akunSisi) && ! in_array(self::akunKonfigurasi($akunSisi, $petaKonfigurasi), $accIds, true)) {
                continue; // akun konfigurasi cabang ini tidak diminta
            }

            // Ekspresi akun di select-list: konfigurasi → binding literal, kolom/subquery → apa adanya.
            $bindingSelect = [];
            $akunSql = $cabang['akun'];
            if (self::isKonfigurasi($akunSql)) {
                $bindingSelect[] = self::akunKonfigurasi($akunSql, $petaKonfigurasi);
                $akunSql = '?';
            }
            $akunLawanSql = $cabang['akunLawan'];
            if (self::isKonfigurasi($akunLawanSql)) {
                $bindingSelect[] = self::akunKonfigurasi($akunLawanSql, $petaKonfigurasi);
                $akunLawanSql = '?';
            }

            $where = [];
            $bindingWhere = [];
            if (! self::isKonfigurasi($akunSisi)) {
                $where[] = count($accIds) === 1
                    ? "{$akunSisi} = ?"
                    : "{$akunSisi} in (".implode(',', array_fill(0, count($accIds), '?')).')';
                array_push($bindingWhere, ...$accIds);
            }
            $where[] = "{$cabang['tanggal']} >= TO_DATE(?,'YYYY-MM-DD') and {$cabang['tanggal']} < TO_DATE(?,'YYYY-MM-DD') + 1";
            $bindingWhere[] = $dari;
            $bindingWhere[] = $sampai;
            if ($cabang['where'] !== '') {
                $where[] = "({$cabang['where']})";
            }

            $cabangSql[] = "select {$cabang['label']} txn_name, {$akunSql} txn_acc, {$akunLawanSql} txn_acc_k, '1' shift, "
                ."{$cabang['tanggal']} txn_date, {$cabang['debit']} txn_d, {$cabang['kredit']} txn_k from {$cabang['from']} where ".implode(' and ', $where);

            array_push($bindings, ...$bindingSelect, ...$bindingWhere);
        }

        if ($denganHpp) {
            foreach (Hpp::cabangSemu($accIds, $sisi, $dari, $sampai) as [$sql, $bind]) {
                $cabangSql[] = $sql;
                array_push($bindings, ...$bind);
            }
        }

        if ($cabangSql === []) {
            // Akun tidak pernah muncul di jurnal → query kosong yang tetap sah.
            $cabangSql[] = "select null txn_name, null txn_acc, null txn_acc_k, '1' shift, "
                .'cast(null as date) txn_date, 0 txn_d, 0 txn_k from dual where 1 = 0';
        }

        return DB::table(DB::raw('('.implode("\nunion all\n", $cabangSql).') j'))
            ->addBinding($bindings, 'from');
    }

    /**
     * Saldo awal tahun (sktxn_saldoawalakuns) untuk sekumpulan akun:
     * [acc_id => ['debit' => sa_acc_d, 'kredit' => sa_acc_k]], akun tanpa baris = nol.
     */
    public static function saldoAwalPerAkun(array $accIds, int $tahun): array
    {
        $accIds = array_values(array_unique(array_map('strval', $accIds)));
        $saldoList = array_fill_keys($accIds, ['debit' => 0.0, 'kredit' => 0.0]);

        foreach (array_chunk($accIds, 900) as $kelompokAkun) {
            $rows = DB::table('sktxn_saldoawalakuns')
                ->whereIn('acc_id', $kelompokAkun)
                ->where('sa_year', (string) $tahun)
                ->get();
            foreach ($rows as $baris) {
                $saldoList[(string) $baris->acc_id] = [
                    'debit' => (float) ($baris->sa_acc_d ?? 0),
                    'kredit' => (float) ($baris->sa_acc_k ?? 0),
                ];
            }
        }

        return $saldoList;
    }

    /** Nama akun untuk kumpulan acc_id (query kecil terpisah; jangan join di atas jurnal). */
    public static function namaAkun(iterable $accIds): array
    {
        $accIdList = collect($accIds)->filter()->unique()->values()->all();

        return $accIdList === [] ? [] : DB::table('skacc_accountses')
            ->whereIn('acc_id', $accIdList)
            ->pluck('acc_desc', 'acc_id')
            ->all();
    }
}
