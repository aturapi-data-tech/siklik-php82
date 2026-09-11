<?php
// Viewer read-only "Surat Keterangan" (Sehat / Istirahat) — display Rekam Medis RJ.
// Pola docs/dokumen-view-pattern.md: Lihat = render blade cetak ke iframe; payload (buatData)
// disamakan persis dengan komponen cetak ⚡cetak-suket-sehat / ⚡cetak-suket-sakit.

use Livewire\Component;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?int $rjNo = null;
    public array $suket = [];
    public string $selected = '';
    public string $previewHtml = '';

    private const JENIS = [
        'sehat' => ['judul' => 'Surat Keterangan Sehat', 'view' => 'pages.components.modul-dokumen.rj.suket-sehat.cetak-suket-sehat-print', 'berkas' => 'suket-sehat'],
        'istirahat' => ['judul' => 'Surat Keterangan Istirahat (Sakit)', 'view' => 'pages.components.modul-dokumen.rj.suket-sakit.cetak-suket-sakit-print', 'berkas' => 'suket-sakit'],
    ];

    public function mount(?int $rjNo = null, array $suket = []): void
    {
        $this->rjNo = $rjNo;
        $this->suket = $suket ?? [];
    }

    /** Sehat terisi bila keterangannya ada; istirahat bila keterangan ATAU lama harinya ada (sama dgn suketTerisi() di form). */
    public function terisi(string $jenis): bool
    {
        return $jenis === 'sehat'
            ? trim((string) data_get($this->suket, 'suketSehat.suketSehat', '')) !== ''
            : (trim((string) data_get($this->suket, 'suketIstirahat.suketIstirahat', '')) !== '' || (int) data_get($this->suket, 'suketIstirahat.suketIstirahatHari', 0) > 0);
    }

    public function judul(string $jenis): string
    {
        return self::JENIS[$jenis]['judul'] ?? 'Surat Keterangan';
    }

    public function lihat(string $id): void
    {
        $data = $this->buatData($id);
        if (!$data) {
            return;
        }
        $this->selected = $id;
        $this->previewHtml = $this->renderDokumenPreview(self::JENIS[$id]['view'], $data);
        $this->dispatch('open-modal', name: "view-suket-rj-{$this->rjNo}");
    }

    public function cetak(string $id): mixed
    {
        $data = $this->buatData($id);
        if (!$data) {
            return null;
        }
        set_time_limit(300);
        $pdf = Pdf::loadView(self::JENIS[$id]['view'], ['data' => $data])->setPaper('A4');

        return response()->streamDownload(fn() => print $pdf->output(), self::JENIS[$id]['berkas'] . '-' . ($data['regNo'] ?? $this->rjNo) . '.pdf');
    }

    private function buatData(string $jenis): ?array
    {
        if (!isset(self::JENIS[$jenis])) {
            return null;
        }
        $dataRJ = $this->rjNo ? ($this->findDataRJ($this->rjNo) ?: []) : [];
        $suket = $dataRJ['suket'] ?? [];
        if (empty($suket)) {
            $this->dispatch('toast', type: 'error', message: 'Data Surat Keterangan belum tersedia.');
            return null;
        }

        $pasien = $this->dvPasien($dataRJ['regNo'] ?? '');
        if (empty($pasien)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        // TTD di cetakan = dokter pemeriksa kunjungan (users.myuser_code == skmst_doctors.dr_id).
        $drId = $dataRJ['drId'] ?? '';
        $namaDokter = DB::table('skmst_doctors')->where('dr_id', $drId)->value('dr_name');
        $umum = [
            'namaDokter' => $namaDokter,
            'ttdDokterPath' => $this->dvTtdPath($drId ?: null),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ];

        if ($jenis === 'sehat') {
            return array_merge($pasien, $umum, [
                'keteranganSehat' => $suket['suketSehat']['suketSehat'] ?? null,
            ]);
        }

        $istirahat = $suket['suketIstirahat'] ?? [];
        // Strip suffix legacy " (Hari Ini)" / " (Besok)" supaya Carbon parse aman.
        $mulaiRaw = (string) ($istirahat['mulaiIstirahat'] ?? Carbon::now(config('app.timezone'))->format('d/m/Y'));
        $mulai = trim(preg_replace('/\s*\(.+?\)\s*$/', '', $mulaiRaw)) ?: Carbon::now(config('app.timezone'))->format('d/m/Y');
        $lamaHari = max(1, (int) ($istirahat['suketIstirahatHari'] ?? 1));
        try {
            $tglSelesai = Carbon::createFromFormat('d/m/Y', $mulai)->copy()->addDays($lamaHari - 1)->format('d/m/Y');
        } catch (\Throwable) {
            $tglSelesai = '-';
        }

        return array_merge($pasien, $umum, [
            'lamaIstirahat' => $lamaHari,
            'tglMulai' => $mulai,
            'tglSelesai' => $tglSelesai,
        ]);
    }
};
?>

<div>
    <x-border-form title="Surat Keterangan">
        @php $adaSuket = false; @endphp
        @foreach (['sehat', 'istirahat'] as $jenis)
            @if ($this->terisi($jenis))
                @php $adaSuket = true; @endphp
                <x-rm.doc-list-row wire:key="suket-rj-{{ $rjNo }}-{{ $jenis }}" :id="$jenis" :title="$this->judul($jenis)"
                    :date="$jenis === 'istirahat' ? (data_get($suket, 'suketIstirahat.suketIstirahatHari') ? data_get($suket, 'suketIstirahat.suketIstirahatHari') . ' hari' : null) : null"
                    :sub="$jenis === 'sehat' ? (data_get($suket, 'suketSehat.suketSehat') ?: null) : (data_get($suket, 'suketIstirahat.suketIstirahat') ?: null)" />
            @endif
        @endforeach
        @unless ($adaSuket)
            <x-rm.doc-empty />
        @endunless
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-suket-rj-{{ $rjNo }}" :title="$selected ? $this->judul($selected) : 'Surat Keterangan'"
        :cetakId="$selected ?: null" :previewHtml="$previewHtml" />
</div>
