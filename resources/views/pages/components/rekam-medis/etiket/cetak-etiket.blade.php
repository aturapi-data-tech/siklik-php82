<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use MasterPasienTrait;

    public ?string $regNo = null;

    /* ===============================
     | OPEN & LANGSUNG CETAK
     =============================== */
    #[On('cetak-etiket.open')]
    public function open(string $regNo): mixed
    {
        $this->regNo = $regNo;

        $pasienData = $this->findDataMasterPasien($regNo);

        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        $dataPasien = $pasienData['pasien'];

        // Hitung umur realtime (model etiket lama hanya pakai tahun, mis. "63 tahun")
        if (!empty($dataPasien['tglLahir'])) {
            $umur = Carbon::createFromFormat('d/m/Y', $dataPasien['tglLahir'])->diff(Carbon::now(env('APP_TIMEZONE')));
            $dataPasien['thn'] = $umur->format('%y Thn, %m Bln %d Hr');
            $dataPasien['umurTahun'] = $umur->y;
        }

        // Nama instansi dari master identitas (jangan hardcode)
        $klinikName = DB::table('dimst_identitases')->value('int_name') ?: config('app.name');

        // Langsung cetak
        $pdf = Pdf::loadView('pages.components.rekam-medis.etiket.cetak-etiket-print', [
            'data' => $dataPasien,
            'klinikName' => $klinikName,
        ])->setPaper([0, 0, 170.08, 113.39]); // 6cm x 4cm dalam points

        return response()->streamDownload(fn() => print $pdf->output(), 'etiket-' . ($dataPasien['regNo'] ?? $regNo) . '.pdf');
    }
};

?>

<div></div>
