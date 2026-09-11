<?php
use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Support\TtdUser;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait;

    public ?int $rjNo = null;

    /* ===============================
     | OPEN & LANGSUNG CETAK
     =============================== */
    #[On('cetak-suket-sehat.open')]
    public function open(int $rjNo): mixed
    {
        $this->rjNo = $rjNo;

        // Ambil data JSON RJ dari DB
        $dataRJ = $this->findDataRJ($rjNo);

        if (empty($dataRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return null;
        }

        // Ambil data pasien via regNo
        $pasienData = $this->findDataMasterPasien($dataRJ['regNo'] ?? '');

        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        $pasien = $pasienData['pasien'];
        $suketSehat = $dataRJ['suket']['suketSehat'] ?? [];

        // Hitung umur realtime
        if (!empty($pasien['tglLahir'])) {
            $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                ->diff(Carbon::now(config('app.timezone')))
                ->format('%y Thn, %m Bln %d Hr');
        }

        // Ambil data dokter langsung dari DB berdasarkan drId
        $drId = $dataRJ['drId'] ?? '';
        $dokter = DB::table('skmst_doctors')->where('dr_id', $drId)->select('dr_name')->first();

        // TTD dokter dari storage (users.myuser_code == skmst_doctors.dr_id).
        // Wajib lewat TtdUser: kolom myuser_ttd_image punya DUA format
        // (path relatif & nama berkas saja) — docs/ttd-pattern-pdf-print.md §6.
        $ttdDokterPath = TtdUser::pathBerkasDariKode($drId);

        $data = array_merge($pasien, [
            'keteranganSehat' => $suketSehat['suketSehat'] ?? null,
            'namaDokter' => $dokter->dr_name ?? null,
            'ttdDokterPath' => $ttdDokterPath,
            'tglCetak' => Carbon::now()->translatedFormat('d F Y'),
        ]);

        $pdf = Pdf::loadView('pages.components.modul-dokumen.rj.suket-sehat.cetak-suket-sehat-print', [
            'data' => $data,
        ])->setPaper('A4');

        return response()->streamDownload(fn() => print $pdf->output(), 'suket-sehat-' . ($pasien['regNo'] ?? $rjNo) . '.pdf');
    }
};
?>

<div></div>
