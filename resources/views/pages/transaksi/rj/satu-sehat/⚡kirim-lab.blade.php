<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-lab.blade.php
// Step 7: Kirim Penunjang Lab — per paket checkup: ServiceRequest → Specimen →
// Observation(laboratory) → DiagnosticReport.
//
// Sumber (DB, bukan JSON EMR): sktxn_rjlabs(rj_no → checkup_no) + sktxn_checkuphdrs(ref_no)
//   → sktxn_checkupdtls(clabitem_id, lab_result) → skmst_clabitems(loinc_code, loinc_display,
//   unit_desc). Satu paket checkup = satu SR + Specimen + DR; tiap item ber-loinc_code
//   BERHASIL = satu Observation. Item tanpa loinc_code dilewati DAN dihitung (diisi lewat
//   Master Lab), tidak boleh hilang diam-diam.
//
// ASUMSI MVP: code panel SR/DR = LOINC generik 26436-6 (Laboratory studies); Specimen =
//   darah (SNOMED 119297000) metode venipuncture; hasil numerik → valueQuantity, selain
//   itu valueString.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\ServiceRequestTrait;
use App\Http\Traits\SATUSEHAT\SpecimenTrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;
use App\Http\Traits\SATUSEHAT\DiagnosticReportTrait;
use App\Http\Traits\SATUSEHAT\PenunjangKirimTrait;
use App\Support\PenanggungJawabPenunjang;

