<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-nyeri-kesadaran.blade.php
// Step 8: Kirim Skala Nyeri (multi-entri) + Tingkat Kesadaran sebagai Observation.
//
// Sumber data — DUA node berbeda, keduanya sudah diisi petugas dan selama ini
// tidak pernah dikirim ke SATUSEHAT:
//   penilaian.nyeri[]  → bentuk lama & baru bercampur; WAJIB lewat
//                        NyeriOptions::daftarEntri() yang menyatukannya. Di basis
//                        data siklik sekarang bentuknya SELURUHNYA yang lama (satu
//                        entri assoc, nyeriMetode berupa string, skor di skalaNyeri
//                        atau vas.vas) — 0 record memakai nyeriMetodeScore.
//   pemeriksaan.tandaVital.tingkatKesadaran → TUNGGAL, dan isinya KODE BPJS PCare
//                        ('01' Compos mentis … '04' Coma), bukan teks AVPU yang
//                        masih terdaftar di tingkatKesadaranOptions form perawat.
//                        Diperiksa ke 200 kunjungan terbaru: 200/200 memakai '01'.
//                        (sirus membaca screening.kesadaran — node itu tidak ada di siklik.)
//
// Kode terminologi ada di App\Support\Terminologi\NyeriKesadaranObservationMap,
// seluruhnya dari koleksi Postman resmi. Skala tanpa contoh resmi (VAS, FLACC, BPS)
// sengaja TIDAK dikirim — jumlahnya dimunculkan di kartu serta disebut di toast,
// sesuai aturan "yang tak terkirim wajib dilaporkan".

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;
use App\Support\Options\NyeriOptions;
use App\Support\Terminologi\NyeriKesadaranObservationMap;

