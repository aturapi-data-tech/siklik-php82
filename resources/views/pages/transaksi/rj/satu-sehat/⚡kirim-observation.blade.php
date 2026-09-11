<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-observation.blade.php
// Step 3: Kirim Tanda Vital

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;

new class extends Component {
    use EmrRJTrait, ObservationTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;

    /** Berapa Observation yang TERSEDIA (nilai vital terisi) untuk dikirim. */
    public int $tersedia = 0;

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat modal berisi banyak kartu. */
    public bool $pratinjauTerbuka = false;

    /**
     * Vital tunggal yang dikirim sebagai satu Observation masing-masing. Dijadikan
     * konstanta supaya hitungan kartu, pratinjau, dan payload kirim membaca daftar
     * yang SAMA — dulu tiga tempat menulis key-nya sendiri-sendiri dan kartu bisa
     * mengaku siap padahal pengirimnya membaca key lain.
     *
     * Key JSON EMR: pemeriksaan.tandaVital.{frekuensiNadi,suhu,frekuensiNafas,spo2}
     * (bukan nadi/rr/respirasi — key itu tak pernah ditulis siapa pun di siklik).
     */
    private const VITAL_TUNGGAL = [
        ['kunci' => 'frekuensiNadi',  'loinc' => '8867-4',  'display' => 'Heart rate',       'unit' => 'beats/minute',   'ucum' => '/min'],
        ['kunci' => 'suhu',           'loinc' => '8310-5',  'display' => 'Body temperature', 'unit' => 'C',              'ucum' => 'Cel'],
        ['kunci' => 'frekuensiNafas', 'loinc' => '9279-1',  'display' => 'Respiratory rate', 'unit' => 'breaths/minute', 'ucum' => '/min'],
        ['kunci' => 'spo2',           'loinc' => '59408-5', 'display' => 'Oxygen saturation in Arterial blood by Pulse oximetry', 'unit' => '%', 'ucum' => '%'],
    ];

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /** Observation yang AKAN berangkat, dari node yang SAMA dengan kirimInti(). */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }

        $dataRJ = $this->findDataRJ($this->rjNo);
        if (empty($dataRJ)) {
            return [];
        }

        $tandaVital = $dataRJ['pemeriksaan']['tandaVital'] ?? [];
        $baris = [];

        if (!empty($tandaVital['sistolik']) && !empty($tandaVital['distolik'])) {
            $baris[] = [
                'label' => 'Tekanan darah',
                'nilai' => $tandaVital['sistolik'] . '/' . $tandaVital['distolik'] . ' mm[Hg]',
                'ket' => 'LOINC 85354-9 (panel: 8480-6 sistolik, 8462-4 diastolik)',
            ];
        }

        foreach (self::VITAL_TUNGGAL as $vital) {
            if (empty($tandaVital[$vital['kunci']])) {
                continue;
            }
            $baris[] = [
                'label' => $vital['display'],
                'nilai' => $tandaVital[$vital['kunci']] . ' ' . $vital['unit'],
                'ket' => 'LOINC ' . $vital['loinc'],
            ];
        }

        if (empty($baris)) {
            return [];
        }

        array_unshift($baris, [
            'label' => 'resourceType',
            'nilai' => 'Observation',
            'ket' => 'encounter ' . ($dataRJ['satusehat']['encounterId'] ?? '(belum ada)'),
        ]);

        return $baris;
    }

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
        $this->count = count($satuSehat['observationIds'] ?? []);

        // Hitung Observation yang benar-benar akan berangkat: panel tekanan darah
        // (butuh sistolik DAN distolik) + tiap vital tunggal yang nilainya terisi.
        $tandaVital = $data['pemeriksaan']['tandaVital'] ?? [];
        $tersedia = (!empty($tandaVital['sistolik']) && !empty($tandaVital['distolik'])) ? 1 : 0;
        foreach (self::VITAL_TUNGGAL as $vital) {
            if (!empty($tandaVital[$vital['kunci']])) {
                $tersedia++;
            }
        }
        $this->tersedia = $tersedia;
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
    #[On('ss-observation-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('rj-satu-sehat.langkah-selesai', langkah: 'observation');
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
            if (!empty($satuSehat['observationIds'])) { $this->dispatch('toast', type: 'info', message: 'Tanda vital sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('skmst_doctors')->where('dr_id', $dataRJ['drId'] ?? '')->value('dr_uuid') ?? '');
            $rjDate = $this->parseDate($dataRJ['rjDate'] ?? '');
            $isoDate = $rjDate->toIso8601String();

            // Tanda vital RJ bersarang di pemeriksaan.tandaVital — key yang sama dengan
            // yang dibaca EmrCompletenessRJTrait dan cetak rekam medis. Tidak ada node
            // 'pemeriksaanFisik' maupun 'tandaVital' di akar JSON RJ, jadi pembacaan lama
            // selalu berakhir "Tidak ada data tanda vital".
            $tandaVital = $dataRJ['pemeriksaan']['tandaVital'] ?? [];
            if (empty($tandaVital)) { $this->dispatch('toast', type: 'error', message: 'Tidak ada data tanda vital.'); return; }

            $satuSehat['observationIds'] = [];
            $payloadDasar = ['patientId' => $patientId, 'encounterId' => $satuSehat['encounterId'], 'performerId' => $practitionerId, 'effectiveDate' => $isoDate];

            // Tekanan darah — key JSON EMR: sistolik / distolik (bukan sistole/diastole).
            $sistolik = $tandaVital['sistolik'] ?? null; $distolik = $tandaVital['distolik'] ?? null;
            if (!empty($sistolik) && !empty($distolik)) {
                $respons = $this->createObservation(array_merge($payloadDasar, [
                    'code' => ['system' => 'http://loinc.org', 'code' => '85354-9', 'display' => 'Blood pressure panel with all children optional'],
                    'components' => [
                        ['code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8480-6', 'display' => 'Systolic blood pressure']]], 'valueQuantity' => ['value' => (float) $sistolik, 'unit' => 'mm[Hg]', 'system' => 'http://unitsofmeasure.org', 'code' => 'mm[Hg]']],
                        ['code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8462-4', 'display' => 'Diastolic blood pressure']]], 'valueQuantity' => ['value' => (float) $distolik, 'unit' => 'mm[Hg]', 'system' => 'http://unitsofmeasure.org', 'code' => 'mm[Hg]']],
                    ],
                ]));
                if (!empty($respons['id'])) $satuSehat['observationIds'][] = $respons['id'];
            }

            // Nadi, Suhu, Pernapasan, SpO2 — daftarnya di konstanta VITAL_TUNGGAL,
            // sumber tunggal yang juga dibaca hitungan kartu dan pratinjau.
            foreach (self::VITAL_TUNGGAL as $vital) {
                $nilaiVital = $tandaVital[$vital['kunci']] ?? null;
                if (empty($nilaiVital)) continue;
                $respons = $this->createObservation(array_merge($payloadDasar, [
                    'code' => ['system' => 'http://loinc.org', 'code' => $vital['loinc'], 'display' => $vital['display']],
                    'valueQuantity' => ['value' => (float) $nilaiVital, 'unit' => $vital['unit'], 'system' => 'http://unitsofmeasure.org', 'code' => $vital['ucum']],
                ]));
                if (!empty($respons['id'])) $satuSehat['observationIds'][] = $respons['id'];
            }

            if (empty($satuSehat['observationIds'])) { $this->dispatch('toast', type: 'error', message: 'Tidak ada nilai vital valid untuk dikirim.'); return; }

            $this->saveResult($rjNo, $satuSehat);
            $count = count($satuSehat['observationIds']);
            $this->dispatch('toast', type: 'success', message: "Tanda vital berhasil dikirim ({$count} item).");
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            // Simpan dulu yang sudah TERLANJUR terbentuk di SATUSEHAT sebelum melapor
            // gagal. Tanpa ini id-nya hangus padahal resource-nya SUDAH ada di sana,
            // lalu percobaan berikutnya menumpuk resource yatim. Dibungkus try sendiri
            // supaya kegagalan menyimpan tidak menutupi error aslinya.
            try { if (!empty($satuSehat['observationIds'])) { $this->saveResult($rjNo, $satuSehat); } } catch (\Throwable) {}
            $this->dispatch('toast', type: 'error', message: 'Tanda vital gagal: ' . $this->ringkasErrorSatuSehat($e));
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

