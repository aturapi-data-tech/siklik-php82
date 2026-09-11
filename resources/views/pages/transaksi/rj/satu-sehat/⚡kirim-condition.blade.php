<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-condition.blade.php
// Step 2: Kirim Diagnosa ICD-10

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\ConditionTrait;

new class extends Component {
    use EmrRJTrait, ConditionTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;

    /** Berapa diagnosa ber-ICD-10 yang TERSEDIA di EMR untuk dikirim. */
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
        $this->count = count($satuSehat['conditionIds'] ?? []);
        $this->tersedia = count($this->daftarDiagnosa($data));
    }

    /**
     * Diagnosa yang akan berangkat, dibaca dari sumber yang SAMA dengan kirimInti()
     * supaya hitungan di kartu tak bisa berbeda dari kenyataan.
     *
     * Sumber JSON: dataRJ['diagnosis'][] { icdX / diagId, diagDesc } — ditulis
     * rm-diagnosa-rj-actions. (Key lama 'diagnpinaList' / 'kodeIcdx' tidak pernah
     * ditulis siapa pun di siklik → kartu ini dulu selalu bilang "tidak ada data".)
     */
    private function daftarDiagnosa(array $dataRJ): array
    {
        $daftar = [];
        foreach ($dataRJ['diagnosis'] ?? [] as $diagnosa) {
            $kode = trim((string) ($diagnosa['icdX'] ?? ($diagnosa['diagId'] ?? '')));
            if ($kode === '') {
                continue;
            }
            $daftar[] = ['kode' => $kode, 'display' => (string) ($diagnosa['diagDesc'] ?? '')];
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
     * kabar supaya orkestrator bisa melanjutkan. Tanpa ini rantai menggantung diam-diam
     * pada langkah pertama yang gagal, dan petugas cuma melihat modal yang membeku.
     */
    #[On('ss-condition-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('rj-satu-sehat.langkah-selesai', langkah: 'condition');
    }

    public function kirimInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.'); return; }

            $satuSehat = $dataRJ['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            // Sengaja TIDAK memblokir kiriman ulang begitu ada satu id tersimpan:
            // kiriman yang separuh jalan harus bisa dilengkapi. Pengulangan aman
            // karena diagnosa yang sudah ada di SATUSEHAT dipungut id-nya, bukan
            // dibuat ulang.

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $rjDate = $this->parseDate($dataRJ['rjDate'] ?? '');
            $diagnosaList = $this->daftarDiagnosa($dataRJ);
            if (empty($diagnosaList)) { $this->dispatch('toast', type: 'error', message: 'Tidak ada data diagnosa untuk dikirim.'); return; }

            // Id lama TIDAK dibuang: yang sudah terkirim tetap dihitung, sisanya dilengkapi.
            $terkumpul = array_values($satuSehat['conditionIds'] ?? []);
            $baru = 0;
            $dipungut = 0;
            $gagal = [];

            foreach ($diagnosaList as $diagnosa) {
                $kode = $diagnosa['kode'];
                $display = $diagnosa['display'];

                // Per diagnosa, JANGAN biarkan satu kegagalan menghanguskan yang lain.
                // Dulu exception dari satu baris melompati saveResult(), sehingga
                // Condition yang SUDAH terbentuk di SATUSEHAT tidak pernah tercatat
                // id-nya — dan ronde berikutnya ia balik jadi duplikat. Sekali
                // tergelincir, macetnya permanen.
                try {
                    $respons = $this->createFinalDiagnosis([
                        'patientId' => $patientId, 'encounterId' => $satuSehat['encounterId'],
                        'icd10_code' => $kode, 'icd10_display' => $display,
                        'diagnosis_text' => "{$kode} - {$display}",
                        'recordedDate' => $rjDate->toIso8601String(),
                    ]);
                    if (!empty($respons['id'])) { $terkumpul[] = $respons['id']; $baru++; }
                } catch (\Throwable $e) {
                    if (!$this->isDuplicateError($e)) { $gagal[] = "{$kode}: " . $this->ringkasErrorSatuSehat($e); continue; }

                    // Ditolak duplikat = resource-nya memang sudah ada di sana.
                    $idLama = $this->findExistingConditionId($satuSehat['encounterId'], $kode, $terkumpul, $patientId);
                    if ($idLama !== '') { $terkumpul[] = $idLama; $dipungut++; }
                    else { $gagal[] = "{$kode}: sudah ada di SATUSEHAT tapi id-nya tidak ditemukan di encounter ini"; }
                }
            }

            // SELALU disimpan, walau ada yang gagal — inti perbaikannya di sini.
            $satuSehat['conditionIds'] = array_values(array_unique($terkumpul));
            $this->saveResult($rjNo, $satuSehat);
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);

            if (!empty($gagal)) {
                $this->dispatch('toast', type: 'error', message: 'Sebagian diagnosa gagal — ' . implode('; ', $gagal));
                return;
            }

            $pesan = "Diagnosa berhasil dikirim ({$baru} item).";
            if ($dipungut > 0) { $pesan = "Diagnosa lengkap: {$baru} baru, {$dipungut} sudah ada di SATUSEHAT dan id-nya dipulihkan."; }
            $this->dispatch('toast', type: 'success', message: $pesan);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Diagnosa gagal: ' . $this->ringkasErrorSatuSehat($e));
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
            <span class="text-sm font-bold">2</span>
        </div>
        <div>
            <div class="font-semibold text-gray-800 dark:text-gray-100">Condition</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Diagnosa / keluhan pasien (ICD-10).</div>
            <div class="mt-1 text-xs {{ $tersedia > 0 ? 'text-gray-500 dark:text-gray-400' : 'text-amber-600 dark:text-amber-400' }}">
                {{ $tersedia > 0 ? $tersedia . ' diagnosa ber-ICD-10 di EMR' : 'Belum ada diagnosa ber-ICD-10 di EMR — Kirim akan ditolak.' }}
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
