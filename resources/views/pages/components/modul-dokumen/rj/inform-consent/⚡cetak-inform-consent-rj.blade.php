<?php
// resources/views/pages/components/modul-dokumen/rj/inform-consent/cetak-inform-consent-rj.blade.php

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
    public ?string $signatureDate = null;

    #[On('cetak-inform-consent-rj.open')]
    public function open(int $rjNo, ?string $signatureDate = null): mixed
    {
        $this->rjNo = $rjNo;
        $this->signatureDate = $signatureDate;

        $dataRJ = $this->findDataRJ($rjNo);
        if (empty($dataRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
            return null;
        }

        $consentList = $dataRJ['informConsentPasienRJ'] ?? [];
        if (empty($consentList)) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada Inform Consent yang tersimpan.');
            return null;
        }

        // Jika signatureDate tidak disediakan, ambil yang terbaru
        if (empty($this->signatureDate)) {
            $consent = collect($consentList)->sortByDesc('signatureDate')->first();
        } else {
            $consent = collect($consentList)->firstWhere('signatureDate', $this->signatureDate);
        }

        if (empty($consent)) {
            $this->dispatch('toast', type: 'error', message: 'Data Inform Consent yang dipilih tidak ditemukan.');
            return null;
        }

        $pasienData = $this->findDataMasterPasien($dataRJ['regNo'] ?? '');
        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        $pasien = $pasienData['pasien'];

        // Hitung umur
        if (!empty($pasien['tglLahir'])) {
            try {
                $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                    ->diff(Carbon::now(config('app.timezone')))
                    ->format('%y Thn, %m Bln %d Hr');
            } catch (\Throwable) {
                $pasien['thn'] = '-';
            }
        }

        // TTD dokter penjelas dari storage — wajib lewat TtdUser (kolom
        // myuser_ttd_image punya DUA format; lihat docs/ttd-pattern-pdf-print.md §6)
        $ttdDokterPath = TtdUser::pathBerkasDariKode($consent['dokterCode'] ?? null);

        // TTD dokter tindakan / PPA dari storage (myuser_code == dr_id)
        $ttdDokterTindakanPath = TtdUser::pathBerkasDariKode($consent['petugasPemeriksaCode'] ?? null);
        $dokterTindakanName = null;
        if (!empty($consent['petugasPemeriksaCode'])) {
            $dokterTindakanName = DB::table('users')->where('myuser_code', $consent['petugasPemeriksaCode'])->value('myuser_name');
            if (empty($dokterTindakanName)) {
                $dokterTindakanName = DB::table('skmst_doctors')->where('dr_id', $consent['petugasPemeriksaCode'])->value('dr_name');
            }
        }

        $identitasRs = DB::table('skmst_identitases')->select('int_name', 'int_phone1', 'int_address', 'int_city')->first();

        $data = array_merge($pasien, [
            'dataRJ' => $dataRJ,
            'consent' => $consent,
            'identitasRs' => $identitasRs,
            'ttdDokterPath' => $ttdDokterPath,
            'ttdDokterTindakanPath' => $ttdDokterTindakanPath,
            'dokterTindakanName' => $dokterTindakanName ?? ($consent['petugasPemeriksa'] ?? null),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);

        set_time_limit(300);

        $pdf = Pdf::loadView('pages.components.modul-dokumen.rj.inform-consent.cetak-inform-consent-rj-print', ['data' => $data])->setPaper('A4');

        return response()->streamDownload(fn() => print $pdf->output(), 'inform-consent-rj-' . ($pasien['regNo'] ?? $rjNo) . '.pdf');
    }
};
?>
<div></div>
