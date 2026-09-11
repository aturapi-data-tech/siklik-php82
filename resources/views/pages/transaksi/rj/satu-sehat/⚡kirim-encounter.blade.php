<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-encounter.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\SATUSEHAT\EncounterTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait, EncounterTrait;

    public ?string $rjNo = null;
    public ?string $encounterId = null;
    public bool $encounterInProgress = false;
    public bool $encounterFinished = false;

    /** Sudah ada Condition terkirim? Finish mensyaratkannya (RuleNumber 10457). */
    public bool $adaDiagnosaTerkirim = false;

    /** Tanggal kunjungan apa adanya dari basis data — kosong = Kirim akan ditolak. */
    public string $tanggalKunjungan = '';

    /**
     * Kartu mana yang dirender: 'kirim' (langkah 1, paling atas), 'selesai'
     * (Selesaikan Encounter, dipasang paling bawah), atau 'semua' (keduanya —
     * bentuk yang dipakai modal saat ini). Satu komponen dua kartu supaya logika
     * Encounter tetap di satu berkas; induk tinggal merender dua kali dengan
     * $bagian berbeda ketika urutan kartu perlu dipisah.
     */
    public string $bagian = 'semua';

    public function mount(?string $rjNo = null, string $bagian = 'semua'): void
    {
        $this->rjNo = $rjNo;
        $this->bagian = in_array($bagian, ['kirim', 'selesai', 'semua'], true) ? $bagian : 'semua';
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
        $this->encounterId = $satuSehat['encounterId'] ?? null;
        $this->encounterInProgress = !empty($satuSehat['encounterInProgress']);
        $this->encounterFinished = !empty($satuSehat['encounterFinished']);
        $this->adaDiagnosaTerkirim = !empty($satuSehat['conditionIds']);
        $this->tanggalKunjungan = trim((string) ($data['rjDate'] ?? ''));
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    public function finishForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->finish($this->rjNo);
        $this->reloadState();
    }

    /**
     * Pembungkus untuk rantai "Kirim Semua" (rencana potongan B): apa pun hasilnya —
     * berhasil, ditolak SATUSEHAT, atau berhenti di guard — langkah ini WAJIB memberi
     * kabar supaya orkestrator bisa melanjutkan. Tanpa ini rantai menggantung diam-diam
     * pada langkah pertama yang gagal, dan petugas cuma melihat modal yang membeku.
     */
    #[On('ss-encounter-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('rj-satu-sehat.langkah-selesai', langkah: 'encounter');
    }

    public function kirimInti(string $rjNo): void
    {
        $satuSehat = null;
        try {
            $this->initializeSatuSehat();
            [$dataRJ, $pasien, $satuSehat] = $this->loadData($rjNo);

            // Ambil 3 IHS (patient/dokter/poli) dari DB — pola sama rujukan-kompetensi.
            // Patient UUID registration (SATUSEHAT) di-handle di master-pasien,
            // BUKAN di sini. Kalau kosong, arahkan petugas ke master-pasien dulu.
            $regNo = $dataRJ['regNo'] ?? '';
            $patientId = $regNo ? (string) (DB::table('skmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '') : '';

            $drId = $dataRJ['drId'] ?? '';
            $practitionerId = $drId ? (string) (DB::table('skmst_doctors')->where('dr_id', $drId)->value('dr_uuid') ?? '') : '';

            $poliId = $dataRJ['poliId'] ?? '';
            $locationId = $poliId ? (string) (DB::table('skmst_polis')->where('poli_id', $poliId)->value('poli_uuid') ?? '') : '';

            if (empty($patientId)) {
                $this->dispatch('toast', type: 'error',
                    message: 'Patient IHS Number kosong. Daftarkan pasien ke SATUSEHAT dulu via Master Pasien (tombol "Update patientUuid").');
                return;
            }
            if (empty($practitionerId)) {
                $this->dispatch('toast', type: 'error', message: 'Dokter IHS Number kosong.');
                return;
            }
            if (empty($locationId)) {
                $this->dispatch('toast', type: 'error', message: 'Poli IHS Number kosong.');
                return;
            }

            // Tanggal kunjungan kosong = parseDate() diam-diam memakai now(), sehingga
            // period.start terisi JAM PETUGAS MENEKAN TOMBOL, bukan jam kunjungan. Waktu
            // itu lalu DIBEKUKAN di SATUSEHAT begitu Encounter terbentuk dan tak bisa
            // dikoreksi belakangan. Akibatnya berantai: Finish memakai jam layanan
            // sesungguhnya (taskId7/taskId5) yang bisa lebih awal -> melanggar constraint
            // start<=end, dan seluruh resource yang menempel ikut salah waktu.
            // Lebih baik ditolak di sini supaya tanggalnya dibetulkan lebih dulu.
            $tanggalKunjungan = trim((string) ($dataRJ['rjDate'] ?? ''));
            if ($tanggalKunjungan === '') {
                $this->dispatch('toast', type: 'error',
                    message: 'Tanggal kunjungan (rj_date) kosong — betulkan dulu di pendaftaran sebelum kirim Encounter.');
                return;
            }

            $rjDate = $this->parseDate($tanggalKunjungan);

            if (empty($satuSehat['encounterId'])) {
                $respons = $this->createNewEncounter([
                    'encounterId' => 'RJ-' . $rjNo,
                    'patientId' => $patientId,
                    'patientName' => $pasien['regName'] ?? '',
                    'practitionerId' => $practitionerId,
                    'practitionerName' => $dataRJ['drDesc'] ?? '',
                    'locationId' => $locationId,
                    'class_code' => 'AMB',
                    'startDate' => $rjDate->toIso8601String(),
                ]);
                $satuSehat['encounterId'] = $respons['id'] ?? null;
            }

            if (!empty($satuSehat['encounterId']) && empty($satuSehat['encounterInProgress'])) {
                $this->startRoomEncounter($satuSehat['encounterId'], [
                    'startDate' => $rjDate->toIso8601String(),
                    'locationId' => $locationId,
                ]);
                $satuSehat['encounterInProgress'] = true;
            }

            $this->saveResult($rjNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Encounter berhasil dikirim: ' . ($satuSehat['encounterId'] ?? '-'));
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            // Encounter yang sudah terbentuk TIDAK boleh hangus id-nya: kalau
            // startRoomEncounter() yang gagal, encounterId sudah ada di tangan.
            try { if (!empty($satuSehat['encounterId'])) { $this->saveResult($rjNo, $satuSehat); } } catch (\Throwable) {}
            $this->dispatch('toast', type: 'error', message: 'Encounter gagal: ' . $this->ringkasErrorSatuSehat($e));
        }
    }

    /** Pembungkus rantai — lihat catatan di kirim(). */
    #[On('ss-encounter-rj.finish')]
    public function finish(string $rjNo): void
    {
        $this->finishInti($rjNo);
        $this->dispatch('rj-satu-sehat.langkah-selesai', langkah: 'encounter-selesai');
    }

    public function finishInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            [$dataRJ, , $satuSehat] = $this->loadData($rjNo);

            if (empty($satuSehat['encounterId'])) {
                $this->dispatch('toast', type: 'error', message: 'Encounter belum dibuat.');
                return;
            }
            if (!empty($satuSehat['encounterFinished'])) {
                $this->dispatch('toast', type: 'info', message: 'Encounter sudah finished.');
                return;
            }

            // Waktu selesai = jam layanan berakhir, bukan now() (jam petugas mengklik):
            // task 7 (obat diserahkan), atau task 5 (keluar poli) bila tak ada obat.
            $taskIdPelayanan = $dataRJ['taskIdPelayanan'] ?? [];
            $waktuSelesai = trim((string) ($taskIdPelayanan['taskId7'] ?? '')) ?: trim((string) ($taskIdPelayanan['taskId5'] ?? ''));
            $akhirIso = $waktuSelesai !== ''
                ? $this->parseDate($waktuSelesai)->toIso8601String()
                : now()->toIso8601String();

            // Encounter.diagnosis wajib (RuleNumber 10457) dan harus merujuk Condition yang
            // sudah dikirim — tolak lebih dulu, jangan kirim lalu pasti ditolak server.
            $conditionIdList = $satuSehat['conditionIds'] ?? [];
            if (empty($conditionIdList)) {
                $this->dispatch('toast', type: 'error',
                    message: 'Kirim Diagnosa (Condition) dulu — SATUSEHAT mewajibkan Encounter.diagnosis saat finish.');
                return;
            }

            // statusHistory tiap entri wajib start+end (Rule 10122), dan period.end tak
            // boleh mendahului period.start yang dibekukan SATUSEHAT — dirapikan di trait.
            $encounterTersimpan = $this->siapkanFinishEncounter(
                $this->getEncounter($satuSehat['encounterId']), $akhirIso, $conditionIdList
            );
            $this->makeRequest('put', "Encounter/{$satuSehat['encounterId']}", $encounterTersimpan);

            $satuSehat['encounterFinished'] = true;

            $this->saveResult($rjNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Encounter finished.');
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Finish Encounter gagal: ' . $this->ringkasErrorSatuSehat($e));
        }
    }

    private function loadData(string $rjNo): array
    {
        $dataRJ = $this->findDataRJ($rjNo);
        if (empty($dataRJ)) {
            throw new \RuntimeException('Data RJ tidak ditemukan.');
        }
        $pasienData = $this->findDataMasterPasien($dataRJ['regNo'] ?? '');
        $pasien = $pasienData['pasien'] ?? [];
        return [$dataRJ, $pasien, $dataRJ['satusehat'] ?? []];
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
        if (empty($teksTanggal)) {
            return Carbon::now();
        }
        try {
            return Carbon::createFromFormat('d/m/Y H:i:s', $teksTanggal);
        } catch (\Throwable) {
            try {
                return Carbon::parse($teksTanggal);
            } catch (\Throwable) {
                return Carbon::now();
            }
        }
    }
};
?>

<div class="space-y-3">
    @if ($bagian !== 'selesai')
        {{-- Step 1: Encounter --}}
        <div
            class="flex items-center justify-between p-4 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <div
                    class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($encounterId) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
                    <span class="text-sm font-bold">1</span>
                </div>
                <div>
                    <div class="font-semibold text-gray-800 dark:text-gray-100">Encounter</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Kunjungan pasien (AMB / rawat jalan).</div>
                    @if ($tanggalKunjungan === '')
                        <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                            Tanggal kunjungan kosong — betulkan di pendaftaran, Kirim akan ditolak.
                        </div>
                    @else
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Mulai (period.start): {{ $tanggalKunjungan }}
                        </div>
                    @endif
                    @if (!empty($encounterId))
                        <div class="mt-1 font-mono text-xs text-emerald-600 dark:text-emerald-400">
                            ID: {{ $encounterId }}
                        </div>
                    @endif
                </div>
            </div>
            <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled"
                class="!bg-teal-600 hover:!bg-teal-700 {{ !empty($encounterId) ? '!bg-emerald-600' : '' }}">
                <span wire:loading.remove wire:target="kirimForCurrent,kirim">
                    {{ !empty($encounterId) ? 'Terkirim' : 'Kirim' }}
                </span>
                <span wire:loading wire:target="kirimForCurrent,kirim"><x-loading />...</span>
            </x-primary-button>
        </div>
    @endif

    {{-- Selesaikan Encounter — muncul setelah encounter ada. --}}
    @if ($bagian !== 'kirim' && !empty($encounterId))
        <div
            class="flex items-center justify-between p-4 bg-white border-2 border-teal-300 shadow-sm rounded-xl dark:bg-gray-900 dark:border-teal-700">
            <div class="flex items-center gap-3">
                <div
                    class="flex items-center justify-center w-8 h-8 rounded-full {{ $encounterFinished ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-teal-100 text-teal-600 dark:bg-teal-900/30 dark:text-teal-400' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-gray-800 dark:text-gray-100">Selesaikan Encounter</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Update status encounter menjadi finished.
                    </div>
                    @unless ($encounterFinished)
                        @if (!$adaDiagnosaTerkirim)
                            <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                Kirim Diagnosa (Condition) dulu — Encounter.diagnosis wajib saat finish.
                            </div>
                        @endif
                    @endunless
                </div>
            </div>
            <x-primary-button type="button" wire:click="finishForCurrent" wire:loading.attr="disabled"
                class="{{ $encounterFinished ? '!bg-emerald-600' : '!bg-teal-600 hover:!bg-teal-700' }}">
                <span wire:loading.remove wire:target="finishForCurrent,finish">
                    {{ $encounterFinished ? 'Selesai' : 'Finish' }}
                </span>
                <span wire:loading wire:target="finishForCurrent,finish"><x-loading />...</span>
            </x-primary-button>
        </div>
    @endif
</div>
