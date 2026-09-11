<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-allergy.blade.php
// Step 7: Kirim Alergi (AllergyIntolerance, SNOMED CT)
//
// Sumber: anamnesa.alergi.{adaAlergi, alergi, snomedCode, snomedDisplayEn, snomedDisplayId}.
// createAllergyIntolerance WAJIB: patientId, encounterId, code(SNOMED), recorderId.
//
// Node alergi kini punya radio "Ada Alergi?" (`adaAlergi` = Ya/Tidak, seragam dgn sirus):
//   Tidak -> AlergiSnomed::normalisasi() memasang teks "Tidak ada alergi" + SNOMED
//            716186003 di anamnesa, jadi kartu ini SELALU punya kode untuk dikirim
//            (category tetap wajib — RuleNumber 10075).
//   Ya    -> teks + kode ZAT dari LOV SNOMED (lov.selected.alergiSnomed).
// Kartu tetap memutuskan dari KODE (AlergiSnomed::adalahTidakAdaAlergi), bukan dari
// `adaAlergi`: kode itulah yang benar-benar masuk payload. Teks bebas "tidak ada" TIDAK
// diterjemahkan jadi kode diam-diam — itu akan mengarang pernyataan klinis yang tak
// pernah dibuat siapa pun; record LAMA yang belum dibuka ulang di anamnesa karenanya
// masih bisa "teks tidak ada tapi kode kosong" (dijelaskan di pratinjau).

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\AllergyIntoleranceTrait;
use App\Support\Terminologi\AlergiSnomed;

