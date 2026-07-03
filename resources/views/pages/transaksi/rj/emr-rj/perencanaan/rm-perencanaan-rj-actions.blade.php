<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Carbon\Carbon;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];

    // renderVersions
    public array $renderVersions = [];
    protected array $renderAreas = ['modal-perencanaan-rj'];

    // Untuk modal E-Resep
    public string $isOpenModeEresepRJ = 'insert';
    public string $activeTabRacikanNonRacikan = 'NonRacikan';
    public array $EmrMenuRacikanNonRacikan = [['ermMenuId' => 'NonRacikan', 'ermMenuName' => 'NonRacikan'], ['ermMenuId' => 'Racikan', 'ermMenuName' => 'Racikan']];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-perencanaan-rj']);
    }

    public function rendering(): void
    {
        $default = $this->getDefaultPerencanaan();
        $current = $this->dataDaftarPoliRJ['perencanaan'] ?? [];
        $this->dataDaftarPoliRJ['perencanaan'] = array_replace_recursive($default, $current);
    }

    /* -------------------------
     | Tindak Lanjut options dari BPJS PCare cache (ref_bpjs_table).
     | Pakai kategori 'Status Pulang RJ' (siklik-lite convention) — di-sync
     | via Master > Ref BPJS. Lookup case-insensitive supaya toleran terhadap
     | klinik existing yg casing-nya beda (UPPERCASE/lowercase). Cache kosong
     | → return [] dan UI kasih hint untuk sync.
     * ------------------------- */
    #[Computed]
    public function statusPulangOptions(): array
    {
        $json = DB::table('ref_bpjs_table')
            ->whereRaw('upper(ref_keterangan) = upper(?)', ['Status Pulang RJ'])
            ->value('ref_json');

        if (!$json) return [];

        $list = json_decode($json, true);
        if (!is_array($list)) return [];

        // Auto-detect key kode (kdStatusPulang/kode/id) & nama (nmStatusPulang/nama).
        $first = (array) ($list[0] ?? []);
        $codeKey = collect(array_keys($first))->first(fn($k) =>
            str_starts_with(strtolower($k), 'kd') || strtolower($k) === 'kode'
        ) ?? array_key_first($first);
        $nameKey = collect(array_keys($first))->first(fn($k) =>
            str_starts_with(strtolower($k), 'nm') || strtolower($k) === 'nama'
        ) ?? $codeKey;

        return collect($list)->map(fn($it) => [
            'kd' => (string) ((array) $it)[$codeKey] ?? '',
            'nm' => (string) ((array) $it)[$nameKey] ?? '',
        ])->filter(fn($o) => $o['kd'] !== '')->values()->all();
    }

    /**
     * Handler change select kdStatusPulang — set juga tindakLanjut.tindakLanjut
     * (display name) untuk konsistensi data lama.
     */
    public function changeStatusPulang(string $kdStatusPulang): void
    {
        $opts = $this->statusPulangOptions;
        $found = collect($opts)->firstWhere('kd', $kdStatusPulang);
        $nm = $found['nm'] ?? '';

        $this->dataDaftarPoliRJ['perencanaan']['kdStatusPulang']           = $kdStatusPulang;
        $this->dataDaftarPoliRJ['perencanaan']['tindakLanjut']['tindakLanjut'] = $nm;
    }

    /* ===============================
     | OPEN REKAM MEDIS - PERENCANAAN
     =============================== */
    #[On('open-rm-perencanaan-rj')]
    public function openPerencanaan($rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->rjNo = $rjNo;

        $this->resetForm();
        $this->resetValidation();

        $dataDaftarPoliRJ = $this->findDataRJ($rjNo);

        if (!$dataDaftarPoliRJ) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $dataDaftarPoliRJ;

        // Initialize perencanaan data jika belum ada
        $this->dataDaftarPoliRJ['perencanaan'] ??= $this->getDefaultPerencanaan();

        // 🔥 INCREMENT: Refresh seluruh modal perencanaan
        $this->incrementVersion('modal-perencanaan-rj');

        // Cek status lock
        if ($this->checkEmrRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }
    }

    /* ===============================
     | GET DEFAULT PERENCANAAN STRUCTURE
     =============================== */
    private function getDefaultPerencanaan(): array
    {
        return [
            'pengkajianMedisTab' => 'Petugas Medis',
            'pengkajianMedis' => [
                'waktuPemeriksaan' => '',
                'selesaiPemeriksaan' => '',
                'drPemeriksa' => '',
            ],

            'tindakLanjutTab' => 'Tindak Lanjut',
            'kdStatusPulang' => '', // BPJS PCare kdStatusPulang (cached di ref_bpjs_table)
            'tindakLanjut' => [
                'tindakLanjut' => '',
                'keteranganTindakLanjut' => '',
                'tindakLanjutOptions' => [], // di-overwrite oleh computed statusPulangOptions dari ref_bpjs_table
            ],

            'terapiTab' => 'Terapi',
            'terapi' => [
                'terapi' => '',
            ],

            // 'dischargePlanningTab' => 'Discharge Planning', // TIDAK DIPAKAI
            // 'dischargePlanning' => [                         // TIDAK DIPAKAI
            //     'pelayananBerkelanjutan' => [
            //         'pelayananBerkelanjutan' => 'Tidak Ada',
            //         'pelayananBerkelanjutanOption' => [
            //             ['pelayananBerkelanjutan' => 'Tidak Ada'],
            //             ['pelayananBerkelanjutan' => 'Ada']
            //         ],
            //     ],
            //     'pelayananBerkelanjutanOpsi' => [
            //         'rawatLuka' => [],
            //         'dm' => [],
            //         'ppok' => [],
            //         'hivAids' => [],
            //         'dmTerapiInsulin' => [],
            //         'ckd' => [],
            //         'tb' => [],
            //         'stroke' => [],
            //         'kemoterapi' => [],
            //     ],
            //     'penggunaanAlatBantu' => [
            //         'penggunaanAlatBantu' => 'Tidak Ada',
            //         'penggunaanAlatBantuOption' => [
            //             ['penggunaanAlatBantu' => 'Tidak Ada'],
            //             ['penggunaanAlatBantu' => 'Ada']
            //         ],
            //     ],
            //     'penggunaanAlatBantuOpsi' => [
            //         'kateterUrin' => [],
            //         'ngt' => [],
            //         'traechotomy' => [],
            //         'colostomy' => [],
            //     ],
            // ],
        ];
    }

    /* ===============================
     | SYNC JSON — private helper
     | Dipanggil dari dalam transaksi yang sudah ada lockRJRow()-nya.
     | Tidak membungkus transaction/lock sendiri untuk menghindari nested.
     =============================== */
    private function syncPerencanaanJson(): void
    {
        $data = $this->findDataRJ($this->rjNo) ?? [];

        if (empty($data)) {
            throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
        }

        // Set hanya key milik komponen ini — key lain tidak tersentuh
        $data['perencanaan'] = $this->dataDaftarPoliRJ['perencanaan'] ?? [];

        // ermStatus dikelola dari setDrPemeriksa
        if (isset($this->dataDaftarPoliRJ['ermStatus'])) {
            $data['ermStatus'] = $this->dataDaftarPoliRJ['ermStatus'];
        }

        $this->updateJsonRJ($this->rjNo, $data);
        $this->dataDaftarPoliRJ = $data;
    }

    /* ===============================
     | SAVE — standalone via #[On] event (tombol simpan manual)
     =============================== */
    #[On('save-rm-perencanaan-rj')]
    public function save(): void
    {
        // 1. Read-only guard — selalu dengan toast
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan data.');
            return;
        }

        // 2. Guard: properti lokal belum ter-load
        if (empty($this->dataDaftarPoliRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan, silakan buka ulang form.');
            return;
        }

        // 3. Validasi Livewire rules
        $this->validateWithToast();

        try {
            DB::transaction(function () {
                // 4. Lock row di DB (SELECT FOR UPDATE) — cegah race condition
                $this->lockRJRow($this->rjNo);

                // Tangkap status baru/lama sebelum sync (key perencanaan belum ada saat pertama disimpan)
                $isBaru = empty(($this->findDataRJ($this->rjNo) ?? [])['perencanaan']);

                // 5. Sync JSON via helper
                $this->syncPerencanaanJson();

                // 6. Audit log (kategori Rekam Medis)
                $this->appendAdminLogRJ((int) $this->rjNo, ($isBaru ? 'Buat' : 'Update') . ' Perencanaan RJ — waktu pemeriksaan ' . ($this->dataDaftarPoliRJ['perencanaan']['pengkajianMedis']['waktuPemeriksaan'] ?? '-'), 'MR');
            });

            $this->afterSave('Perencanaan berhasil disimpan.');
        } catch (\RuntimeException $e) {
            // lockRJRow() / syncPerencanaanJson() throws RuntimeException
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | VALIDASI SEBELUM DOKTER TTD
     =============================== */
    private function validateBeforeDrPemeriksa(): void
    {
        try {
            $this->validateWithToast([
                'dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNadi' => 'required|numeric',
                'dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNafas' => 'required|numeric',
                'dataDaftarPoliRJ.pemeriksaan.tandaVital.suhu' => 'required|numeric',
                'dataDaftarPoliRJ.pemeriksaan.nutrisi.bb' => 'required|numeric',
                'dataDaftarPoliRJ.pemeriksaan.nutrisi.tb' => 'required|numeric',
                'dataDaftarPoliRJ.pemeriksaan.nutrisi.imt' => 'required|numeric',
                'dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.jamDatang' => 'required|date_format:d/m/Y H:i:s',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak dapat melakukan TTD-E karena data pemeriksaan belum lengkap.');
            throw $e;
        }
    }

    /* ===============================
     | SET DOKTER PEMERIKSA (TTD)
     =============================== */
    public function setDrPemeriksa(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $myUserCodeActive = auth()->user()->myuser_code;
        $myUserNameActive = auth()->user()->myuser_name;

        // Validasi data pemeriksaan sudah lengkap sebelum masuk lock
        try {
            $this->validateBeforeDrPemeriksa();
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Pesan sudah di-dispatch di dalam validateBeforeDrPemeriksa()
            return;
        }

        if (!auth()->user()->hasRole('Dokter')) {
            $this->dispatch('toast', type: 'error', message: "Anda tidak dapat melakukan TTD-E karena User Role {$myUserNameActive} Bukan Dokter.");
            return;
        }

        if (($this->dataDaftarPoliRJ['drId'] ?? '') !== $myUserCodeActive) {
            $this->dispatch('toast', type: 'error', message: "Anda tidak dapat melakukan TTD-E karena Bukan Pasien {$myUserNameActive}.");
            return;
        }

        try {
            DB::transaction(function () {
                // 1. Lock row dulu — update erm_status + JSON harus atomik dalam satu transaksi
                $this->lockRJRow($this->rjNo);

                $drDesc = $this->dataDaftarPoliRJ['drDesc'] ?? 'Dokter Pemeriksa';

                // 2. Set data perencanaan
                $this->dataDaftarPoliRJ['perencanaan']['pengkajianMedis']['drPemeriksa'] = $drDesc;

                // Auto-isi waktu pemeriksaan jika belum diisi
                $this->dataDaftarPoliRJ['perencanaan']['pengkajianMedis']['waktuPemeriksaan'] ??= Carbon::now()->format('d/m/Y H:i:s');

                // Auto-isi selesai pemeriksaan jika belum diisi
                $this->dataDaftarPoliRJ['perencanaan']['pengkajianMedis']['selesaiPemeriksaan'] ??= Carbon::now()->format('d/m/Y H:i:s');

                // 3. Update erm_status di header — dalam satu transaksi dengan JSON update
                $this->dataDaftarPoliRJ['ermStatus'] = 'L';
                DB::table('rstxn_rjhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update(['erm_status' => 'L']);

                // 4. Sync JSON — row sudah di-lock, tidak perlu lock/transaction lagi
                $this->syncPerencanaanJson();

                // 5. Audit log (kategori Rekam Medis) — TTD-E mengunci EMR
                $this->appendAdminLogRJ((int) $this->rjNo, 'TTD-E Dokter Pemeriksa (kunci EMR) — ' . $drDesc . ' @ ' . ($this->dataDaftarPoliRJ['perencanaan']['pengkajianMedis']['waktuPemeriksaan'] ?? '-'), 'MR');
            });

            $this->afterSave('TTD-E berhasil.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal TTD-E: ' . $e->getMessage());
        }
    }

    /* ===============================
     | OPEN MODAL E-RESEP
     =============================== */
    public function openModalEresepRJ(): void
    {
        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor kunjungan tidak ditemukan.');
            return;
        }

        $this->dispatch('emr-rj.eresep.open', rjNo: $this->rjNo);
        $this->dispatch('open-eresep-non-racikan-rj', rjNo: $this->rjNo);
        $this->dispatch('open-eresep-racikan-rj', rjNo: $this->rjNo);
    }

    /* ===============================
     | VALIDATION RULES
     =============================== */
    protected function rules(): array
    {
        return [
            'dataDaftarPoliRJ.perencanaan.pengkajianMedis.waktuPemeriksaan' => 'nullable|date_format:d/m/Y H:i:s',
            'dataDaftarPoliRJ.perencanaan.pengkajianMedis.selesaiPemeriksaan' => 'nullable|date_format:d/m/Y H:i:s',
        ];
    }

    protected function messages(): array
    {
        return [
            'dataDaftarPoliRJ.perencanaan.pengkajianMedis.waktuPemeriksaan.date_format' => ':attribute harus dalam format dd/mm/yyyy hh:mi:ss',
            'dataDaftarPoliRJ.perencanaan.pengkajianMedis.selesaiPemeriksaan.date_format' => ':attribute harus dalam format dd/mm/yyyy hh:mi:ss',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'dataDaftarPoliRJ.perencanaan.pengkajianMedis.waktuPemeriksaan' => 'Waktu Pemeriksaan',
            'dataDaftarPoliRJ.perencanaan.pengkajianMedis.selesaiPemeriksaan' => 'Selesai Pemeriksaan',
        ];
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-perencanaan-actions');
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function afterSave(string $message): void
    {
        $this->incrementVersion('modal-perencanaan-rj');
        $this->dispatch('refresh-after-rj.saved');
        $this->dispatch('toast', type: 'success', message: $message);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
    }
};

?>

<div>
    {{-- CONTAINER UTAMA --}}
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-perencanaan-rj', [$rjNo ?? 'new']) }}">

        {{-- BODY --}}
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                {{-- jika perencanaan ada --}}
                @if (isset($dataDaftarPoliRJ['perencanaan']))
                    <div class="w-full">
                        <div id="TransaksiRawatJalan" x-data="{ activeTab: '{{ $dataDaftarPoliRJ['perencanaan']['pengkajianMedisTab'] ?? 'Petugas Medis' }}' }" class="w-full">

                            {{-- TAB NAVIGATION --}}
                            <x-scrollable-tabs class="w-full px-2 mb-2 border-b border-gray-200 dark:border-gray-700">
                                <ul
                                    class="flex flex-nowrap whitespace-nowrap w-full -mb-px text-xs font-medium text-center text-gray-500 dark:text-gray-400">

                                    {{-- PETUGAS MEDIS TAB --}}
                                    <li class="mr-2">
                                        <label
                                            class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarPoliRJ['perencanaan']['pengkajianMedisTab'] ?? 'Petugas Medis' }}'
                                                ?
                                                'text-primary border-primary bg-gray-100' : ''"
                                            @click="activeTab = '{{ $dataDaftarPoliRJ['perencanaan']['pengkajianMedisTab'] ?? 'Petugas Medis' }}'">
                                            {{ $dataDaftarPoliRJ['perencanaan']['pengkajianMedisTab'] ?? 'Petugas Medis' }}
                                        </label>
                                    </li>

                                    {{-- TINDAK LANJUT TAB --}}
                                    <li class="mr-2">
                                        <label
                                            class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarPoliRJ['perencanaan']['tindakLanjutTab'] ?? 'Tindak Lanjut' }}'
                                                ?
                                                'text-primary border-primary bg-gray-100' : ''"
                                            @click="activeTab = '{{ $dataDaftarPoliRJ['perencanaan']['tindakLanjutTab'] ?? 'Tindak Lanjut' }}'">
                                            {{ $dataDaftarPoliRJ['perencanaan']['tindakLanjutTab'] ?? 'Tindak Lanjut' }}
                                        </label>
                                    </li>

                                    {{-- TERAPI TAB --}}
                                    {{-- <li class="mr-2">
                                        <label
                                            class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarPoliRJ['perencanaan']['terapiTab'] ?? 'Terapi' }}'
                                                ? 'text-primary border-primary bg-gray-100' : ''"
                                            @click="activeTab = '{{ $dataDaftarPoliRJ['perencanaan']['terapiTab'] ?? 'Terapi' }}'">
                                            {{ $dataDaftarPoliRJ['perencanaan']['terapiTab'] ?? 'Terapi' }}
                                        </label>
                                    </li> --}}

                                    {{-- DISCHARGE PLANNING TAB --}}
                                    {{-- <li class="mr-2">
                                        <label
                                            class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarPoliRJ['perencanaan']['dischargePlanningTab'] ?? 'Discharge Planning' }}'
                                                ? 'text-primary border-primary bg-gray-100' : ''"
                                            @click="activeTab = '{{ $dataDaftarPoliRJ['perencanaan']['dischargePlanningTab'] ?? 'Discharge Planning' }}'">
                                            {{ $dataDaftarPoliRJ['perencanaan']['dischargePlanningTab'] ?? 'Discharge Planning' }}
                                        </label>
                                    </li> --}}

                                </ul>
                            </x-scrollable-tabs>

                            {{-- TAB CONTENTS --}}
                            <div class="w-full p-2">

                                {{-- PETUGAS MEDIS TAB --}}
                                <div class="w-full"
                                    x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarPoliRJ['perencanaan']['pengkajianMedisTab'] ?? 'Petugas Medis' }}'">
                                    @include('pages.transaksi.rj.emr-rj.perencanaan.tabs.petugas-medis-tab')
                                </div>

                                {{-- TINDAK LANJUT TAB --}}
                                <div class="w-full"
                                    x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarPoliRJ['perencanaan']['tindakLanjutTab'] ?? 'Tindak Lanjut' }}'">
                                    @include('pages.transaksi.rj.emr-rj.perencanaan.tabs.tindak-lanjut-tab')
                                </div>

                                {{-- TERAPI TAB --}}
                                {{-- <div class="w-full"
                                    x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarPoliRJ['perencanaan']['terapiTab'] ?? 'Terapi' }}'">
                                    @include('pages.transaksi.rj.emr-rj.perencanaan.tabs.terapi-tab')
                                </div> --}}

                                {{-- DISCHARGE PLANNING TAB --}}
                                {{-- @if (isset($dataDaftarPoliRJ['perencanaan']['dischargePlanningTab']))
                                    <div class="w-full"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarPoliRJ['perencanaan']['dischargePlanningTab'] ?? 'Discharge Planning' }}'">
                                        @include('pages.transaksi.rj.emr-rj.perencanaan.tabs.discharge-planning-tab')
                                    </div>
                                @endif --}}

                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Eresep RJ --}}
    <livewire:pages::transaksi.rj.eresep-rj.eresep-rj :rjNo="$rjNo" wire:key="eresep-rj-{{ $rjNo }}" />
</div>
