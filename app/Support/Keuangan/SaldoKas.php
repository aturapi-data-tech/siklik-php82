<?php

namespace App\Support\Keuangan;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Rumus saldo kas Oracle Forms 6i (hitung_saldo_tanggal), dipakai halaman Cek Saldo Kas
 * supaya angkanya dapat dibandingkan langsung dengan form legacy. Pola sirus-php82.
 *
 * Sumber : App\Support\Keuangan\Jurnal — jurnal dibaca LANGSUNG dari tabel transaksi.
 *          Untuk akun kas (cara bayar), Jurnal hanya menyisakan cabang yang memuat kolom
 *          b.acc_id (BAYAR APOTEK, BAYAR RJ, BAYAR RCV, CASH IN/OUT TU); cabang lain
 *          ber-akun konfigurasi dan dibuang di PHP.
 * Akun D : baris dengan txn_acc   = akun, arus = txn_d − txn_k.
 * Akun K : baris dengan txn_acc_k = akun, arus = txn_k − txn_d.
 * Saldo  : saldo awal tahun (SKTXN_SALDOAWALAKUNS) + arus 1 Januari s/d tanggal.
 * Shift  : siklik tidak menyimpan shift di jurnal (kolom SHIFT view tak ada) dan SKTXN_SHIFTCTLS
 *          kosong; parameter shift dipertahankan demi kesamaan API, tidak berpengaruh.
 */
final class SaldoKas
{
    public static function saldoAwalTahun(string $accId, string $dkStatus, int $tahun): float
    {
        $saldoAwal = DB::table('sktxn_saldoawalakuns')
            ->where('acc_id', $accId)
            ->where('sa_year', (string) $tahun)
            ->first();

        return $dkStatus === 'D'
            ? (float) ($saldoAwal->sa_acc_d ?? 0)
            : (float) ($saldoAwal->sa_acc_k ?? 0);
    }

    /** Kolom sisi 6i: akun D dibaca dari baris txn_acc, akun K dari baris txn_acc_k. */
    public static function sisi(string $dkStatus): array
    {
        return $dkStatus === 'D'
            ? ['filter' => Jurnal::SISI_ACC, 'lawan' => Jurnal::SISI_ACCK, 'debit' => 'txn_d', 'kredit' => 'txn_k']
            : ['filter' => Jurnal::SISI_ACCK, 'lawan' => Jurnal::SISI_ACC, 'debit' => 'txn_k', 'kredit' => 'txn_d'];
    }

    /** Query dasar: baris akun ini pada rentang tanggal (inklusif). */
    public static function query(string $accId, string $dkStatus, string $dari, string $sampai, ?string $shift = null): Builder
    {
        $query = Jurnal::query($accId, self::sisi($dkStatus)['filter'], $dari, $sampai);

        if ($shift !== null && $shift !== '') {
            $query->whereRaw("(txn_date < TO_DATE(?,'YYYY-MM-DD') OR shift <= ?)", [$sampai, $shift]);
        }

        return $query;
    }

    /** Arus (mutasi bersih) akun pada rentang tanggal. */
    public static function arus(string $accId, string $dkStatus, string $dari, string $sampai, ?string $shift = null): float
    {
        $sisi = self::sisi($dkStatus);

        return (float) self::query($accId, $dkStatus, $dari, $sampai, $shift)
            ->sum(DB::raw("NVL({$sisi['debit']},0) - NVL({$sisi['kredit']},0)"));
    }

    /** Saldo per tanggal — padanan hitung_saldo_tanggal 6i. */
    public static function hitung(string $accId, string $dkStatus, string $tanggal, ?string $shift = null): float
    {
        $tahun = (int) substr($tanggal, 0, 4);

        return self::saldoAwalTahun($accId, $dkStatus, $tahun)
            + self::arus($accId, $dkStatus, sprintf('%04d-01-01', $tahun), $tanggal, $shift);
    }

    /** Arus satu tahun penuh (Jan–Des), dipakai back-calc Edit Saldo Awal. */
    public static function arusTahun(string $accId, string $dkStatus, int $tahun): float
    {
        return self::arus($accId, $dkStatus, sprintf('%04d-01-01', $tahun), sprintf('%04d-12-31', $tahun));
    }

    /** Nomor shift sesuai jam sekarang (sktxn_shiftctls), fallback '1'. */
    public static function shiftSekarang(): string
    {
        $shift = DB::table('sktxn_shiftctls')
            ->select('shift')
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [now()->format('H:i:s')])
            ->first();

        return (string) ($shift?->shift ?? '1');
    }

    /** Daftar nomor shift yang terdefinisi, urut jam mulai. */
    public static function daftarShift(): array
    {
        return DB::table('sktxn_shiftctls')
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->orderBy('shift_start')
            ->pluck('shift')
            ->map(fn ($nomorShift) => (string) $nomorShift)
            ->unique()
            ->values()
            ->all();
    }
}
