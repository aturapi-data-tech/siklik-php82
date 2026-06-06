<?php

namespace App\Http\Traits\Manajemen\Rs\Tu;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Trait untuk laporan Pendapatan Klinik (klinik pratama — RAWAT JALAN + Apotek penjualan bebas).
 *
 * Slim-port dari sirus-php82 PendapatanRsTrait, dibuang komponen UGD/RI/OK/kamar/visite.
 * Komponen pendapatan:
 *   RJ (rstxn_rjhdrs, rj_status='L', tanggal rj_date):
 *     - Jasa Dokter RJ       rstxn_rjaccdocs.accdoc_price
 *     - Jasa Karyawan        rstxn_rjactemps.acte_price
 *     - Jasa Paramedis       rstxn_rjactparams.pact_price
 *     - Obat RJ              rstxn_rjobats   SUM(qty * price)
 *     - Obat Racikan RJ      rstxn_rjobatracikans SUM(qty * price)  (NVL → 0 bila price NULL)
 *     - Laborat              rstxn_rjlabs.lab_price
 *     - Radiologi            rstxn_rjrads.rad_price
 *     - Lain-lain            rstxn_rjothers.other_price
 *     - Admin RS / RJ / Poli rstxn_rjhdrs.rs_admin + rj_admin + poli_price
 *   Apotek (tktxn_slshdrs, tanggal sls_date):
 *     - Penjualan Bebas      tktxn_slsdtls SUM(qty * sales_price - NVL(dtl_diskon,0))
 *
 * Catatan klaim: split BPJS vs UMUM via rsmst_klaimtypes.klaim_status. Penjualan bebas
 * apotek selalu masuk kategori UMUM (tidak ada klaim BPJS untuk obat bebas).
 */
trait PendapatanKlinikTrait
{
    /**
     * Bangun matriks pendapatan per periode × komponen × klaim.
     *
     * @return array  [periode => ['rj_bpjs','rj_umum','apt_umum','bpjs','umum','total']]
     */
    protected function buildPendapatanKlinikAggregate(Carbon $start, Carbon $end, string $oracleFmt): array
    {
        $rj  = $this->aggregateRjKlinik($start, $end, $oracleFmt);
        $apt = $this->aggregateApotek($start, $end, $oracleFmt);

        $periodes = collect()
            ->merge($rj->keys())
            ->merge($apt->keys())
            ->unique()
            ->values();

        $result = [];
        foreach ($periodes as $p) {
            $rjB  = (int) ($rj[$p]['bpjs'] ?? 0);
            $rjU  = (int) ($rj[$p]['umum'] ?? 0);
            $aptU = (int) ($apt[$p]['umum'] ?? 0);
            $bpjs = $rjB;
            $umum = $rjU + $aptU;
            $result[$p] = [
                'rj_bpjs'  => $rjB,
                'rj_umum'  => $rjU,
                'apt_umum' => $aptU,
                'bpjs'     => $bpjs,
                'umum'     => $umum,
                'total'    => $bpjs + $umum,
            ];
        }
        return $result;
    }