new class extends Component {
    use EmrRJTrait, AllergyIntoleranceTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;

    public bool $adaTeks = false;
    public bool $adaSnomed = false;

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat modal berisi banyak kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /**
     * Isi yang AKAN dikirim, dari sumber yang SAMA dengan kirimInti() — anamnesa.alergi.
     * "Tidak ada alergi" pun DIKIRIM (pernyataan negatif yang sah), jadi ikut
     * ditampilkan supaya petugas tak mengira kartunya kosong.
     */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }

        $alergi = $this->findDataRJ($this->rjNo)['anamnesa']['alergi'] ?? [];
        $teks = trim((string) ($alergi['alergi'] ?? ''));
        $kode = trim((string) ($alergi['snomedCode'] ?? ''));
        if ($teks === '' && $kode === '') {
            return [];
        }

        $baris = [
            ['label' => 'Alergi', 'nilai' => $teks ?: '(kosong)'],
            [
                'label' => 'Kode SNOMED',
                'nilai' => $kode ?: '(belum dipilih — Kirim akan ditolak)',
                'ket' => trim((string) ($alergi['snomedDisplayEn'] ?? ($alergi['snomedDisplayId'] ?? ''))),
            ],
            ['label' => 'category (wajib)', 'nilai' => AlergiSnomed::kategoriFhir($kode), 'ket' => 'RuleNumber 10075 — tetap dikirim walau "tidak ada alergi"'],
        ];

        if ($kode !== '' && AlergiSnomed::adalahTidakAdaAlergi($kode)) {
            $baris[] = ['label' => 'Jenis', 'nilai' => 'Pernyataan TIDAK ADA alergi', 'ket' => 'type & criticality dihilangkan; tetap dikirim sebagai AllergyIntolerance'];
        } elseif ($kode === '' && AlergiSnomed::adalahTeksTidakAda($teks)) {
            $baris[] = ['label' => 'Catatan', 'nilai' => 'Teks berbunyi "tidak ada" tapi tanpa kode', 'ket' => 'pilih kode SNOMED "Tidak ada alergi" di anamnesa — teks tidak diterjemahkan otomatis'];
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
        $alergi = $data['anamnesa']['alergi'] ?? [];

        $this->hasEncounter = !empty($satuSehat['encounterId']);
        $this->count = !empty($satuSehat['allergyId']) ? 1 : 0;
        $this->adaTeks = trim((string) ($alergi['alergi'] ?? '')) !== '';
        $this->adaSnomed = trim((string) ($alergi['snomedCode'] ?? '')) !== '';
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    /** Pembungkus rantai "Kirim Semua" — WAJIB melapor apa pun hasilnya. */
    #[On('ss-allergy-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('rj-satu-sehat.langkah-selesai', langkah: 'allergy');
    }

    public function kirimInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.'); return; }

            $satuSehat = $dataRJ['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['allergyId'])) { $this->dispatch('toast', type: 'info', message: 'Alergi sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $recorderId = $this->getDoctorIHS($dataRJ['drId'] ?? '');
            if (empty($recorderId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong — lengkapi di master dokter.'); return; }

            $alergi = $dataRJ['anamnesa']['alergi'] ?? [];
            $alergiText = trim((string) ($alergi['alergi'] ?? ''));
            $snomedCode = trim((string) ($alergi['snomedCode'] ?? ''));

            $tidakAdaAlergi = AlergiSnomed::adalahTidakAdaAlergi($snomedCode);
            // Teks boleh kosong SELAMA kodenya pernyataan "tidak ada alergi" — di situ
            // kode-nya yang bermakna, bukan teksnya.
            if ($alergiText === '' && !$tidakAdaAlergi) { $this->dispatch('toast', type: 'error', message: 'Data alergi belum diisi di anamnesa.'); return; }
            if ($snomedCode === '') { $this->dispatch('toast', type: 'error', message: 'Kode SNOMED Alergi belum dipilih di anamnesa (wajib untuk SATUSEHAT).'); return; }

            $respons = $this->createAllergyIntolerance([
                'patientId'   => $patientId,
                'encounterId' => $satuSehat['encounterId'],
                'recorderId'  => $recorderId,
                'code'        => $snomedCode,
                'display'     => $alergi['snomedDisplayEn'] ?? ($alergi['snomedDisplayId'] ?? $alergiText),
                // "Tidak ada alergi" (mis. SNOMED 716186003) = pernyataan TIADA alergi →
                // type & criticality DIHILANGKAN (null), keduanya atribut alergi yang ADA.
                // category TETAP dikirim: SATUSEHAT mewajibkannya (RuleNumber 10075),
                // pemetaannya di AlergiSnomed::kategoriFhir().
                'type'        => $tidakAdaAlergi ? null : 'allergy',
                'category'    => AlergiSnomed::kategoriFhir($snomedCode),
                'criticality' => $tidakAdaAlergi ? null : 'low',
                'note'        => $alergiText,
                'onset'       => $this->parseDate($dataRJ['rjDate'] ?? '')->toIso8601String(),
            ]);

            if (empty($respons['id'])) { $this->dispatch('toast', type: 'error', message: 'Alergi gagal: respons tanpa id.'); return; }

            $satuSehat['allergyId'] = $respons['id'];
            $this->saveResult($rjNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: $tidakAdaAlergi ? 'Pernyataan "tidak ada alergi" berhasil dikirim.' : 'Alergi berhasil dikirim.');
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Alergi gagal: ' . $this->ringkasErrorSatuSehat($e));
        }
    }

    private function getPatientIHS(string $regNo): string
    {
        if (empty($regNo)) return '';
        return (string) (DB::table('skmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '');
    }

    private function getDoctorIHS(string $drId): string
    {
        if (empty($drId)) return '';
        return (string) (DB::table('skmst_doctors')->where('dr_id', $drId)->value('dr_uuid') ?? '');
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
                <span class="text-sm font-bold">7</span>
            </div>
            <div>
                <div class="font-semibold text-gray-800 dark:text-gray-100">Allergy Intolerance</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Riwayat alergi pasien (SNOMED CT).</div>
                <div class="mt-1 text-xs {{ $adaSnomed ? 'text-gray-500 dark:text-gray-400' : 'text-amber-600 dark:text-amber-400' }}">
                    @if ($adaSnomed)
                        Kode SNOMED alergi siap dikirim.
                    @elseif ($adaTeks)
                        Alergi terisi teks tapi kode SNOMED belum dipilih — Kirim akan ditolak.
                    @else
                        Riwayat alergi belum diisi di anamnesa — Kirim akan ditolak.
                    @endif
                </div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-emerald-600 dark:text-emerald-400">terkirim</div>
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
        kosong="Riwayat alergi belum diisi di anamnesa — Kirim akan ditolak." />
</div>
