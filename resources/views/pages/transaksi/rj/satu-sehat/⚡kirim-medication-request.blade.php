<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-medication-request.blade.php
// Step 5: Kirim Resep Obat (MedicationRequest)

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\MedicationRequestTrait;

new class extends Component {
    use EmrRJTrait, MedicationRequestTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;

    /** Obat non-racikan yang PUNYA padanan kode KFA — hanya ini yang berangkat. */
    public int $siapKirim = 0;

    /** Obat non-racikan yang DILEWATI karena tidak punya kode KFA. */
    public int $obatTanpaKfa = 0;

    /** Racikan di resep — belum didukung pengiriman (butuh KFA per bahan). */
    public int $racikanBelumDidukung = 0;

    /** Master obat sudah punya kolom pemetaan KFA? Kalau belum, tak ada yang bisa dikirim. */
    public bool $kolomKfaAda = false;

    /**
     * Kolom pemetaan Master Obat -> kode KFA SATUSEHAT.
     *
     * BELUM ADA di skmst_products (cek user_tab_columns). Selama kolom ini belum
     * dibuat & diisi, TIDAK ADA satu pun obat yang bisa dikirim — kartu melaporkan
     * "0 item punya KFA" apa adanya, bukan pura-pura siap. Begitu kolomnya dibuat,
     * kartu ini langsung jalan tanpa perubahan kode.
     */
    private const KOLOM_KFA = 'product_id_satusehat';

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat modal berisi banyak kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /**
     * Isi yang AKAN dikirim, memakai daftarObat() — helper yang sama persis dengan
     * yang dipanggil kirimInti(). Yang TIDAK berangkat (obat tanpa KFA, racikan)
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
        $daftar = $this->daftarObat($dataRJ, $tanpaKfa);
        $jumlahRacikan = count($dataRJ['eresepRacikan'] ?? []);

        $baris = [];
        foreach ($daftar as $urutan => $obat) {
            $baris[] = [
                'label' => 'Obat ' . ($urutan + 1),
                'nilai' => $obat['display'] . ' × ' . $obat['qty'],
                'ket' => 'KFA ' . $obat['kode'] . ' · prescriptionItemId ' . $this->rjNo . '-' . ($urutan + 1),
            ];
        }

        if ($tanpaKfa > 0) {
            $baris[] = ['label' => 'Dilewati', 'nilai' => $tanpaKfa . ' obat tanpa kode KFA',
                'ket' => $this->kolomKfaAda
                    ? 'belum ada padanan KFA di Master Obat'
                    : 'Master Obat belum punya kolom skmst_products.' . self::KOLOM_KFA];
        }
        if ($jumlahRacikan > 0) {
            $baris[] = ['label' => 'Dilewati', 'nilai' => $jumlahRacikan . ' racikan',
                'ket' => 'pengiriman racikan belum didukung (butuh KFA per bahan)'];
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

        $this->kolomKfaAda = $this->adaKolomKfa();

        $tanpaKfa = 0;
        $this->siapKirim = count($this->daftarObat($data, $tanpaKfa));
        $this->obatTanpaKfa = $tanpaKfa;
        $this->racikanBelumDidukung = count($data['eresepRacikan'] ?? []);
    }

    /**
     * Apakah master obat sudah punya kolom pemetaan KFA?
     * Dicek langsung ke kamus data Oracle — murah, dan tidak melempar bila
     * koneksi/DDL belum siap (kartu tetap tampil, sekadar melaporkan "belum ada").
     */
    private function adaKolomKfa(): bool
    {
        try {
            $jumlah = DB::selectOne(
                'select count(*) as jml from user_tab_columns where table_name = :tabel and column_name = :kolom',
                ['tabel' => 'SKMST_PRODUCTS', 'kolom' => strtoupper(self::KOLOM_KFA)]
            );

            return (int) ($jumlah->jml ?? 0) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Obat non-racikan yang AKAN dikirim, dari sumber yang SAMA dengan kirimInti().
     *
     * Item eresep siklik: { productId, productName, qty, signaX, signaHari,
     * catatanKhusus, jenisKeterangan } — TIDAK ADA key 'kfaCode' maupun
     * 'product_id_satusehat' di dalam JSON. Kode KFA hanya bisa datang dari master
     * obat; kalau pemetaannya belum ada, obatnya dilewati DAN dihitung di
     * $tanpaKfa supaya tidak hilang diam-diam.
     */
    private function daftarObat(array $dataRJ, int &$tanpaKfa): array
    {
        $tanpaKfa = 0;
        $eresep = $dataRJ['eresep'] ?? [];
        if (empty($eresep)) {
            return [];
        }

        $petaKfa = $this->petaKfaProduk(array_column($eresep, 'productId'));

        $daftar = [];
        foreach ($eresep as $obat) {
            $productId = trim((string) ($obat['productId'] ?? ''));
            $kodeKfa = trim((string) ($petaKfa[$productId] ?? ''));

            if ($kodeKfa === '') {
                $tanpaKfa++;
                continue;
            }

            $daftar[] = [
                'productId' => $productId,
                'kode' => $kodeKfa,
                'display' => trim((string) ($obat['productName'] ?? $productId)),
                'qty' => (string) ($obat['qty'] ?? '1'),
            ];
        }

        return $daftar;
    }

    /** productId -> kode KFA dari master obat; kosong selama kolom KFA belum ada. */
    private function petaKfaProduk(array $productIdList): array
    {
        $productIdList = array_values(array_filter(array_map('trim', array_map('strval', $productIdList))));
        if (empty($productIdList) || !$this->adaKolomKfa()) {
            return [];
        }

        return DB::table('skmst_products')
            ->whereIn('product_id', $productIdList)
            ->pluck(self::KOLOM_KFA, 'product_id')
            ->filter()
            ->all();
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
            $obatList = $this->daftarObat($dataRJ, $obatTanpaKfa);
            $jumlahRacikan = count($dataRJ['eresepRacikan'] ?? []);

            if (empty($obatList)) {
                $this->dispatch('toast', type: 'error',
                    message: $this->adaKolomKfa()
                        ? "Tidak ada obat yang bisa dikirim: {$obatTanpaKfa} item belum punya padanan kode KFA di Master Obat."
                        : 'Tidak ada obat yang bisa dikirim: Master Obat belum punya kolom pemetaan kode KFA (skmst_products.' . self::KOLOM_KFA . ').');
                return;
            }

            $satuSehat['medicationRequestIds'] = [];
            $satuSehat['medicationRequestItems'] = [];
            foreach ($obatList as $indeks => $obat) {
                // prescriptionItemId UNIK per item. Satu resep bisa berisi banyak obat;
                // tanpa nomor item sendiri semua MedicationRequest-nya memakai identifier
                // yang sama persis dan SATUSEHAT tak bisa membedakannya.
                $itemId = "{$rjNo}-" . ($indeks + 1);

                // Field opsional (dosageInstruction/dispenseRequest/reasonReference)
                // sengaja TIDAK dikirim: signa siklik belum dipetakan ke struktur FHIR,
                // dan mengirimnya sebagai [] ditolak SATUSEHAT (dispenseRequest itu objek
                // 0..1). Trait sudah menghilangkan kunci yang kosong.
                $respons = $this->createMedicationRequest([
                    'registrationId' => $obat['kode'], 'orgId' => $orgId, 'medContainedId' => "med-{$itemId}",
                    'medicationCode' => $obat['kode'], 'medicationDisplay' => $obat['display'],
                    'medicationFormCode' => 'BS066', 'medicationFormDisplay' => 'Tablet',
                    'medicationTypeCode' => 'NC', 'medicationTypeDisplay' => 'Non-compound',
                    'prescriptionId' => $rjNo, 'prescriptionItemId' => $itemId,
                    'patientId' => $patientId, 'patientName' => $patientName,
                    'encounterId' => $satuSehat['encounterId'], 'requesterId' => $practitionerId, 'requesterName' => $drDesc,
                    'authoredOn' => $rjDate->toIso8601String(), 'category' => 'outpatient',
                ]);
                if (!empty($respons['id'])) {
                    $satuSehat['medicationRequestIds'][] = $respons['id'];
                    // Peta eksplisit untuk MedicationDispense nanti: tanpa ini dispense
                    // harus menebak pasangan resepnya lewat urutan daftar.
                    $satuSehat['medicationRequestItems'][] = [
                        'id' => $respons['id'],
                        'jenis' => 'nonRacikan',
                        'kunci' => $obat['productId'],
                        'kode' => $obat['kode'],
                        'display' => $obat['display'],
                        'qty' => $obat['qty'],
                    ];
                }
            }

            $this->saveResult($rjNo, $satuSehat);
            $count = count($satuSehat['medicationRequestIds']);

            // Yang TIDAK berangkat wajib dilaporkan — tanpa ini obat tanpa KFA hilang
            // diam-diam dan tak ada pesan error apa pun untuknya.
            $catatan = [];
            if ($obatTanpaKfa > 0) { $catatan[] = "{$obatTanpaKfa} obat tanpa kode KFA dilewati"; }
            if ($jumlahRacikan > 0) { $catatan[] = "{$jumlahRacikan} racikan belum didukung"; }

            $this->dispatch('toast',
                type: empty($catatan) ? 'success' : 'info',
                message: "Resep obat berhasil dikirim ({$count} item)." . (empty($catatan) ? '' : ' ' . implode('; ', $catatan) . '.'));
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            // Simpan dulu yang sudah TERLANJUR terbentuk di SATUSEHAT sebelum melapor
            // gagal — id yang hangus berarti resource yatim dan kiriman ulang menumpuk.
            try { if (!empty($satuSehat['medicationRequestIds'])) { $this->saveResult($rjNo, $satuSehat); } } catch (\Throwable) {}
            $this->dispatch('toast', type: 'error', message: 'Resep obat gagal: ' . $this->ringkasErrorSatuSehat($e));
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
            <span class="text-sm font-bold">5</span>
        </div>
        <div>
            <div class="font-semibold text-gray-800 dark:text-gray-100">Medication Request</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Resep obat (kode KFA).</div>

            <div class="mt-1 text-xs {{ $siapKirim > 0 ? 'text-gray-500 dark:text-gray-400' : 'text-amber-600 dark:text-amber-400' }}">
                {{ $siapKirim }} item punya KFA
                @if ($obatTanpaKfa > 0)
                    &middot; {{ $obatTanpaKfa }} tanpa KFA (dilewati)
                @endif
                @if ($racikanBelumDidukung > 0)
                    &middot; {{ $racikanBelumDidukung }} racikan belum didukung
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