    private function aggregateRjKlinik(Carbon $start, Carbon $end, string $fmt): Collection
    {
        $periodeExpr = "to_char(h.rj_date, '{$fmt}')";

        $perRjExpr = "NVL(h.rs_admin,0) + NVL(h.rj_admin,0) + NVL(h.poli_price,0)
                      + NVL(acte.v,0) + NVL(actp.v,0) + NVL(actd.v,0)
                      + NVL(obt.v,0)  + NVL(rac.v,0)  + NVL(lab.v,0)
                      + NVL(rad.v,0)  + NVL(oth.v,0)";

        return DB::table('rstxn_rjhdrs as h')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoinSub(DB::table('rstxn_rjactemps')->select('rj_no', DB::raw('NVL(SUM(acte_price),0) as v'))->groupBy('rj_no'),         'acte', 'acte.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjactparams')->select('rj_no', DB::raw('NVL(SUM(pact_price),0) as v'))->groupBy('rj_no'),       'actp', 'actp.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjaccdocs')->select('rj_no', DB::raw('NVL(SUM(accdoc_price),0) as v'))->groupBy('rj_no'),       'actd', 'actd.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjobats')->select('rj_no', DB::raw('NVL(SUM(qty * price),0) as v'))->groupBy('rj_no'),          'obt',  'obt.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjobatracikans')->select('rj_no', DB::raw('NVL(SUM(NVL(qty,0) * NVL(price,0)),0) as v'))->groupBy('rj_no'), 'rac', 'rac.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjlabs')->select('rj_no', DB::raw('NVL(SUM(lab_price),0) as v'))->groupBy('rj_no'),             'lab',  'lab.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjrads')->select('rj_no', DB::raw('NVL(SUM(rad_price),0) as v'))->groupBy('rj_no'),             'rad',  'rad.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjothers')->select('rj_no', DB::raw('NVL(SUM(other_price),0) as v'))->groupBy('rj_no'),         'oth',  'oth.rj_no', '=', 'h.rj_no')
            ->where('h.rj_status', 'L')
            ->whereBetween('h.rj_date', [$start, $end])
            ->selectRaw("{$periodeExpr} as periode,
                SUM(CASE WHEN k.klaim_status = 'BPJS' THEN ({$perRjExpr}) ELSE 0 END) as bpjs,
                SUM(CASE WHEN k.klaim_status != 'BPJS' OR k.klaim_status IS NULL THEN ({$perRjExpr}) ELSE 0 END) as umum")
            ->groupBy(DB::raw($periodeExpr))
            ->get()
            ->keyBy('periode')
            ->map(fn ($r) => ['bpjs' => (int) $r->bpjs, 'umum' => (int) $r->umum]);
    }

    /**
     * Penjualan bebas apotek: omzet = SUM(qty * sales_price - dtl_diskon) per header,
     * di-group ke periode pakai sls_date. Selalu UMUM (obat bebas tidak diklaim BPJS).
     * Exclude header batal: sls_status != 'C' (filter konservatif; sls_status='1'/null = aktif).
     */
    private function aggregateApotek(Carbon $start, Carbon $end, string $fmt): Collection
    {
        $periodeExpr = "to_char(h.sls_date, '{$fmt}')";

        $omzetExpr = "NVL(SUM(NVL(d.qty,0) * NVL(d.sales_price,0) - NVL(d.dtl_diskon,0)),0)";

        return DB::table('tktxn_slshdrs as h')
            ->join('tktxn_slsdtls as d', 'd.sls_no', '=', 'h.sls_no')
            ->where(function ($q) {
                $q->where('h.sls_status', '!=', 'C')->orWhereNull('h.sls_status');
            })
            ->whereBetween('h.sls_date', [$start, $end])
            ->selectRaw("{$periodeExpr} as periode, {$omzetExpr} as umum")
            ->groupBy(DB::raw($periodeExpr))
            ->get()
            ->keyBy('periode')
            ->map(fn ($r) => ['umum' => (int) $r->umum]);
    }

    protected function chartDataPendapatanKlinik(array $rows): array
    {
        return [
            'labels'   => array_column($rows, 'label'),
            'rj_bpjs'  => array_map(fn ($r) => (int) $r['rj_bpjs'],  $rows),
            'rj_umum'  => array_map(fn ($r) => (int) $r['rj_umum'],  $rows),
            'apt_umum' => array_map(fn ($r) => (int) $r['apt_umum'], $rows),
            'bpjs'     => array_map(fn ($r) => (int) $r['bpjs'],     $rows),
            'umum'     => array_map(fn ($r) => (int) $r['umum'],     $rows),
            'total'    => array_map(fn ($r) => (int) $r['total'],    $rows),
        ];
    }

    protected function totalsPendapatanKlinik(array $rows): array
    {
        return [
            'rj_bpjs'  => array_sum(array_column($rows, 'rj_bpjs')),
            'rj_umum'  => array_sum(array_column($rows, 'rj_umum')),
            'apt_umum' => array_sum(array_column($rows, 'apt_umum')),
            'bpjs'     => array_sum(array_column($rows, 'bpjs')),
            'umum'     => array_sum(array_column($rows, 'umum')),
            'total'    => array_sum(array_column($rows, 'total')),
        ];
    }

    protected function bulanLabelPendapatan(int $m): string
    {
        return ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'][$m - 1] ?? (string) $m;
    }

    /* ───────────────────────────────────────────────────────────────
       BREAKDOWN per dokter — sumber: RJ (jasa dokter + seluruh komponen RJ)
       ─────────────────────────────────────────────────────────────── */

    /**
     * RJ: per dokter × poli, count distinct rj_no + sum pendapatan (BPJS/UMUM/total).
     */
    protected function dokterBreakdownRjKlinik(Carbon $start, Carbon $end): array
    {
        $perRjExpr = "NVL(h.rs_admin,0) + NVL(h.rj_admin,0) + NVL(h.poli_price,0)
                      + NVL(acte.v,0) + NVL(actp.v,0) + NVL(actd.v,0)
                      + NVL(obt.v,0)  + NVL(rac.v,0)  + NVL(lab.v,0)
                      + NVL(rad.v,0)  + NVL(oth.v,0)";

        $rows = DB::table('rstxn_rjhdrs as h')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_polis as p', 'p.poli_id', '=', 'h.poli_id')
            ->leftJoinSub(DB::table('rstxn_rjactemps')->select('rj_no', DB::raw('NVL(SUM(acte_price),0) as v'))->groupBy('rj_no'),         'acte', 'acte.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjactparams')->select('rj_no', DB::raw('NVL(SUM(pact_price),0) as v'))->groupBy('rj_no'),       'actp', 'actp.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjaccdocs')->select('rj_no', DB::raw('NVL(SUM(accdoc_price),0) as v'))->groupBy('rj_no'),       'actd', 'actd.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjobats')->select('rj_no', DB::raw('NVL(SUM(qty * price),0) as v'))->groupBy('rj_no'),          'obt',  'obt.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjobatracikans')->select('rj_no', DB::raw('NVL(SUM(NVL(qty,0) * NVL(price,0)),0) as v'))->groupBy('rj_no'), 'rac', 'rac.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjlabs')->select('rj_no', DB::raw('NVL(SUM(lab_price),0) as v'))->groupBy('rj_no'),             'lab',  'lab.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjrads')->select('rj_no', DB::raw('NVL(SUM(rad_price),0) as v'))->groupBy('rj_no'),             'rad',  'rad.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjothers')->select('rj_no', DB::raw('NVL(SUM(other_price),0) as v'))->groupBy('rj_no'),         'oth',  'oth.rj_no', '=', 'h.rj_no')
            ->where('h.rj_status', 'L')
            ->whereBetween('h.rj_date', [$start, $end])
            ->selectRaw("h.dr_id as dr_id,
                d.dr_name as dr_name,
                h.poli_id as poli_id,
                p.poli_desc as poli_desc,
                COUNT(DISTINCT h.rj_no) as jumlah,
                SUM(CASE WHEN k.klaim_status = 'BPJS' THEN ({$perRjExpr}) ELSE 0 END) as bpjs,
                SUM(CASE WHEN k.klaim_status != 'BPJS' OR k.klaim_status IS NULL THEN ({$perRjExpr}) ELSE 0 END) as umum")
            ->groupBy('h.dr_id', 'd.dr_name', 'h.poli_id', 'p.poli_desc')
            ->get();

        return $rows->map(fn ($r) => [
            'dr_id'     => $r->dr_id !== null && $r->dr_id !== '' ? (string) $r->dr_id : '__NONE__',
            'dr_name'   => $r->dr_name ?: '(Tidak Ada Kategori)',
            'poli_desc' => $r->poli_desc ?: '(Tidak Ada Kategori)',
            'jumlah'    => (int) $r->jumlah,
            'bpjs'      => (int) $r->bpjs,
            'umum'      => (int) $r->umum,
            'total'     => (int) $r->bpjs + (int) $r->umum,
        ])->sortByDesc('total')->values()->all();
    }
}