new class extends Component {
    use EmrRJTrait, ObservationTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;          // Observation terkirim
    public int $nyeriSiap = 0;      // entri nyeri yang BISA dikirim
    public int $nyeriDilewati = 0;  // entri nyeri ber-skala tanpa kode resmi
    public string $kesadaranLabel = '';

    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /** Isi yang AKAN dikirim — memakai helper yang SAMA dengan kirimInti(). */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }

        $data = $this->findDataRJ($this->rjNo);
        $baris = [];

        foreach (NyeriOptions::daftarEntri($data['penilaian']['nyeri'] ?? []) as $urutan => $entri) {
            $node = $entri['nyeri'] ?? [];
            $kodeSkala = (string) ($node['nyeriMetode']['nyeriMetode'] ?? '');
            $skor = $node['nyeriMetode']['nyeriMetodeScore'] ?? null;
            $terkirim = NyeriKesadaranObservationMap::nyeri($entri) !== [];

            $baris[] = [
                'label' => 'Nyeri ' . ($urutan + 1) . ($kodeSkala !== '' ? ' (' . $kodeSkala . ')' : ''),
                'nilai' => $terkirim
                    ? 'skor ' . $skor
                    : ($kodeSkala !== '' && !NyeriKesadaranObservationMap::skalaDidukung($kodeSkala)
                        ? '(skala ' . $kodeSkala . ' belum punya kode resmi — dilewati)'
                        : '(tidak dikirim)'),
                'ket' => trim((string) ($entri['tglPenilaian'] ?? '')),
            ];
        }

        $kesadaran = trim((string) ($data['pemeriksaan']['tandaVital']['tingkatKesadaran'] ?? ''));
        if ($kesadaran !== '') {
            $label = NyeriKesadaranObservationMap::labelKesadaran($kesadaran);
            $baris[] = [
                'label' => 'Tingkat kesadaran',
                'nilai' => $label,
                'ket' => $label === $kesadaran ? '' : 'kode BPJS ' . $kesadaran,
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
        $this->count = count($satuSehat['nyeriKesadaranObservationIds'] ?? []);

        $siap = 0;
        $lewat = 0;
        foreach (NyeriOptions::daftarEntri($data['penilaian']['nyeri'] ?? []) as $entri) {
            if (NyeriKesadaranObservationMap::nyeri($entri) !== []) {
                $siap++;
                continue;
            }
            $kodeSkala = (string) ($entri['nyeri']['nyeriMetode']['nyeriMetode'] ?? '');
            // Hanya dihitung "dilewati" bila petugas MEMANG memilih skala; entri
            // tanpa skala (dijawab tidak nyeri) bukan kehilangan data.
            if ($kodeSkala !== '' && !NyeriKesadaranObservationMap::skalaDidukung($kodeSkala)) {
                $lewat++;
            }
        }
        $this->nyeriSiap = $siap;
        $this->nyeriDilewati = $lewat;
        $this->kesadaranLabel = NyeriKesadaranObservationMap::labelKesadaran(
            $data['pemeriksaan']['tandaVital']['tingkatKesadaran'] ?? ''
        );
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
    #[On('ss-nyeri-kesadaran-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('rj-satu-sehat.langkah-selesai', langkah: 'nyeri-kesadaran');
    }

    public function kirimInti(string $rjNo): void
    {
        $satuSehat = [];
        $idList = [];
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.'); return; }

            $satuSehat = $dataRJ['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['nyeriKesadaranObservationIds'])) { $this->dispatch('toast', type: 'info', message: 'Nyeri & kesadaran sudah pernah dikirim.'); return; }

            $patientId = (string) (DB::table('skmst_pasiens')->where('reg_no', $dataRJ['regNo'] ?? '')->value('patient_uuid') ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('skmst_doctors')->where('dr_id', $dataRJ['drId'] ?? '')->value('dr_uuid') ?? '');

            $entriNyeri = NyeriOptions::daftarEntri($dataRJ['penilaian']['nyeri'] ?? []);
            $kesadaran = trim((string) ($dataRJ['pemeriksaan']['tandaVital']['tingkatKesadaran'] ?? ''));
            if (empty($entriNyeri) && $kesadaran === '') {
                $this->dispatch('toast', type: 'error', message: 'Tidak ada data Skala Nyeri maupun Tingkat Kesadaran.');
                return;
            }

            $dilewati = [];

            foreach ($entriNyeri as $entri) {
                $observationList = NyeriKesadaranObservationMap::nyeri($entri);
                if ($observationList === []) {
                    $kodeSkala = (string) ($entri['nyeri']['nyeriMetode']['nyeriMetode'] ?? '');
                    if ($kodeSkala !== '' && !NyeriKesadaranObservationMap::skalaDidukung($kodeSkala)) {
                        $dilewati[$kodeSkala] = true;
                    }
                    continue;
                }
                $payloadDasar = $this->baseFor($entri['tglPenilaian'] ?? '', $dataRJ, $patientId, $satuSehat['encounterId'], $practitionerId);
                foreach ($observationList as $observation) {
                    $respons = $this->createObservation(array_merge($payloadDasar, $observation));
                    if (!empty($respons['id'])) { $idList[] = $respons['id']; }
                }
            }

            // Kesadaran diambil dari tanda vital, jadi waktunya mengikuti kunjungan.
            foreach (NyeriKesadaranObservationMap::kesadaran($kesadaran) as $observation) {
                $payloadDasar = $this->baseFor('', $dataRJ, $patientId, $satuSehat['encounterId'], $practitionerId);
                $respons = $this->createObservation(array_merge($payloadDasar, $observation));
                if (!empty($respons['id'])) { $idList[] = $respons['id']; }
            }

            $catatanLewat = $dilewati === [] ? '' : ' ' . count($dilewati) . ' skala dilewati (' . implode(', ', array_keys($dilewati)) . ') — belum ada kode resmi SATUSEHAT.';

            if (empty($idList)) {
                $this->dispatch('toast', type: 'info', message: 'Tidak ada yang bisa dikirim.' . $catatanLewat);
                return;
            }

            $satuSehat['nyeriKesadaranObservationIds'] = $idList;
            $this->saveResult($rjNo, $satuSehat);
            $this->dispatch('toast', type: $dilewati === [] ? 'success' : 'warning', message: 'Nyeri & kesadaran terkirim (' . count($idList) . ' observation).' . $catatanLewat);
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            // Simpan dulu id yang TERLANJUR terbentuk di SATUSEHAT sebelum melapor gagal —
            // tanpa ini resource-nya jadi yatim dan percobaan berikutnya menumpuk duplikat.
            try {
                if (!empty($idList)) {
                    $satuSehat['nyeriKesadaranObservationIds'] = $idList;
                    $this->saveResult($rjNo, $satuSehat);
                }
            } catch (\Throwable) {
            }
            $this->dispatch('toast', type: 'error', message: 'Nyeri & kesadaran gagal: ' . $this->ringkasErrorSatuSehat($e));
        }
    }

    private function baseFor(string $waktu, array $dataRJ, string $patientId, string $encounterId, string $practitionerId): array
    {
        $waktu = trim($waktu);
        $isoDate = ($waktu !== '' ? $this->parseDate($waktu) : $this->parseDate($dataRJ['rjDate'] ?? ''))->toIso8601String();

        return [
            'patientId'     => $patientId,
            'encounterId'   => $encounterId,
            'performerId'   => $practitionerId,
            'effectiveDate' => $isoDate,
        ];
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
        if (empty($teksTanggal)) { return Carbon::now(); }
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
                <div class="font-semibold text-gray-800 dark:text-gray-100">Nyeri &amp; Kesadaran</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Skala nyeri per penilaian dan tingkat kesadaran dari tanda vital.
                </div>
                <div class="mt-1 text-xs {{ $nyeriSiap > 0 || $kesadaranLabel !== '' ? 'text-gray-500 dark:text-gray-400' : 'text-amber-600 dark:text-amber-400' }}">
                    @if ($nyeriSiap > 0 || $kesadaranLabel !== '')
                        {{ $nyeriSiap }} nyeri siap dikirim{{ $kesadaranLabel !== '' ? ', kesadaran: ' . $kesadaranLabel : '' }}.
                    @else
                        Belum ada skala nyeri ber-skor maupun tingkat kesadaran — Kirim akan ditolak.
                    @endif
                </div>
                @if ($nyeriDilewati > 0)
                    {{-- Yang tak terkirim tidak boleh hilang diam-diam --}}
                    <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                        {{ $nyeriDilewati }} entri memakai skala yang belum punya kode resmi SATUSEHAT — tidak ikut terkirim.
                    </div>
                @endif
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-emerald-600 dark:text-emerald-400">{{ $count }} terkirim</div>
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
        kosong="Belum ada penilaian nyeri maupun tingkat kesadaran — Kirim akan ditolak sampai salah satunya diisi." />
</div>
