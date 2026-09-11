<?php

namespace App\Console\Commands;

use App\Support\Keuangan\Hpp;
use App\Support\Keuangan\Jurnal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Verifikasi katalog jurnal: bandingkan App\Support\Keuangan\Jurnal dengan view SKVIEW_ACCOUNTS
 * per akun pada TINGKAT BARIS (jumlah baris + Σ debit + Σ kredit) untuk rentang tanggal pendek.
 * Agregat view di Oracle 10g tidak stabil, jadi pembanding dihitung di PHP dari baris mentah.
 *
 *   php artisan siklik:verif-jurnal                       # bulan lalu, semua akun yang punya transaksi
 *   php artisan siklik:verif-jurnal --dari=2026-08-01 --sampai=2026-08-31 --akun=1112,1131
 */
class SiklikVerifJurnal extends Command
{
    protected $signature = 'siklik:verif-jurnal
        {--dari= : Tanggal awal YYYY-MM-DD (bawaan: awal bulan lalu)}
        {--sampai= : Tanggal akhir YYYY-MM-DD (bawaan: akhir bulan lalu)}
        {--akun= : Daftar acc_id dipisah koma (bawaan: semua akun yang muncul di view pada rentang itu)}';

    protected $description = 'Bandingkan Jurnal (tabel transaksi langsung) vs view SKVIEW_ACCOUNTS per akun tingkat baris';

    public function handle(): int
    {
        $dari = $this->option('dari') ?: now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d');
        $sampai = $this->option('sampai') ?: now()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d');
        $rentang = ["txn_date >= to_date(?,'YYYY-MM-DD') and txn_date < to_date(?,'YYYY-MM-DD') + 1", [$dari, $sampai]];

        $akunList = $this->option('akun')
            ? array_map('trim', explode(',', $this->option('akun')))
            : DB::table('skview_accounts')->whereRaw($rentang[0], $rentang[1])->distinct()->orderBy('txn_acc')->pluck('txn_acc')->all();

        $this->info("Rentang $dari s/d $sampai — ".count($akunList).' akun');
        $beda = 0;
        $baris = [];
        foreach ($akunList as $acc) {
            $acc = (string) $acc;
            $j = Jurnal::query($acc, Jurnal::SISI_ACC, $dari, $sampai)->get();
            $v = DB::table('skview_accounts')->where('txn_acc', $acc)->whereRaw($rentang[0], $rentang[1])->get();
            // Cabang semu HPP (1 Des) tidak ada di SKVIEW_ACCOUNTS (ada di _LABARUGI) → keluarkan dari pembanding.
            $j = $j->filter(fn ($r) => $r->txn_name !== 'HPP');
            $sama = $j->count() === $v->count()
                && round((float) $j->sum('txn_d')) === round((float) $v->sum('txn_d'))
                && round((float) $j->sum('txn_k')) === round((float) $v->sum('txn_k'));
            $beda += $sama ? 0 : 1;
            $baris[] = [$acc, $j->count(), number_format((float) $j->sum('txn_d')), number_format((float) $j->sum('txn_k')),
                $v->count(), number_format((float) $v->sum('txn_d')), number_format((float) $v->sum('txn_k')), $sama ? 'SAMA' : 'BEDA'];
        }
        $this->table(['Akun', 'Jurnal baris', 'Jurnal D', 'Jurnal K', 'View baris', 'View D', 'View K', 'Hasil'], $baris);

        $tahun = (int) substr($sampai, 0, 4);
        $hppView = DB::table('skview_hppes')->where('hpp_year', (string) $tahun)->value('hpp');
        $this->line(sprintf('HPP %d: Jurnal/Hpp = %s | view HPPES = %s', $tahun, number_format(Hpp::nilai($tahun)), $hppView === null ? 'null' : number_format((float) $hppView)));

        if ($beda > 0) {
            $this->error("$beda akun BEDA — periksa katalog JurnalCabang (atau view di DB target berbeda dari yang diturunkan).");

            return self::FAILURE;
        }
        $this->info('Semua akun SAMA.');

        return self::SUCCESS;
    }
}
