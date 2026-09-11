<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-medication-dispense.blade.php
// Step 6: Kirim Obat Diserahkan (MedicationDispense) — sesudah MedicationRequest.
//
// ASUMSI (MVP) — penyerahan sesungguhnya terjadi di alur APOTEK; di sini diaproksimasi
// dari peta item resep yang sudah dikirim:
//   - authorizingPrescription → MedicationRequest dari satusehat.medicationRequestItems
//   - performer               → IHS dokter (belum ada IHS apoteker di skmst_doctors)
//   - whenPrepared/HandedOver → jam obat diserahkan (taskIdPelayanan.taskId7) → now()
//   - quantity                → qty e-resep

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\MedicationDispenseTrait;
use App\Support\Terminologi\MedicationRequestItem;
use App\Support\Terminologi\RacikanKfa;

new class extends Component {
    use EmrRJTrait, MedicationDispenseTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public bool $hasResep = false;
    public int $count = 0;

    /** Item yang bisa diserahkan — peta resep→obat; 0 berarti kirim() akan membatalkan diri. */
    public int $siapKirim = 0;

    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /**
     * Obat yang AKAN diserahkan, memakai peta yang SAMA dengan kirimInti() —
     * MedicationRequestItem::ambil(). Peta inilah yang menautkan tiap penyerahan ke
     * resep yang benar; kalau ia kosong, kirimInti() memang membatalkan diri.
     */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }

        $data = $this->findDataRJ($this->rjNo);
        if (empty($data)) {
            return [];
        }

        $itemList = MedicationRequestItem::ambil($data['satusehat'] ?? [], $data);
        if (empty($itemList)) {
            return [];
        }

        $baris = [];
        foreach ($itemList as $urutan => $item) {
            $jenis = ($item['jenis'] ?? '') === 'racikan' ? 'Racikan' : 'Non-racikan';
            $baris[] = [
                'label' => $jenis . ' ' . ($urutan + 1),
                'nilai' => (string) ($item['display'] ?? ($item['kunci'] ?? '-')),
                'ket' => trim('qty ' . ($item['qty'] ?? 1) . ' · ' . (!empty($item['kode']) ? 'KFA ' . $item['kode'] : 'compound')
                    . ' · resep ' . ($item['id'] ?? '-')),
            ];
        }

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
        $this->hasResep = !empty($satuSehat['medicationRequestIds']);
        $this->count = count($satuSehat['medicationDispenseIds'] ?? []);
        $this->siapKirim = count(MedicationRequestItem::ambil($satuSehat, $data));
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
     * Pembungkus untuk rantai "Kirim Semua": apa pun hasilnya — berhasil, ditolak
     * SATUSEHAT, atau berhenti di guard — langkah ini WAJIB memberi kabar supaya
     * orkestrator bisa melanjutkan dan modal tidak membeku.
     */
    #[On('ss-medication-dispense-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('rj-satu-sehat.langkah-selesai', langkah: 'medication-dispense');
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
            if (empty($satuSehat['medicationRequestIds'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Resep (MedicationRequest) terlebih dahulu.'); return; }
            if (!empty($satuSehat['medicationDispenseIds'])) { $this->dispatch('toast', type: 'info', message: 'Obat diserahkan sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $performerId = (string) (DB::table('skmst_doctors')->where('dr_id', $dataRJ['drId'] ?? '')->value('dr_uuid') ?? '');
            if (empty($performerId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong.'); return; }

            $orgId = config('satusehat.organization_id');
            $patientName = $dataRJ['regName'] ?? '';
            $waktuSerah = $this->waktuPenyerahan($dataRJ)->toIso8601String();

            // Sumber pasangan resep→penyerahan: peta yang dicatat saat MedicationRequest
            // dikirim. Memasangkan lewat URUTAN daftar berarti geser satu item saja dan
            // obat tertaut ke resep yang salah.
            $itemList = MedicationRequestItem::ambil($satuSehat, $dataRJ);
            if (empty($itemList)) {
                $this->dispatch('toast', type: 'error',
                    message: 'Rincian item resep tak bisa dipastikan (daftar obat berubah setelah resep dikirim). Penyerahan dibatalkan.');
                return;
            }

            $racikanPerNomor = [];
            foreach (RacikanKfa::grupList($dataRJ) as $grup) {
                $racikanPerNomor[$grup['noRacikan']] = $grup;
            }

            $satuSehat['medicationDispenseIds'] = [];
            $dilewati = [];
            foreach ($itemList as $indeks => $item) {
                $adalahRacikan = ($item['jenis'] ?? '') === 'racikan';
                $ingredient = [];

                if ($adalahRacikan) {
                    $grup = $racikanPerNomor[$item['kunci']] ?? null;
                    if ($grup === null || !$grup['siap']) {
                        $dilewati[] = 'racikan ' . $item['kunci'];
                        continue;
                    }
                    $ingredient = RacikanKfa::fhirIngredient($grup['bahanList']);
                }

                $itemId = "{$rjNo}-" . ($indeks + 1);
                $jumlah = (int) ($item['qty'] ?? 1) ?: 1;

                $respons = $this->createMedicationDispense([
                    'orgId' => $orgId,
                    'registrationId' => $adalahRacikan ? "RACIKAN-{$itemId}" : $item['kode'],
                    'prescriptionItemId' => $itemId,
                    'medContainedId' => "meddisp-{$itemId}",
                    'medicationCode' => $item['kode'], 'medicationDisplay' => $item['display'],
                    'ingredient' => $ingredient,
                    'medicationFormCode' => 'BS066', 'medicationFormDisplay' => 'Tablet',
                    'medicationTypeCode' => $adalahRacikan ? 'SD' : 'NC',
                    'medicationTypeDisplay' => $adalahRacikan ? 'Compound' : 'Non-compound',
                    'patientId' => $patientId, 'patientName' => $patientName,
                    'encounterId' => $satuSehat['encounterId'],
                    'status' => 'completed', 'category' => 'outpatient',
                    'whenPrepared' => $waktuSerah, 'whenHandedOver' => $waktuSerah,
                    'performer' => [['actor' => ['reference' => "Practitioner/{$performerId}"]]],
                    // dosageInstruction sengaja dikosongkan: signa siklik belum dipetakan
                    // ke struktur FHIR, dan elemen objek yang dikirim [] ditolak validator.
                    'dosageInstruction' => [],
                    'authorizingPrescription' => ['reference' => "MedicationRequest/{$item['id']}"],
                    // Satuan pakai v3-orderableDrugForm. CodeSystem kfa-satuan DITOLAK
                    // SATUSEHAT: "Invalid coding system ... kfa-satuan (RuleNumber: 10050)".
                    'quantity' => ['value' => $jumlah, 'unit' => 'Tablet', 'system' => 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm', 'code' => 'TAB'],
                    'daysSupply' => ['value' => 1, 'unit' => 'Hari', 'system' => 'http://unitsofmeasure.org', 'code' => 'd'],
                    'receiver' => ['reference' => "Patient/{$patientId}", 'display' => $patientName],
                ]);
                if (!empty($respons['id'])) { $satuSehat['medicationDispenseIds'][] = $respons['id']; }
            }

            $this->saveResult($rjNo, $satuSehat);
            $pesan = 'Obat diserahkan berhasil dikirim (' . count($satuSehat['medicationDispenseIds']) . ' item).';
            if ($dilewati !== []) {
                $pesan .= ' Dilewati: ' . implode(', ', $dilewati) . '.';
            }
            $this->dispatch('toast', type: $dilewati === [] ? 'success' : 'info', message: $pesan);
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            // Simpan dulu yang sudah TERLANJUR terbentuk di SATUSEHAT sebelum melapor
            // gagal — id yang hangus berarti resource yatim dan kiriman ulang menumpuk.
            try { if (!empty($satuSehat['medicationDispenseIds'])) { $this->saveResult($rjNo, $satuSehat); } } catch (\Throwable) {}
            $this->dispatch('toast', type: 'error', message: 'Obat diserahkan gagal: ' . $this->ringkasErrorSatuSehat($e));
        }
    }

    /**
     * Jam obat benar-benar diserahkan, bukan jam petugas mengklik tombol.
     * taskIdPelayanan.taskId7 = "obat diserahkan"; belum terisi → now().
     */
    private function waktuPenyerahan(array $dataRJ): Carbon
    {
        $teksWaktu = trim((string) ($dataRJ['taskIdPelayanan']['taskId7'] ?? ''));

        return $teksWaktu !== '' ? $this->parseDate($teksWaktu) : Carbon::now();
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
                <span class="text-sm font-bold">6</span>
            </div>
            <div>
                <div class="font-semibold text-gray-800 dark:text-gray-100">Medication Dispense</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Obat diserahkan (butuh resep dikirim dulu).</div>

                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-emerald-600 dark:text-emerald-400">
                        {{ $count }} terkirim
                    </div>
                @elseif (!$hasResep)
                    <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">Kirim Resep dulu</div>
                @elseif ($siapKirim === 0)
                    <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                        Rincian item resep tak bisa dipastikan — daftar obat berubah setelah resep dikirim.
                    </div>
                @else
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $siapKirim }} item siap diserahkan</div>
                @endif
            </div>
        </div>
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled"
            :disabled="!$hasEncounter || !$hasResep"
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
        kosong="Resep belum dikirim atau rincian itemnya tak bisa dipastikan — Kirim akan ditolak." />
</div>