new class extends Component {
    use EmrRJTrait, ServiceRequestTrait, SpecimenTrait, ObservationTrait, DiagnosticReportTrait, PenunjangKirimTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;   // jumlah DiagnosticReport terkirim

    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /**
     * Paket lab yang AKAN dikirim, dari kriteria yang SAMA dengan kirimInti(): paket
     * milik kunjungan ini yang statusnya BUKAN 'P' (masih proses). Item tanpa LOINC ikut
     * dihitung karena kirimInti() melewatinya — itu justru yang perlu dilihat petugas.
     */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }

        $baris = [];
        foreach ($this->paketList($this->rjNo) as $paket) {
            $itemList = $this->itemList($paket->checkup_no);
            $berLoinc = $itemList->filter(fn ($item) => trim((string) ($item->loinc_code ?? '')) !== '')->count();
            $tanpaLoinc = $itemList->count() - $berLoinc;

            $baris[] = [
                'label' => 'Paket ' . $paket->checkup_no,
                'nilai' => $berLoinc . ' pemeriksaan ber-LOINC',
                'ket' => trim((string) ($paket->checkup_date ?? ''))
                    . ($tanpaLoinc > 0 ? " · {$tanpaLoinc} item tanpa LOINC DILEWATI" : ''),
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
        $this->count = count($satuSehat['labDiagnosticReportIds'] ?? []);
    }

    /**
     * Paket checkup lab milik satu kunjungan yang SUDAH ada hasilnya.
     *
     * Relasi rj→checkup ada di DUA tempat: sktxn_rjlabs (baris tagihan lab pada kunjungan)
     * dan sktxn_checkuphdrs.ref_no. Keduanya dipakai — record lama kadang hanya punya
     * salah satunya, dan paket yang terlewat berarti hasil lab tak pernah terkirim.
     */
    private function paketList(string $rjNo)
    {
        $checkupNoList = DB::table('sktxn_rjlabs')->where('rj_no', $rjNo)->pluck('checkup_no')->all();

        return DB::table('sktxn_checkuphdrs')
            ->where('status_rjri', 'RJ')
            ->where('checkup_status', '<>', 'P')
            ->where(function ($subQuery) use ($rjNo, $checkupNoList) {
                $subQuery->where('ref_no', $rjNo);
                if ($checkupNoList !== []) {
                    $subQuery->orWhereIn('checkup_no', $checkupNoList);
                }
            })
            ->orderBy('checkup_no')
            ->get(['checkup_no', 'dr_id', DB::raw("to_char(checkup_date,'dd/mm/yyyy hh24:mi:ss') as checkup_date")]);
    }

    /** Item hasil satu paket (kecuali baris judul grup & item yang disembunyikan). */
    private function itemList($checkupNo)
    {
        return DB::table('sktxn_checkupdtls as b')
            ->join('skmst_clabitems as d', 'b.clabitem_id', '=', 'd.clabitem_id')
            ->where('b.checkup_no', $checkupNo)
            ->whereRaw("nvl(d.hidden_status,'N') = 'N'")
            ->whereRaw("nvl(d.is_group,'N') <> 'Y'")
            ->orderBy('b.checkup_dtl')
            ->get(['d.clabitem_desc', 'd.loinc_code', 'd.loinc_display', 'd.unit_desc', 'b.lab_result']);
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
    #[On('ss-lab-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('rj-satu-sehat.langkah-selesai', langkah: 'lab');
    }

    public function kirimInti(string $rjNo): void
    {
        $satuSehat = null;
        $indeks = null;
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.'); return; }

            $satuSehat = $dataRJ['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('skmst_doctors')->where('dr_id', $dataRJ['drId'] ?? '')->value('dr_uuid') ?? '');
            if (empty($practitionerId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong.'); return; }

            $orgId = config('satusehat.organization_id');
            $encounterId = $satuSehat['encounterId'];
            $drDesc = $dataRJ['drDesc'] ?? '';

            $paketList = $this->paketList($rjNo);
            if ($paketList->isEmpty()) { $this->dispatch('toast', type: 'error', message: 'Tidak ada hasil lab (paket selesai) untuk dikirim.'); return; }

            foreach (['labServiceRequestIds', 'labSpecimenIds', 'labObservationIds', 'labDiagnosticReportIds'] as $kunciDatar) {
                $satuSehat[$kunciDatar] = $satuSehat[$kunciDatar] ?? [];
            }

            // Indeks per-paket — penentu paket mana yang masih bolong. Lihat PenunjangKirimTrait.
            $sistemSr = "http://sys-ids.kemkes.go.id/servicerequest/{$orgId}";
            $sistemSp = "http://sys-ids.kemkes.go.id/specimen/{$orgId}";
            $sistemDr = "http://sys-ids.kemkes.go.id/diagnostic/{$orgId}/lab";
            // Sebelum RuleNumber 10432 identifier DiagnosticReport TANPA akhiran. Record yang
            // sudah terkirim memakai system lama itu, jadi pemulihan mencoba KEDUANYA —
            // kalau tidak, DR lama tak ketemu lalu dibuatkan DR KEDUA di SATUSEHAT.
            $sistemDrLama = "http://sys-ids.kemkes.go.id/diagnostic/{$orgId}";
            $indeks = $this->indeksKirim($satuSehat, 'labKirim');
            $pulihkan = $this->perluPulihIndeks($satuSehat, 'labKirim', ['labServiceRequestIds', 'labDiagnosticReportIds']);

            $itemTanpaLoinc = 0;
            $totalObservasi = 0;
            $paketBaru = 0;        // paket yang benar-benar dikirim putaran ini
            $disusul = 0;          // paket lama yang bagian bolongnya baru dilengkapi sekarang
            $tuntas = 0;           // paket yang memang sudah lengkap — dilewati tanpa memanggil API
            $gagalSr = 0;          // ServiceRequest tak terbentuk → paket ini tak bisa dilanjut
            $tanpaHasil = 0;       // paket yang tak punya satu pun item ber-LOINC + hasil

            foreach ($paketList as $paket) {
                $checkupNo = trim((string) $paket->checkup_no);
                $waktu = $this->parseDate($paket->checkup_date ?? ($dataRJ['rjDate'] ?? ''))->toIso8601String();

                $itemList = $this->itemList($paket->checkup_no);
                $itemBerLoinc = $itemList->filter(fn ($item) => trim((string) ($item->loinc_code ?? '')) !== '');
                $itemTanpaLoinc += $itemList->count() - $itemBerLoinc->count();
                if ($itemBerLoinc->isEmpty()) { $tanpaHasil++; continue; }

                $kunciOrder = "{$rjNo}-{$checkupNo}";

                // Record lama belum punya indeks — pulihkan id yang SUDAH ada di SATUSEHAT
                // lewat identifier, supaya yang tersisa saja yang dikirim ulang.
                if ($pulihkan) {
                    $this->catatKirim($indeks, $kunciOrder, 'sr', $this->cariIdLewatIdentifier('ServiceRequest', $sistemSr, $kunciOrder));
                    $this->catatKirim($indeks, $kunciOrder, 'sp', $this->cariIdLewatIdentifier('Specimen', $sistemSp, $kunciOrder));
                    $this->catatKirim($indeks, $kunciOrder, 'dr',
                        $this->cariIdLewatIdentifier('DiagnosticReport', $sistemDr, $kunciOrder)
                        ?? $this->cariIdLewatIdentifier('DiagnosticReport', $sistemDrLama, $kunciOrder));
                }

                if ($this->orderTuntas($indeks, $kunciOrder, ['sr', 'dr'])) { $tuntas++; continue; }

                $srTersimpan = $this->idKirim($indeks, $kunciOrder, 'sr');
                $sudahAdaSebagian = filled($srTersimpan);

                // performer = petugas penunjang. dr_id paket dipakai lebih dulu; kalau
                // IHS-nya kosong, array kosong membuat ServiceRequestTrait jatuh ke dokter
                // pengirim — kiriman jalan walau nilainya belum akurat.
                $pjLab = PenanggungJawabPenunjang::practitionerRef(
                    PenanggungJawabPenunjang::UNIT_LABORATORIUM, (string) ($paket->dr_id ?? '')
                );

                // 1) ServiceRequest (order) — code panel LOINC generik.
                $serviceRequestId = $srTersimpan;
                if (empty($serviceRequestId)) {
                    $serviceRequest = $this->postServiceRequest([
                        'identifier' => ['system' => $sistemSr, 'value' => $kunciOrder],
                        'status' => 'active', 'intent' => 'original-order', 'priority' => 'routine',
                        'category' => ['system' => 'http://snomed.info/sct', 'code' => '108252007', 'display' => 'Laboratory procedure'],
                        'code' => ['system' => 'http://loinc.org', 'code' => '26436-6', 'display' => 'Laboratory studies'],
                        'subject' => "Patient/{$patientId}", 'encounter' => "Encounter/{$encounterId}",
                        'occurrenceDateTime' => $waktu, 'authoredOn' => $waktu,
                        'requester' => "Practitioner/{$practitionerId}", 'requesterDisplay' => $drDesc,
                        'performer' => $pjLab['reference'] ?? null,
                        'performerDisplay' => $pjLab['display'] ?? null,
                    ]);
                    $serviceRequestId = $serviceRequest['id'] ?? null;
                    if (empty($serviceRequestId)) { $gagalSr++; continue; }
                    $satuSehat['labServiceRequestIds'][] = $serviceRequestId;
                    $this->catatKirim($indeks, $kunciOrder, 'sr', $serviceRequestId);
                }

                // 2) Specimen — darah, venipuncture.
                $specimenId = $this->idKirim($indeks, $kunciOrder, 'sp');
                if (empty($specimenId)) {
                    $specimen = $this->postSpecimen([
                        'identifier' => ['system' => $sistemSp, 'value' => $kunciOrder, 'assigner' => "Organization/{$orgId}"],
                        'status' => 'available', 'subject' => "Patient/{$patientId}",
                        'type' => ['system' => 'http://snomed.info/sct', 'code' => '119297000', 'display' => 'Blood specimen'],
                        'collection' => ['collectedDateTime' => $waktu, 'method' => ['system' => 'http://snomed.info/sct', 'code' => '129300006', 'display' => 'Puncture - action']],
                        'receivedTime' => $waktu, 'request' => ["ServiceRequest/{$serviceRequestId}"],
                    ]);
                    $specimenId = $specimen['id'] ?? null;
                    if (!empty($specimenId)) {
                        $satuSehat['labSpecimenIds'][] = $specimenId;
                        $this->catatKirim($indeks, $kunciOrder, 'sp', $specimenId);
                    }
                }

                // 3 & 4) Observation DI DALAM cabang pembuatan DiagnosticReport.
                // DiagnosticReport.result WAJIB (RuleNumber 10385), tapi Observation tak
                // punya identifier sehingga tak bisa dipulihkan maupun ditolak duplikat.
                // Kalau dibuat di luar cabang ini, paket yang laporannya SUDAH ada akan
                // ditinggali observasi yatim setiap tombol Kirim ditekan.
                if (empty($this->idKirim($indeks, $kunciOrder, 'dr'))) {
                    $observationIdList = $this->daftarIdKirim($indeks, $kunciOrder, 'obs');
                    if (empty($observationIdList)) {
                        foreach ($itemBerLoinc as $item) {
                            $hasil = trim((string) ($item->lab_result ?? ''));
                            if ($hasil === '') { continue; }

                            $observation = $this->createObservation(
                                $this->payloadObservation($item, $hasil, $patientId, $encounterId, $practitionerId, $waktu)
                            );
                            if (!empty($observation['id'])) {
                                $observationIdList[] = $observation['id'];
                                $satuSehat['labObservationIds'][] = $observation['id'];
                                $totalObservasi++;
                            }
                        }
                        $this->catatKirim($indeks, $kunciOrder, 'obs', $observationIdList);
                    }

                    // Tanpa result, DiagnosticReport pasti ditolak — jangan kirim.
                    if (empty($observationIdList)) { $tanpaHasil++; continue; }

                    $laporan = $this->createDiagnosticReport([
                        'identifier' => [['system' => $sistemDr, 'use' => 'official', 'value' => $kunciOrder]],
                        'status' => 'final', 'categoryCode' => 'LAB', 'categoryDisplay' => 'Laboratory',
                        'codeSystem' => 'http://loinc.org', 'code' => '26436-6', 'display' => 'Laboratory studies',
                        'patientId' => $patientId, 'encounterId' => $encounterId,
                        'effectiveDate' => $waktu, 'issued' => $waktu,
                        'performer' => ["Practitioner/{$practitionerId}"],
                        'specimen' => $specimenId ? ["Specimen/{$specimenId}"] : [],
                        'observationIds' => $observationIdList, 'basedOn' => [$serviceRequestId],
                    ]);
                    if (!empty($laporan['id'])) {
                        $satuSehat['labDiagnosticReportIds'][] = $laporan['id'];
                        $this->catatKirim($indeks, $kunciOrder, 'dr', $laporan['id']);
                    }
                }

                $sudahAdaSebagian ? $disusul++ : $paketBaru++;
            }

            $satuSehat['labKirim'] = $indeks;

            // Tak ada yang dikerjakan DAN tak ada yang gagal → memang semuanya sudah pernah
            // dikirim. Indeks tetap disimpan supaya record lama tak perlu dipulihkan lagi.
            if ($paketBaru === 0 && $disusul === 0 && $gagalSr === 0) {
                $this->saveResult($rjNo, $satuSehat);
                $this->dispatch('toast', type: 'info',
                    message: $tuntas > 0 ? 'Lab sudah pernah dikirim.' : 'Tidak ada hasil lab yang bisa dikirim.');
                return;
            }

            if (empty($satuSehat['labDiagnosticReportIds'])) {
                $this->saveResult($rjNo, $satuSehat);
                $this->dispatch('toast', type: 'error',
                    message: $itemTanpaLoinc > 0
                        ? "Gagal: {$itemTanpaLoinc} item lab belum punya kode LOINC di Master Lab."
                        : 'Tidak ada hasil lab yang bisa dikirim.');
                return;
            }

            $this->saveResult($rjNo, $satuSehat);
            $jumlahLaporan = count($satuSehat['labDiagnosticReportIds']);
            $catatan = [];
            if ($itemTanpaLoinc > 0) { $catatan[] = "{$itemTanpaLoinc} item tanpa LOINC dilewati"; }
            if ($disusul > 0) { $catatan[] = "{$disusul} paket lama dilengkapi"; }
            if ($tuntas > 0) { $catatan[] = "{$tuntas} paket sudah lengkap dilewati"; }
            if ($tanpaHasil > 0) { $catatan[] = "{$tanpaHasil} paket tanpa hasil ber-LOINC dilewati"; }
            if ($gagalSr > 0) { $catatan[] = "{$gagalSr} paket GAGAL (ServiceRequest tak terbentuk)"; }

            $this->dispatch('toast',
                type: $gagalSr > 0 ? 'warning' : 'success',
                message: "Lab terkirim: {$jumlahLaporan} laporan, {$totalObservasi} observasi."
                    . (empty($catatan) ? '' : ' ' . implode('; ', $catatan) . '.'));
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            // Simpan dulu yang sudah TERLANJUR terbentuk di SATUSEHAT sebelum melapor
            // gagal — id yang hangus berarti resource yatim dan kiriman ulang menumpuk.
            // Indeks per-paket ikut disimpan supaya percobaan berikutnya tahu yang bolong.
            try {
                if (is_array($satuSehat)) {
                    if (is_array($indeks)) { $satuSehat['labKirim'] = $indeks; }
                    $this->saveResult($rjNo, $satuSehat);
                }
            } catch (\Throwable) {}
            $this->dispatch('toast', type: 'error', message: 'Lab gagal: ' . $this->ringkasErrorSatuSehat($e));
        }
    }

    /** Nilai numerik → valueQuantity (+UCUM dari unit_desc); selain itu valueString. */
    private function payloadObservation(object $item, string $hasil, string $patientId, string $encounterId, string $practitionerId, string $waktu): array
    {
        $payload = [
            'patientId' => $patientId, 'encounterId' => $encounterId, 'performerId' => $practitionerId,
            'effectiveDate' => $waktu,
            'category' => [['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/observation-category', 'code' => 'laboratory', 'display' => 'Laboratory']]]],
            'code' => ['system' => 'http://loinc.org', 'code' => trim((string) $item->loinc_code),
                'display' => trim((string) ($item->loinc_display ?: $item->clabitem_desc))],
        ];

        $angka = str_replace(',', '.', $hasil);
        if (is_numeric($angka)) {
            $satuan = trim((string) ($item->unit_desc ?? '')) ?: '1';
            $payload['valueQuantity'] = ['value' => (float) $angka, 'unit' => $satuan,
                'system' => 'http://unitsofmeasure.org', 'code' => $satuan];
        } else {
            $payload['valueString'] = $hasil;
        }

        return $payload;
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
                <span class="text-sm font-bold">7</span>
            </div>
            <div>
                <div class="font-semibold text-gray-800 dark:text-gray-100">Penunjang Lab</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">ServiceRequest · Specimen · Observation · DiagnosticReport.</div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-emerald-600 dark:text-emerald-400">
                        {{ $count }} laporan terkirim
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
        kosong="Belum ada paket lab selesai (checkup_status masih P) — Kirim akan ditolak." />
</div>
