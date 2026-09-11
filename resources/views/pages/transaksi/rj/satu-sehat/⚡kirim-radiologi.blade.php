<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-radiologi.blade.php
// Step 8: Kirim Penunjang Radiologi — per order: ServiceRequest + Observation ringkas +
// DiagnosticReport.
//
// Sumber: sktxn_rjrads(rj_no, rad_dtl, rad_id, rad_result, dr_radiologi, waktu_entry)
//   ← skmst_radiologis(rad_desc, loinc_code, loinc_display).
//
// TANPA ImagingStudy/Orthanc: siklik klinik pratama tidak punya PACS, jadi bagian itu
// SENGAJA tidak diport dari sirus. Hasil bacaan tetap berupa teks/lampiran di aplikasi;
// di SATUSEHAT diwakili satu Observation ringkas (category imaging, valueString) supaya
// DiagnosticReport.result terisi — field itu wajib (RuleNumber 10385).

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\ServiceRequestTrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;
use App\Http\Traits\SATUSEHAT\DiagnosticReportTrait;
use App\Http\Traits\SATUSEHAT\PenunjangKirimTrait;
use App\Support\KolomSatuSehat;
use App\Support\PenanggungJawabPenunjang;

new class extends Component {
    use EmrRJTrait, ServiceRequestTrait, ObservationTrait, DiagnosticReportTrait, PenunjangKirimTrait;

    /** LOINC generik "Diagnostic imaging study" — hanya dipakai bila master kosong. */
    private const LOINC_GENERIK = '18748-4';

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;   // jumlah DiagnosticReport terkirim

    /** Master radiologi sudah punya kolom LOINC? Kalau belum, semua order pakai kode generik. */
    public bool $kolomLoincAda = false;

    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /** Order radiologi yang AKAN dikirim, dari tabel & kolom yang SAMA dengan kirimInti(). */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }

        $baris = [];
        foreach ($this->orderList($this->rjNo) as $order) {
            $loinc = $this->loincOrder($order);
            $baris[] = [
                'label' => 'Pemeriksaan ' . $order->rad_dtl,
                'nilai' => trim((string) ($order->rad_desc ?? '-')),
                'ket' => 'kode ' . ($order->rad_id ?? '-') . ' · LOINC ' . $loinc['code']
                    . ($loinc['generik'] ? ' (generik — master belum dipetakan)' : ''),
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
        $this->kolomLoincAda = KolomSatuSehat::radiologiPunyaLoinc();

        $data = $this->findDataRJ($this->rjNo);
        if (empty($data)) {
            return;
        }
        $satuSehat = $data['satusehat'] ?? [];
        $this->hasEncounter = !empty($satuSehat['encounterId']);
        $this->count = count($satuSehat['radDiagnosticReportIds'] ?? []);
    }

    /**
     * Order radiologi satu kunjungan.
     *
     * Kolom LOINC master ditambahkan lewat SQL manual, jadi bisa belum ada saat kode ini
     * berjalan — dipilih hanya bila kolomnya memang ada, kalau tidak Oracle membalas
     * ORA-00904 dan kartu mati sebelum sempat melapor.
     */
    private function orderList(string $rjNo)
    {
        $kolomList = ['a.rad_dtl', 'a.rad_id', 'a.rad_result', 'a.dr_radiologi', 'm.rad_desc',
            DB::raw("to_char(a.waktu_entry,'dd/mm/yyyy hh24:mi:ss') as waktu_entry")];

        if (KolomSatuSehat::radiologiPunyaLoinc()) {
            $kolomList[] = 'm.loinc_code';
            $kolomList[] = 'm.loinc_display';
        }

        return DB::table('sktxn_rjrads as a')
            ->leftJoin('skmst_radiologis as m', 'a.rad_id', '=', 'm.rad_id')
            ->where('a.rj_no', $rjNo)
            ->orderBy('a.rad_dtl')
            ->get($kolomList);
    }

    /** Kode LOINC satu order: dari master bila ada, kalau tidak generik 18748-4. */
    private function loincOrder(object $order): array
    {
        $deskripsi = trim((string) ($order->rad_desc ?? '')) ?: 'Pemeriksaan Radiologi';
        $kode = trim((string) ($order->loinc_code ?? ''));
        $tampilan = trim((string) ($order->loinc_display ?? ''));

        if ($kode === '') {
            return ['code' => self::LOINC_GENERIK, 'display' => $deskripsi, 'generik' => true];
        }

        return ['code' => $kode, 'display' => $tampilan ?: $deskripsi, 'generik' => false];
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
    #[On('ss-radiologi-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('rj-satu-sehat.langkah-selesai', langkah: 'radiologi');
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
            $waktuKunjungan = $dataRJ['rjDate'] ?? '';

            $orderList = $this->orderList($rjNo);
            if ($orderList->isEmpty()) { $this->dispatch('toast', type: 'error', message: 'Tidak ada order radiologi untuk dikirim.'); return; }

            foreach (['radServiceRequestIds', 'radObservationIds', 'radDiagnosticReportIds'] as $kunciDatar) {
                $satuSehat[$kunciDatar] = $satuSehat[$kunciDatar] ?? [];
            }

            // Indeks per-order — penentu order mana yang masih bolong. Lihat PenunjangKirimTrait.
            $sistemSr = "http://sys-ids.kemkes.go.id/servicerequest/{$orgId}";
            $sistemDr = "http://sys-ids.kemkes.go.id/diagnostic/{$orgId}/rad";
            // Sebelum RuleNumber 10432 identifier DiagnosticReport TANPA akhiran. Record yang
            // sudah terkirim memakai system lama itu, jadi pemulihan mencoba KEDUANYA —
            // kalau tidak, DR lama tak ketemu lalu dibuatkan DR KEDUA di SATUSEHAT.
            $sistemDrLama = "http://sys-ids.kemkes.go.id/diagnostic/{$orgId}";
            $indeks = $this->indeksKirim($satuSehat, 'radKirim');
            $pulihkan = $this->perluPulihIndeks($satuSehat, 'radKirim', ['radServiceRequestIds', 'radDiagnosticReportIds']);

            $orderBaru = 0;        // order yang benar-benar dikirim putaran ini
            $disusul = 0;          // order lama yang bagian bolongnya baru dilengkapi sekarang
            $tuntas = 0;           // order yang memang sudah lengkap — dilewati tanpa memanggil API
            $gagalSr = 0;          // ServiceRequest tak terbentuk → order ini tak bisa dilanjut
            $takTerpetakan = 0;    // record lama yang id-nya tak ketemu saat dipulihkan
            $loincGenerik = 0;     // order yang memakai kode generik karena master kosong

            foreach ($orderList as $order) {
                $nomorDetail = trim((string) ($order->rad_dtl ?? ''));
                $deskripsi = trim((string) ($order->rad_desc ?? '')) ?: 'Pemeriksaan Radiologi';
                $kunciOrder = "rad-{$rjNo}-{$nomorDetail}";
                $waktu = $this->parseDate(trim((string) ($order->waktu_entry ?? '')) ?: $waktuKunjungan)->toIso8601String();

                $loinc = $this->loincOrder($order);
                if ($loinc['generik']) { $loincGenerik++; }

                // Record lama belum punya indeks — pulihkan id yang SUDAH ada di SATUSEHAT
                // lewat identifier, supaya yang tersisa saja yang dikirim ulang.
                if ($pulihkan) {
                    $this->catatKirim($indeks, $kunciOrder, 'sr', $this->cariIdLewatIdentifier('ServiceRequest', $sistemSr, $kunciOrder));
                    $this->catatKirim($indeks, $kunciOrder, 'dr',
                        $this->cariIdLewatIdentifier('DiagnosticReport', $sistemDr, $kunciOrder)
                        ?? $this->cariIdLewatIdentifier('DiagnosticReport', $sistemDrLama, $kunciOrder));
                }

                if ($this->orderTuntas($indeks, $kunciOrder, ['sr', 'dr'])) { $tuntas++; continue; }

                // Record lama yang id-nya TAK berhasil dipetakan — jangan dikirim ulang.
                // Array datar sudah membuktikan order ini pernah terkirim; POST ulang cuma
                // akan ditolak duplikat (RuleNumber 20002), persis kemacetan yang dihindari.
                if ($pulihkan && blank($this->idKirim($indeks, $kunciOrder, 'sr'))) { $takTerpetakan++; continue; }

                $srTersimpan = $this->idKirim($indeks, $kunciOrder, 'sr');
                $sudahAdaSebagian = filled($srTersimpan);

                // performer = petugas radiologi. Namanya tersimpan sebagai teks bebas di
                // dr_radiologi; diterima hanya bila cocok tepat satu dokter ber-IHS. Array
                // kosong → ServiceRequestTrait memakai dokter pengirim sebagai pengganti.
                $pjRadiologi = PenanggungJawabPenunjang::practitionerRef(
                    PenanggungJawabPenunjang::UNIT_RADIOLOGI, null, (string) ($order->dr_radiologi ?? '')
                );

                // 1) ServiceRequest — order radiologi.
                $serviceRequestId = $srTersimpan;
                if (empty($serviceRequestId)) {
                    $serviceRequest = $this->postServiceRequest([
                        'identifier' => ['system' => $sistemSr, 'value' => $kunciOrder],
                        'status' => 'active', 'intent' => 'original-order', 'priority' => 'routine',
                        'category' => ['system' => 'http://snomed.info/sct', 'code' => '363679005', 'display' => 'Imaging'],
                        'code' => ['system' => 'http://loinc.org', 'code' => $loinc['code'], 'display' => $loinc['display']],
                        'subject' => "Patient/{$patientId}", 'encounter' => "Encounter/{$encounterId}",
                        'occurrenceDateTime' => $waktu, 'authoredOn' => $waktu,
                        'requester' => "Practitioner/{$practitionerId}", 'requesterDisplay' => $drDesc,
                        'performer' => $pjRadiologi['reference'] ?? null,
                        'performerDisplay' => $pjRadiologi['display'] ?? null,
                    ]);
                    $serviceRequestId = $serviceRequest['id'] ?? null;
                    if (empty($serviceRequestId)) { $gagalSr++; continue; }
                    $satuSehat['radServiceRequestIds'][] = $serviceRequestId;
                    $this->catatKirim($indeks, $kunciOrder, 'sr', $serviceRequestId);
                }

                // 2) DiagnosticReport — pelaporan.
                if (empty($this->idKirim($indeks, $kunciOrder, 'dr'))) {
                    // Observation dibuat DI DALAM cabang ini, bukan di luar: DiagnosticReport.result
                    // wajib (RuleNumber 10385), tapi Observation tak punya identifier sehingga tak
                    // bisa dipulihkan maupun ditolak duplikat. Kalau dibuat di luar, laporan yang
                    // SUDAH ada akan ditinggali observasi yatim tiap kali tombol Kirim ditekan.
                    $observationId = $this->idKirim($indeks, $kunciOrder, 'obs');
                    if (empty($observationId)) {
                        $observation = $this->createObservation([
                            'patientId' => $patientId, 'encounterId' => $encounterId, 'performerId' => $practitionerId,
                            'effectiveDate' => $waktu,
                            'category' => [['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/observation-category', 'code' => 'imaging', 'display' => 'Imaging']]]],
                            'code' => ['system' => 'http://loinc.org', 'code' => $loinc['code'], 'display' => $loinc['display']],
                            'valueString' => trim((string) ($order->rad_result ?? '')) ?: 'Lihat hasil pada lampiran radiologi',
                        ]);
                        $observationId = $observation['id'] ?? null;
                        if (!empty($observationId)) {
                            $satuSehat['radObservationIds'][] = $observationId;
                            $this->catatKirim($indeks, $kunciOrder, 'obs', $observationId);
                        }
                    }

                    $payloadLaporan = [
                        'identifier' => [['system' => $sistemDr, 'use' => 'official', 'value' => $kunciOrder]],
                        'status' => 'final', 'categoryCode' => 'RAD', 'categoryDisplay' => 'Radiology',
                        'codeSystem' => 'http://loinc.org', 'code' => $loinc['code'], 'display' => $loinc['display'],
                        'patientId' => $patientId, 'encounterId' => $encounterId,
                        'effectiveDate' => $waktu, 'issued' => $waktu,
                        'performer' => ["Practitioner/{$practitionerId}"], 'basedOn' => [$serviceRequestId],
                    ];
                    if (!empty($observationId)) { $payloadLaporan['observationIds'] = [$observationId]; }

                    $laporan = $this->createDiagnosticReport($payloadLaporan);
                    if (!empty($laporan['id'])) {
                        $satuSehat['radDiagnosticReportIds'][] = $laporan['id'];
                        $this->catatKirim($indeks, $kunciOrder, 'dr', $laporan['id']);
                    }
                }

                $sudahAdaSebagian ? $disusul++ : $orderBaru++;
            }

            $satuSehat['radKirim'] = $indeks;

            // Tak ada yang dikerjakan DAN tak ada yang gagal → memang semuanya sudah pernah
            // dikirim. Indeks tetap disimpan supaya record lama tak perlu dipulihkan lagi.
            if ($orderBaru === 0 && $disusul === 0 && $gagalSr === 0) {
                $this->saveResult($rjNo, $satuSehat);
                $this->dispatch('toast', type: 'info', message: 'Radiologi sudah pernah dikirim.');
                return;
            }

            if (empty($satuSehat['radServiceRequestIds'])) {
                $this->saveResult($rjNo, $satuSehat);
                $this->dispatch('toast', type: 'error', message: 'Tidak ada order radiologi yang bisa dikirim.');
                return;
            }

            $this->saveResult($rjNo, $satuSehat);
            $jumlahOrder = count($satuSehat['radServiceRequestIds']);
            $jumlahLaporan = count($satuSehat['radDiagnosticReportIds']);
            $catatan = [];
            if ($loincGenerik > 0) { $catatan[] = "{$loincGenerik} order pakai LOINC generik (belum dipetakan di Master Radiologi)"; }
            if ($disusul > 0) { $catatan[] = "{$disusul} order lama dilengkapi"; }
            if ($tuntas > 0) { $catatan[] = "{$tuntas} order sudah lengkap dilewati"; }
            if ($takTerpetakan > 0) { $catatan[] = "{$takTerpetakan} order lama tak terpetakan (dilewati)"; }
            if ($gagalSr > 0) { $catatan[] = "{$gagalSr} order GAGAL (ServiceRequest tak terbentuk)"; }

            $this->dispatch('toast',
                type: $gagalSr > 0 ? 'warning' : 'success',
                message: "Radiologi terkirim: {$jumlahOrder} order, {$jumlahLaporan} laporan."
                    . (empty($catatan) ? '' : ' ' . implode('; ', $catatan) . '.'));
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            // Simpan dulu yang sudah TERLANJUR terbentuk di SATUSEHAT sebelum melapor
            // gagal — id yang hangus berarti resource yatim dan kiriman ulang menumpuk.
            // Indeks per-order ikut disimpan supaya percobaan berikutnya tahu yang bolong.
            try {
                if (is_array($satuSehat)) {
                    if (is_array($indeks)) { $satuSehat['radKirim'] = $indeks; }
                    $this->saveResult($rjNo, $satuSehat);
                }
            } catch (\Throwable) {}
            $this->dispatch('toast', type: 'error', message: 'Radiologi gagal: ' . $this->ringkasErrorSatuSehat($e));
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
                <span class="text-sm font-bold">8</span>
            </div>
            <div>
                <div class="font-semibold text-gray-800 dark:text-gray-100">Penunjang Radiologi</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">ServiceRequest · Observation · DiagnosticReport (tanpa PACS).</div>

                @unless ($kolomLoincAda)
                    <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                        Master Radiologi belum punya kolom LOINC — semua order dikirim dengan kode generik 18748-4.
                    </div>
                @endunless

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
        kosong="Belum ada order radiologi untuk kunjungan ini — Kirim akan ditolak." />
</div>
