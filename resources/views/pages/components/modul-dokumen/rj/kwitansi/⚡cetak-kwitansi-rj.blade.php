<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;

new class extends Component {
    use EmrRJTrait;

    /* ═══════════════════════════════════════
     | OPEN & LANGSUNG CETAK
    ═══════════════════════════════════════ */
    #[On('cetak-kwitansi.open')]
    public function open(int $rjNo): mixed
    {
        // ── Query 1: Header RJ + Data Pasien ──
        $hdr = DB::selectOne(
            "
            SELECT
                a.rj_no,
                TO_CHAR(a.rj_date, 'DD/MM/YYYY HH24:MI') AS rj_date,
                a.reg_no,
                b.reg_name,
                b.address,
                b.sex,
                TO_CHAR(b.birth_date, 'DD/MM/YYYY')       AS birth_date,
                a.kasir_id,
                a.rj_diskon,
                a.klaim_id,
                a.dr_id,
                a.vno_sep,
                a.poli_id
            FROM  sktxn_rjhdrs  a
            JOIN  skmst_pasiens b ON b.reg_no = a.reg_no
            WHERE a.rj_no = :rjno
            ",
            ['rjno' => $rjNo],
        );

        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return null;
        }

        // ── Query 2: Rincian Biaya ──
        $rincian = DB::select(
            "
            SELECT txn_id, txn_desc, txn_nominal, txn_no
            FROM   skview_rjstrs
            WHERE  rj_no       = :rjno
              AND  txn_nominal > 0
            ORDER  BY txn_no
            ",
            ['rjno' => $rjNo],
        );

        // ── Kalkulasi Biaya ──
        $subtotal = (int) collect($rincian)->sum('txn_nominal');
        $diskon = (int) ($hdr->rj_diskon ?? 0);
        $grandTotal = max(0, $subtotal - $diskon);

        // ── Bayar dari riwayat kasir ──
        $sudahBayar = (int) DB::table('sktxn_rjcashins')->where('rj_no', $rjNo)->sum('rjc_nominal');

        // ── Sisa tagihan ──
        $sisa = max(0, $grandTotal - $sudahBayar);

        // ── Nama Kasir dari SKMST_KASIRS via kasir_id di header (siklik) ──
        $kasirName = null;
        if (!empty($hdr->kasir_id)) {
            $kasirName = DB::table('skmst_kasirs')->where('kasir_id', $hdr->kasir_id)->value('kasir_name');
        }

        // ── Data JSON RJ ──
        $dataRJ = $this->findDataRJ($rjNo) ?? [];

        // ── Klaim ──
        $klaimRow = DB::table('skmst_klaimtypes')
            ->where('klaim_id', $hdr->klaim_id ?? '')
            ->select('klaim_desc')
            ->first();

        $klaimName = $klaimRow->klaim_desc ?? ($hdr->klaim_id ?? '-');

        // ── Deteksi BPJS ──
        $isBpjs = ($dataRJ['klaimStatus'] ?? '') === 'BPJS' || ($dataRJ['klaimId'] ?? '') === 'JM';

        // ── Poli ──
        $poliDesc =
            DB::table('skmst_polis')
                ->where('poli_id', $hdr->poli_id ?? '')
                ->value('poli_desc') ??
            ($dataRJ['poliName'] ?? '-');

        // ── Dokter ──
        $drName =
            DB::table('skmst_doctors')
                ->where('dr_id', $hdr->dr_id ?? '')
                ->value('dr_name') ??
            ($hdr->dr_id ?? '-');

        // ── Data SEP BPJS ──
        $sepData = null;
        if ($isBpjs && !empty($hdr->vno_sep)) {
            $sepData = [
                'noSep' => $hdr->vno_sep,
                'noReferensi' => $dataRJ['noReferensi'] ?? null,
                'masaRujukan' => null,
            ];

            $tglRujukan = $dataRJ['sep']['reqSep']['request']['t_sep']['rujukan']['tglRujukan'] ?? null;
            if ($tglRujukan) {
                $tgl = Carbon::parse($tglRujukan);
                $batas = $tgl->copy()->addMonths(3);
                $sepData['masaRujukan'] = [
                    'tglMulai' => $tgl->format('d/m/Y'),
                    'tglAkhir' => $batas->format('d/m/Y'),
                    'sisaHari' => (int) now()->diffInDays($batas, false),
                ];
            }
        }

        $data = [
            // ── Pasien ──
            'regNo' => $hdr->reg_no,
            'regName' => $hdr->reg_name,
            'address' => $hdr->address,
            'sex' => $hdr->sex,
            'birthDate' => $hdr->birth_date ?? '-',

            // ── Kunjungan ──
            'rjNo' => $rjNo,
            'rjDate' => $hdr->rj_date ?? '-',
            'rjDiskon' => $diskon,
            'vnoSep' => $hdr->vno_sep,
            'drId' => $hdr->dr_id ?? '-',
            'drName' => $drName,
            'poliName' => $poliDesc,
            'klaimId' => $hdr->klaim_id ?? '-',
            'klaimName' => $klaimName,
            'isBpjs' => $isBpjs,

            // ── SEP ──
            'sep' => $sepData,

            // ── Biaya ──
            'rincian' => $rincian,
            'subtotal' => $subtotal,
            'grandTotal' => $grandTotal,
            'sudahBayar' => $sudahBayar,
            'sisa' => $sisa,

            // ── Kasir / cetak ──
            'kasirName' => $kasirName, // ✅ dari skmst_kasirs
            'kasirLog' => $dataRJ['AdministrasiRj'] ?? null,
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d/m/Y'),
            'jamCetak' => Carbon::now(config('app.timezone'))->format('H:i'),
            'cetakOleh' => auth()->user()->myuser_name ?? '-',
        ];

        // ── Generate PDF ──
        $pdf = Pdf::loadView('pages.components.modul-dokumen.rj.kwitansi.cetak-kwitansi-rj-print', ['data' => $data])->setPaper('A4');

        $filename = 'kwitansi-' . ($hdr->reg_no ?? $rjNo) . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename, ['Content-Type' => 'application/pdf']);
    }
};
?>
<div></div>