<div class="p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center justify-between">
    <div class="flex items-center gap-3">
        <div
            class="flex items-center justify-center w-8 h-8 rounded-full {{ $count > 0 ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
            <span class="text-sm font-bold">3</span>
        </div>
        <div>
            <div class="font-semibold text-gray-800 dark:text-gray-100">Observation</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Tanda vital (TD, nadi, suhu, napas, SpO2).</div>
            <div class="mt-1 text-xs {{ $tersedia > 0 ? 'text-gray-500 dark:text-gray-400' : 'text-amber-600 dark:text-amber-400' }}">
                {{ $tersedia > 0 ? $tersedia . ' nilai vital siap dikirim' : 'Belum ada tanda vital di EMR — Kirim akan ditolak.' }}
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
        <span wire:loading.remove wire:target="kirimForCurrent,kirim">
            <span class="inline-flex items-center gap-1.5">
                <x-satu-sehat.ikon-tombol :selesai="$count > 0" jenis="kirim" />
                {{ $count > 0 ? 'Terkirim' : 'Kirim' }}
            </span>
        </span>
        <span wire:loading wire:target="kirimForCurrent,kirim"><x-loading />...</span>
    </x-primary-button>
    </div>

    <x-satu-sehat.pratinjau :terbuka="$pratinjauTerbuka" :baris="$pratinjauTerbuka ? $this->pratinjau : []"
        kosong="Belum ada tanda vital di EMR (pemeriksaan.tandaVital) — Kirim akan ditolak." />
</div>
