<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-medication-request.blade.php
// Step 5: Kirim Resep Obat (MedicationRequest) — non-racikan + racikan (compound).

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\MedicationRequestTrait;
use App\Support\KolomSatuSehat;
use App\Support\Terminologi\ObatKfa;
use App\Support\Terminologi\RacikanKfa;

new class extends Component {
    use EmrRJTrait, MedicationRequestTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;

    /** Obat non-racikan yang PUNYA padanan kode KFA — hanya ini yang berangkat. */
    public int $siapKirim = 0;

    /** Obat non-racikan yang DILEWATI karena tidak punya kode KFA. */
    public int $obatTanpaKfa = 0;

    /** Grup racikan yang semua bahannya ber-KFA (siap) dan yang tidak. */
    public int $racikanSiap = 0;
    public int $racikanTakSiap = 0;

    /** Master obat sudah punya kolom pemetaan KFA? Kalau belum, tak ada yang bisa dikirim. */
    public bool $kolomKfaAda = false;

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat modal berisi banyak kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /**
     * Isi yang AKAN dikirim, memakai helper yang SAMA PERSIS dengan kirimInti().
     * Yang TIDAK berangkat (obat tanpa KFA, racikan yang bahannya tak terpetakan)
     * tetap muncul sebagai baris tersendiri: dilewati boleh, hilang diam-diam tidak.
     */
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

        $tanpaKfa = 0;
        $obatList = ObatKfa::nonRacikanList($dataRJ, $tanpaKfa);
        $grupList = RacikanKfa::grupList($dataRJ);

        $baris = [];
        $urutan = 0;
        foreach ($obatList as $obat) {
            $urutan++;
            $baris[] = [
                'label' => 'Obat ' . $urutan,
                'nilai' => $obat['display'] . ' × ' . $obat['qty'],
                'ket' => 'KFA ' . $obat['code'] . ' · prescriptionItemId ' . $this->rjNo . '-' . $urutan,
            ];
        }
        foreach ($grupList as $grup) {
            if (!$grup['siap']) {
                continue;
            }
            $urutan++;
            $baris[] = [
                'label' => 'Racikan ' . $grup['noRacikan'],
                'nilai' => $grup['jumlahBahan'] . ' bahan ber-KFA',
                'ket' => 'compound · prescriptionItemId ' . $this->rjNo . '-' . $urutan,
            ];
        }

        if ($tanpaKfa > 0) {
            $baris[] = ['label' => 'Dilewati', 'nilai' => $tanpaKfa . ' obat tanpa kode KFA',
                'ket' => $this->kolomKfaAda
                    ? 'belum ada padanan KFA di Master Obat'
                    : 'Master Obat belum punya kolom skmst_products.' . KolomSatuSehat::PRODUK_KFA_KODE];
        }
        foreach ($grupList as $grup) {
            if ($grup['siap']) {
                continue;
            }
            $baris[] = ['label' => 'Dilewati', 'nilai' => 'Racikan ' . $grup['noRacikan'],
                'ket' => $grup['alasan']];
        }

        if (empty($baris)) {
            return [];
        }

        array_unshift($baris, [
            'label' => 'resourceType',
            'nilai' => 'MedicationRequest',
            'ket' => 'prescriptionId ' . $this->rjNo . ' · encounter ' . ($dataRJ['satusehat']['encounterId'] ?? '(belum ada)'),
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
        $this->count = count($satuSehat['medicationRequestIds'] ?? []);

        $this->kolomKfaAda = KolomSatuSehat::produkPunyaKfa();

        $tanpaKfa = 0;
        $this->siapKirim = count(ObatKfa::nonRacikanList($data, $tanpaKfa));
        $this->obatTanpaKfa = $tanpaKfa;

        $ringkasRacikan = RacikanKfa::ringkas($data);
        $this->racikanSiap = $ringkasRacikan['siap'];
        $this->racikanTakSiap = $ringkasRacikan['takSiap'];
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
    #[On('ss-medication-request-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('rj-satu-sehat.langkah-selesai', langkah: 'medication-request');
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
            if (!empty($satuSehat['medicationRequestIds'])) { $this->dispatch('toast', type: 'info', message: 'Resep obat sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('skmst_doctors')->where('dr_id', $dataRJ['drId'] ?? '')->value('dr_uuid') ?? '');
            $rjDate = $this->parseDate($dataRJ['rjDate'] ?? '');
            $orgId = config('satusehat.organization_id');
            $drDesc = $dataRJ['drDesc'] ?? '';
            $patientName = $dataRJ['regName'] ?? '';

            if (empty($dataRJ['eresep']) && empty($dataRJ['eresepRacikan'])) {
                $this->dispatch('toast', type: 'error', message: 'Tidak ada data resep obat.');
                return;
            }

            $obatTanpaKfa = 0;
            $obatList = ObatKfa::nonRacikanList($dataRJ, $obatTanpaKfa);
            $grupList = RacikanKfa::grupList($dataRJ);
            $grupSiap = array_values(array_filter($grupList, fn ($grup) => $grup['siap']));
            $grupTakSiap = array_values(array_filter($grupList, fn ($grup) => !$grup['siap']));

            if (empty($obatList) && empty($grupSiap)) {
                $this->dispatch('toast', type: 'error',
                    message: KolomSatuSehat::produkPunyaKfa()
                        ? "Tidak ada obat yang bisa dikirim: {$obatTanpaKfa} item non-racikan dan " . count($grupTakSiap) . ' racikan belum punya padanan kode KFA di Master Obat.'
                        : 'Tidak ada obat yang bisa dikirim: Master Obat belum punya kolom pemetaan kode KFA (skmst_products.' . KolomSatuSehat::PRODUK_KFA_KODE . ').');
                return;
            }

            $satuSehat['medicationRequestIds'] = [];
            $satuSehat['medicationRequestItems'] = [];

            // URUTAN PENTING: non-racikan dulu, baru racikan yang siap. MedicationRequestItem
            // membangun ulang pasangan resep→penyerahan dari urutan ini untuk kunjungan lama.
            $indeks = 0;
            foreach ($obatList as $obat) {
                $indeks++;
                $this->kirimSatuItem($satuSehat, [
                    'itemId' => "{$rjNo}-{$indeks}", 'rjNo' => $rjNo, 'orgId' => $orgId,
                    'registrationId' => $obat['code'], 'medicationCode' => $obat['code'],
                    'medicationDisplay' => $obat['display'], 'ingredient' => [],
                    'typeCode' => 'NC', 'typeDisplay' => 'Non-compound',
                    'jenis' => 'nonRacikan', 'kunci' => $obat['productId'],
                    'kode' => $obat['code'], 'qty' => $obat['qty'],
                    'patientId' => $patientId, 'patientName' => $patientName,
                    'practitionerId' => $practitionerId, 'drDesc' => $drDesc,
                    'authoredOn' => $rjDate->toIso8601String(),
                ]);
            }
            foreach ($grupSiap as $grup) {
                $indeks++;
                $display = 'Racikan ' . $grup['noRacikan'] . ' (' . $grup['jumlahBahan'] . ' bahan)';
                $this->kirimSatuItem($satuSehat, [
                    'itemId' => "{$rjNo}-{$indeks}", 'rjNo' => $rjNo, 'orgId' => $orgId,
                    // Campuran racikan tidak punya kode KFA sendiri — yang ber-KFA bahannya.
                    // medicationCode dikosongkan supaya trait menulis code sebagai TEKS saja;
                    // mengirim coding tanpa kode ditolak validator.
                    'registrationId' => "RACIKAN-{$rjNo}-{$indeks}", 'medicationCode' => '',
                    'medicationDisplay' => $display,
                    'ingredient' => RacikanKfa::fhirIngredient($grup['bahanList']),
                    'typeCode' => 'SD', 'typeDisplay' => 'Compound',
                    'jenis' => 'racikan', 'kunci' => $grup['noRacikan'],
                    'kode' => '', 'qty' => 1,
                    'patientId' => $patientId, 'patientName' => $patientName,
                    'practitionerId' => $practitionerId, 'drDesc' => $drDesc,
                    'authoredOn' => $rjDate->toIso8601String(),
                ]);
            }

            $this->saveResult($rjNo, $satuSehat);
            $jumlah = count($satuSehat['medicationRequestIds']);

            // Yang TIDAK berangkat wajib dilaporkan — tanpa ini obat tanpa KFA hilang
            // diam-diam dan tak ada pesan error apa pun untuknya.
            $catatan = [];
            if ($obatTanpaKfa > 0) { $catatan[] = "{$obatTanpaKfa} obat tanpa kode KFA dilewati"; }
            if ($grupTakSiap !== []) { $catatan[] = count($grupTakSiap) . ' racikan dilewati (bahan tanpa KFA)'; }

            $this->dispatch('toast',
                type: empty($catatan) ? 'success' : 'info',
                message: "Resep obat berhasil dikirim ({$jumlah} item)." . (empty($catatan) ? '' : ' ' . implode('; ', $catatan) . '.'));
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            // Simpan dulu yang sudah TERLANJUR terbentuk di SATUSEHAT sebelum melapor
            // gagal — id yang hangus berarti resource yatim dan kiriman ulang menumpuk.
            try { if (!empty($satuSehat['medicationRequestIds'])) { $this->saveResult($rjNo, $satuSehat); } } catch (\Throwable) {}
            $this->dispatch('toast', type: 'error', message: 'Resep obat gagal: ' . $this->ringkasErrorSatuSehat($e));
        }
    }

    /**
     * Satu MedicationRequest + catatan petanya.
     *
     * Field opsional (dosageInstruction/dispenseRequest/reasonReference) sengaja TIDAK
     * dikirim: signa siklik belum dipetakan ke struktur FHIR, dan mengirimnya sebagai []
     * ditolak SATUSEHAT (dispenseRequest itu objek 0..1).
     */
    private function kirimSatuItem(array &$satuSehat, array $item): void
    {
        $respons = $this->createMedicationRequest([
            'registrationId' => $item['registrationId'], 'orgId' => $item['orgId'],
            'medContainedId' => "med-{$item['itemId']}",
            'medicationCode' => $item['medicationCode'], 'medicationDisplay' => $item['medicationDisplay'],
            'ingredient' => $item['ingredient'],
            'medicationFormCode' => 'BS066', 'medicationFormDisplay' => 'Tablet',
            'medicationTypeCode' => $item['typeCode'], 'medicationTypeDisplay' => $item['typeDisplay'],
            // prescriptionItemId UNIK per item: satu resep bisa berisi banyak obat, dan
            // tanpa nomor item sendiri semuanya memakai identifier yang sama persis.
            'prescriptionId' => $item['rjNo'], 'prescriptionItemId' => $item['itemId'],
            'patientId' => $item['patientId'], 'patientName' => $item['patientName'],
            'encounterId' => $satuSehat['encounterId'],
            'requesterId' => $item['practitionerId'], 'requesterName' => $item['drDesc'],
            'authoredOn' => $item['authoredOn'], 'category' => 'outpatient',
        ]);

        if (empty($respons['id'])) {
            return;
        }

        $satuSehat['medicationRequestIds'][] = $respons['id'];
        // Peta eksplisit untuk MedicationDispense nanti: tanpa ini dispense harus
        // menebak pasangan resepnya lewat urutan daftar.
        $satuSehat['medicationRequestItems'][] = [
            'id' => $respons['id'],
            'jenis' => $item['jenis'],
            'kunci' => $item['kunci'],
            'kode' => $item['kode'],
            'display' => $item['medicationDisplay'],
            'qty' => $item['qty'],
        ];
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
            <span class="text-sm font-bold">5</span>
        </div>
        <div>
            <div class="font-semibold text-gray-800 dark:text-gray-100">Medication Request</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Resep obat &amp; racikan (kode KFA).</div>

            <div class="mt-1 text-xs {{ $siapKirim + $racikanSiap > 0 ? 'text-gray-500 dark:text-gray-400' : 'text-amber-600 dark:text-amber-400' }}">
                {{ $siapKirim }} obat ber-KFA
                @if ($racikanSiap > 0)
                    &middot; {{ $racikanSiap }} racikan siap
                @endif
                @if ($obatTanpaKfa > 0)
                    &middot; {{ $obatTanpaKfa }} tanpa KFA (dilewati)
                @endif
                @if ($racikanTakSiap > 0)
                    &middot; {{ $racikanTakSiap }} racikan tak lengkap
                @endif
            </div>

            @unless ($kolomKfaAda)
                <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                    Master Obat belum punya kolom pemetaan KFA (skmst_products.product_id_satusehat) — belum ada obat
                    yang bisa dikirim.
                </div>
            @endunless

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
        kosong="Tidak ada resep obat di EMR — Kirim akan ditolak." />
</div>
