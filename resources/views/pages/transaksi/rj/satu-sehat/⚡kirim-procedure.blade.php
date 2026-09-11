<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-procedure.blade.php
// Step 4: Kirim Tindakan ICD-9-CM

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\ProcedureTrait;

new class extends Component {
    use EmrRJTrait, ProcedureTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;

    /** Berapa tindakan ber-ICD-9-CM yang TERSEDIA di EMR untuk dikirim. */
    public int $tersedia = 0;

    public function mount(?string $rjNo = null): void
    {
        $this->rjNo = $rjNo;
        $this->reloadState();
    }

    #[On('rj-satu-sehat.refresh')]
    public function onRefresh(string $rjNo): void
    {
        if ((string) $this->rjNo !== $rjNo) {
            return;
        }
        $this->reloadState();
    }

    private function reloadState(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $data = $this->findDataRJ($this->rjNo);
        if (empty($data)) {
            return;
        }
        $satuSehat = $data['satusehat'] ?? [];
        $this->hasEncounter = !empty($satuSehat['encounterId']);
        $this->count = count($satuSehat['procedureIds'] ?? []);
        $this->tersedia = count($this->daftarTindakan($data));
    }

    /**
     * Tindakan yang akan berangkat, dibaca dari sumber yang SAMA dengan kirimInti().
     *
     * Sumber JSON: dataRJ['procedure'][] { procedureId = kode ICD-9-CM, procedureDesc }
     * — ditulis rm-diagnosa-rj-actions, dipakai juga cetak rekam medis & surat rujukan.
     * (Key lama 'tindakanList'/'kodeIcd9' tidak pernah ditulis siapa pun di siklik →
     * kartu ini dulu selalu bilang "tidak ada data tindakan".)
     */
    private function daftarTindakan(array $dataRJ): array
    {
        $daftar = [];
        foreach ($dataRJ['procedure'] ?? [] as $tindakan) {
            $kode = trim((string) ($tindakan['procedureId'] ?? ''));
            if ($kode === '') {
                continue;
            }
            $daftar[] = ['kode' => $kode, 'display' => (string) ($tindakan['procedureDesc'] ?? '')];
        }

        return $daftar;
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    /**
     * Pembungkus untuk rantai "Kirim Semua" (rencana potongan B): apa pun hasilnya —
     * berhasil, ditolak SATUSEHAT, atau berhenti di guard — langkah ini WAJIB memberi
     * kabar supaya orkestrator bisa melanjutkan.
     */
    #[On('ss-procedure-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('rj-satu-sehat.langkah-selesai', langkah: 'procedure');
    }

    public function kirimInti(string $rjNo): void
    {
        $satuSehat = null;
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.'); return; }

            $satuSehat = $dataRJ['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['procedureIds'])) { $this->dispatch('toast', type: 'info', message: 'Tindakan sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('skmst_doctors')->where('dr_id', $dataRJ['drId'] ?? '')->value('dr_uuid') ?? '');
            $rjDate = $this->parseDate($dataRJ['rjDate'] ?? '');

            $tindakanList = $this->daftarTindakan($dataRJ);
            if (empty($tindakanList)) { $this->dispatch('toast', type: 'error', message: 'Tidak ada data tindakan.'); return; }

            $satuSehat['procedureIds'] = [];
            foreach ($tindakanList as $tindakan) {
                $respons = $this->createProcedure([
                    'patientId' => $patientId, 'encounterId' => $satuSehat['encounterId'], 'performerId' => $practitionerId,
                    'code' => $tindakan['kode'], 'display' => $tindakan['display'], 'codeSystem' => 'http://hl7.org/fhir/sid/icd-9-cm',
                    'performedDateTime' => $rjDate->toIso8601String(),
                ]);
                if (!empty($respons['id'])) $satuSehat['procedureIds'][] = $respons['id'];
            }

            $this->saveResult($rjNo, $satuSehat);
            $count = count($satuSehat['procedureIds']);
            $this->dispatch('toast', type: 'success', message: "Tindakan berhasil dikirim ({$count} item).");
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            // Simpan dulu yang sudah TERLANJUR terbentuk di SATUSEHAT sebelum melapor
            // gagal. Tanpa ini id-nya hangus padahal resource-nya SUDAH ada di sana,
            // lalu percobaan berikutnya menumpuk resource yatim — persis penyebab
            // diagnosa macet permanen dulu (lihat kartu Condition). Dibungkus try
            // sendiri supaya kegagalan menyimpan tidak menutupi error aslinya.
            try { if (!empty($satuSehat['procedureIds'])) { $this->saveResult($rjNo, $satuSehat); } } catch (\Throwable) {}
            $this->dispatch('toast', type: 'error', message: 'Tindakan gagal: ' . $this->ringkasErrorSatuSehat($e));
        }
    }

    private function getPatientIHS(string $regNo): string
    {
        if (empty($regNo)) return '';
        return (string) (DB::table('skmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '');
    }

    private function saveResult(string $rjNo, array $satuSehat): void
    {
        DB::transaction(function () use ($rjNo, $satuSehat) {
            $this->lockRJRow($rjNo);
            $data = $this->findDataRJ($rjNo);
            $data['satusehat'] = $satuSehat;
            $this->updateJsonRJ($rjNo, $data);
        });
    }

    private function parseDate(string $teksTanggal): Carbon
    {
        if (empty($teksTanggal)) return Carbon::now();
        try { return Carbon::createFromFormat('d/m/Y H:i:s', $teksTanggal); } catch (\Throwable) {
            try { return Carbon::parse($teksTanggal); } catch (\Throwable) { return Carbon::now(); }
        }
    }
};
?>

<div class="flex items-center justify-between p-4 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center gap-3">
        <div
            class="flex items-center justify-center w-8 h-8 rounded-full {{ $count > 0 ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
            <span class="text-sm font-bold">4</span>
        </div>
        <div>
            <div class="font-semibold text-gray-800 dark:text-gray-100">Procedure</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Tindakan medis (ICD-9-CM).</div>
            <div class="mt-1 text-xs {{ $tersedia > 0 ? 'text-gray-500 dark:text-gray-400' : 'text-amber-600 dark:text-amber-400' }}">
                {{ $tersedia > 0 ? $tersedia . ' tindakan ber-ICD-9-CM di EMR' : 'Belum ada tindakan ber-ICD-9-CM di EMR — Kirim akan ditolak.' }}
            </div>
            @if ($count > 0)
                <div class="mt-1 font-mono text-xs text-emerald-600 dark:text-emerald-400">
                    {{ $count }} terkirim
                </div>
            @endif
        </div>
    </div>
    <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter"
        class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 ? '!bg-emerald-600' : '' }}">
        <span wire:loading.remove wire:target="kirimForCurrent,kirim">{{ $count > 0 ? 'Terkirim' : 'Kirim' }}</span>
        <span wire:loading wire:target="kirimForCurrent,kirim"><x-loading />...</span>
    </x-primary-button>
</div>
