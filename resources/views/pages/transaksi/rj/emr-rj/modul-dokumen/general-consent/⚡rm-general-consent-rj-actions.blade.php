<?php
// resources/views/pages/transaksi/rj/emr-rj/modul-dokumen/general-consent/rm-general-consent-rj-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public bool $disabled = false;
    public array $dataDaftarPoliRJ = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-general-consent-rj'];

    // ── Form fields — top-level untuk wire:model ──
    public string $wali = '';
    public string $waliHubungan = ''; // Hubungan wali dengan pasien — HPK 4.2
    public string $agreement = '1'; // 1=Setuju, 0=Tidak Setuju
    public string $signature = ''; // base64 dari canvas/signpad

    // HPK 1 EP-c — Pihak yg diberi akses info medis (max 5 baris).
    public array $pihakInfoMedis = [
        ['nama' => '', 'hubungan' => '', 'noHp' => ''],
    ];

    public array $agreementOptions = [['value' => '1', 'label' => 'Setuju'], ['value' => '0', 'label' => 'Tidak Setuju']];

    public array $waliHubunganOptions = [
        ['value' => 'pasien', 'label' => 'Pasien Sendiri'],
        ['value' => 'suami', 'label' => 'Suami'],
        ['value' => 'istri', 'label' => 'Istri'],
        ['value' => 'ayah', 'label' => 'Ayah'],
        ['value' => 'ibu', 'label' => 'Ibu'],
        ['value' => 'anak', 'label' => 'Anak'],
        ['value' => 'saudara', 'label' => 'Saudara'],
        ['value' => 'wali_hukum', 'label' => 'Wali Hukum'],
        ['value' => 'lainnya', 'label' => 'Lainnya'],
    ];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?int $rjNo = null, bool $disabled = false): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-general-consent-rj']);

        if ($this->rjNo) {
            $data = $this->findDataRJ($this->rjNo);
            if ($data) {
                $this->dataDaftarPoliRJ = $data;
                $this->isFormLocked = $this->checkEmrRJStatus($this->rjNo) || $disabled;
            }
        }
    }

    public function rendering(): void
    {
        $default = $this->getDefaultGeneralConsent();
        $current = $this->dataDaftarPoliRJ['generalConsentPasienRJ'] ?? [];
        $this->dataDaftarPoliRJ['generalConsentPasienRJ'] = array_replace_recursive($default, $current);
    }

    /* ===============================
     | OPEN MODAL
     =============================== */
    public function openModal(): void
    {
        if (!$this->rjNo || $this->disabled) {
            return;
        }

        $this->resetValidation();

        $data = $this->findDataRJ($this->rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $data;
        $this->dataDaftarPoliRJ['generalConsentPasienRJ'] ??= $this->getDefaultGeneralConsent();

        $consent = $this->dataDaftarPoliRJ['generalConsentPasienRJ'];
        $this->wali = $consent['wali'] ?? '';
        $this->waliHubungan = $consent['waliHubungan'] ?? '';
        $this->agreement = $consent['agreement'] ?? '1';
        $this->signature = $consent['signature'] ?? '';

        $loaded = $consent['pihakInfoMedis'] ?? [];
        $this->pihakInfoMedis = !empty($loaded) ? $loaded : [['nama' => '', 'hubungan' => '', 'noHp' => '']];

        $this->isFormLocked = $this->checkEmrRJStatus($this->rjNo) || $this->disabled;
        $this->incrementVersion('modal-general-consent-rj');

        $this->dispatch('open-modal', name: "rm-general-consent-rj-{$this->rjNo}");
    }

    /* ===============================
     | CLOSE
     =============================== */
    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-general-consent-rj-{$this->rjNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'signature' => 'required|string',
            'wali' => 'required|string|max:200',
            'waliHubungan' => 'required|string|max:50',
            'agreement' => 'required|in:1',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'in' => ':attribute tidak valid.',
            'agreement.in' => 'Persetujuan Pelayanan harus "Setuju" agar General Consent dapat diproses.',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'signature' => 'Tanda tangan pasien/wali',
            'wali' => 'Nama wali',
            'waliHubungan' => 'Hubungan wali',
            'agreement' => 'Persetujuan',
        ];
    }

    /* ===============================
     | UPDATED HOOKS — sync top-level → nested
     =============================== */
    public function updated(string $name, mixed $value): void
    {
        $map = [
            'wali' => 'wali',
            'waliHubungan' => 'waliHubungan',
            'agreement' => 'agreement',
        ];
        if (isset($map[$name])) {
            $this->dataDaftarPoliRJ['generalConsentPasienRJ'][$map[$name]] = $value;
        }

        // Sync pihakInfoMedis (nested wire:model live)
        if (str_starts_with($name, 'pihakInfoMedis.')) {
            $this->dataDaftarPoliRJ['generalConsentPasienRJ']['pihakInfoMedis'] = $this->pihakInfoMedis;
        }

        if ($name === 'agreement') {
            $this->validateOnly('agreement');
        }
    }

    public function addPihakInfo(): void
    {
        if ($this->formReadOnly()) {
            return;
        }
        if (count($this->pihakInfoMedis) >= 5) {
            $this->dispatch('toast', type: 'warning', message: 'Maksimal 5 pihak.');
            return;
        }
        $this->pihakInfoMedis[] = ['nama' => '', 'hubungan' => '', 'noHp' => ''];
    }

    public function removePihakInfo(int $index): void
    {
        if ($this->formReadOnly()) {
            return;
        }
        if (count($this->pihakInfoMedis) <= 1) {
            $this->pihakInfoMedis = [['nama' => '', 'hubungan' => '', 'noHp' => '']];
        } else {
            unset($this->pihakInfoMedis[$index]);
            $this->pihakInfoMedis = array_values($this->pihakInfoMedis);
        }
        $this->dataDaftarPoliRJ['generalConsentPasienRJ']['pihakInfoMedis'] = $this->pihakInfoMedis;
    }

    /* ===============================
     | SET SIGNATURE
     =============================== */
    public function setSignature(string $dataUrl): void
    {
        if ($this->formReadOnly()) {
            return;
        }

        $this->signature = $dataUrl;
        $this->dataDaftarPoliRJ['generalConsentPasienRJ']['signature'] = $dataUrl;
        $this->dataDaftarPoliRJ['generalConsentPasienRJ']['signatureDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | CLEAR SIGNATURE
     =============================== */
    public function clearSignature(): void
    {
        if ($this->formReadOnly()) {
            return;
        }

        $this->signature = '';
        $this->dataDaftarPoliRJ['generalConsentPasienRJ']['signature'] = '';
        $this->dataDaftarPoliRJ['generalConsentPasienRJ']['signatureDate'] = '';
        $this->incrementVersion('modal-general-consent-rj');
    }

    /* ===============================
     | STATUS FINAL
     |
     | General Consent tidak punya flag 'finalized' di JSON (dan sengaja TIDAK
     | ditambahkan supaya bentuk node lama tetap sama). Penanda final = stempel
     | petugas pemberi penjelasan sudah terisi — itu aksi TERAKHIR yang sekaligus
     | mengunci entri (standar modul dokumen).
     =============================== */
    public function entriFinal(): bool
    {
        return !empty($this->dataDaftarPoliRJ['generalConsentPasienRJ']['petugasPemeriksa'] ?? '');
    }

    /** Form tidak bisa diubah: EMR terkunci, dipanggil disabled, atau entri sudah final. */
    public function formReadOnly(): bool
    {
        return $this->isFormLocked || $this->entriFinal();
    }

    /* ===============================
     | TTD PETUGAS PEMBERI PENJELASAN = aksi TERAKHIR + PENGUNCI
     |
     | Validasi penuh + stempel + simpan seluruh isi consent dalam satu aksi.
     | JANGAN sediakan tombol "Simpan & Kunci" terpisah (dua jalan mengunci).
     =============================== */
    public function setPetugasPemeriksa(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        if ($this->entriFinal()) {
            $this->dispatch('toast', type: 'error', message: 'Tanda tangan petugas pemberi penjelasan sudah ada.');
            return;
        }

        // Stempel ditulis ke form DULU supaya ikut tersimpan sekali jalan…
        $stempelLama = [
            'petugasPemeriksa' => $this->dataDaftarPoliRJ['generalConsentPasienRJ']['petugasPemeriksa'] ?? '',
            'petugasPemeriksaCode' => $this->dataDaftarPoliRJ['generalConsentPasienRJ']['petugasPemeriksaCode'] ?? '',
            'petugasPemeriksaDate' => $this->dataDaftarPoliRJ['generalConsentPasienRJ']['petugasPemeriksaDate'] ?? '',
        ];

        $this->dataDaftarPoliRJ['generalConsentPasienRJ']['petugasPemeriksa'] = auth()->user()->myuser_name ?? '';
        $this->dataDaftarPoliRJ['generalConsentPasienRJ']['petugasPemeriksaCode'] = auth()->user()->myuser_code ?? '';
        $this->dataDaftarPoliRJ['generalConsentPasienRJ']['petugasPemeriksaDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        // …tetapi WAJIB dicabut lagi saat validasi gagal. Kalau tidak, stempel
        // tersangkut di layar, tombol TTD hilang (komponen menganggap sudah TTD),
        // padahal tidak ada yang tersimpan.
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->dataDaftarPoliRJ['generalConsentPasienRJ'] = array_replace(
                $this->dataDaftarPoliRJ['generalConsentPasienRJ'],
                $stempelLama,
            );
            throw $e;
        }

        try {
            DB::transaction(function () {
                $this->lockRJRow($this->rjNo);

                $data = $this->findDataRJ($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan.');
                }

                $data['generalConsentPasienRJ'] = array_replace(
                    $data['generalConsentPasienRJ'] ?? $this->getDefaultGeneralConsent(),
                    $this->dataDaftarPoliRJ['generalConsentPasienRJ'] ?? [],
                );

                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
                $this->appendAdminLogRJ((int) $this->rjNo, 'TTD Petugas Pemberi Penjelasan General Consent — TTD pasien ' . ($data['generalConsentPasienRJ']['signatureDate'] ?? '-'), 'MR');
            });

            $this->incrementVersion('modal-general-consent-rj');
            $this->dispatch('toast', type: 'success', message: 'Tanda tangan petugas pemberi penjelasan berhasil disimpan, dokumen terkunci.');
            $this->dispatch('refresh-modul-dokumen-rj-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dataDaftarPoliRJ['generalConsentPasienRJ'] = array_replace(
                $this->dataDaftarPoliRJ['generalConsentPasienRJ'],
                $stempelLama,
            );
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dataDaftarPoliRJ['generalConsentPasienRJ'] = array_replace(
                $this->dataDaftarPoliRJ['generalConsentPasienRJ'],
                $stempelLama,
            );
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BUKA KUNCI = cabut TTD petugas
     |
     | Hanya mencabut stempel PETUGAS; TTD pasien/wali DIPERTAHANKAN (tak boleh
     | dihapus sepihak oleh staf). Gate dua lapis: @can di tombol + cek di sini.
     =============================== */
    public function cabutTtdPetugas(): void
    {
        if (!auth()->user()?->can('dokumen.bukaKunci')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang membuka kunci dokumen.');
            return;
        }

        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'EMR terkunci, dokumen tidak dapat dibuka.');
            return;
        }

        if (!$this->entriFinal()) {
            $this->dispatch('toast', type: 'warning', message: 'Dokumen belum ditandatangani petugas.');
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockRJRow($this->rjNo);

                $data = $this->findDataRJ($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan.');
                }

                $petugasLama = $data['generalConsentPasienRJ']['petugasPemeriksa'] ?? '-';

                $data['generalConsentPasienRJ']['petugasPemeriksa'] = '';
                $data['generalConsentPasienRJ']['petugasPemeriksaCode'] = '';
                $data['generalConsentPasienRJ']['petugasPemeriksaDate'] = '';

                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
                $this->appendAdminLogRJ((int) $this->rjNo, 'Buka Kunci General Consent — TTD petugas ' . $petugasLama . ' dicabut oleh ' . (auth()->user()->myuser_name ?? '-'), 'MR');
            });

            $this->incrementVersion('modal-general-consent-rj');
            $this->dispatch('toast', type: 'success', message: 'Kunci dibuka, tanda tangan petugas dicabut.');
            $this->dispatch('refresh-modul-dokumen-rj-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | SAVE
     =============================== */
    /**
     * Simpan Draft — TANPA validasi penuh (formulir klinis diisi bertahap).
     * Validasi penuh + kunci ada di setPetugasPemeriksa() (TTD petugas).
     */
    #[On('save-rm-general-consent-rj')]
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        // Entri final tak boleh ditimpa — buka kunci dulu (cabut TTD petugas).
        if ($this->entriFinal()) {
            $this->dispatch('toast', type: 'error', message: 'Dokumen sudah ditandatangani petugas dan terkunci. Buka kunci dulu untuk mengubah.');
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockRJRow($this->rjNo);

                $data = $this->findDataRJ($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
                }

                // Tangkap status baru/lama sebelum overwrite — pakai signatureDate
                // (key generalConsentPasienRJ bisa pre-init, tapi signatureDate baru terisi saat sudah disimpan/ditandatangani)
                $isBaru = empty($data['generalConsentPasienRJ']['signatureDate'] ?? '');

                $data['generalConsentPasienRJ'] = array_replace($data['generalConsentPasienRJ'] ?? $this->getDefaultGeneralConsent(), $this->dataDaftarPoliRJ['generalConsentPasienRJ'] ?? []);

                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
                $this->appendAdminLogRJ((int) $this->rjNo, ($isBaru ? 'Buat' : 'Update') . ' General Consent — TTD ' . ($data['generalConsentPasienRJ']['signatureDate'] ?? '-'), 'MR');
            });

            $this->incrementVersion('modal-general-consent-rj');
            $this->dispatch('toast', type: 'success', message: 'General Consent berhasil disimpan.');
            $this->dispatch('refresh-modul-dokumen-rj-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK
     =============================== */
    public function cetak(): void
    {
        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor RJ tidak ditemukan.');
            return;
        }

        $this->dispatch('cetak-general-consent-rj.open', rjNo: $this->rjNo);
    }

    /* ===============================
     | DEFAULT STRUCTURE
     =============================== */
    private function getDefaultGeneralConsent(): array
    {
        return [
            'signature' => '',
            'signatureDate' => '',
            'wali' => '',
            'waliHubungan' => '',
            'agreement' => '1',
            'pihakInfoMedis' => [],
            'petugasPemeriksa' => '',
            'petugasPemeriksaCode' => '',
            'petugasPemeriksaDate' => '',
        ];
    }

    /* ===============================
     | HELPERS
     =============================== */
    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarPoliRJ = [];
        $this->signature = '';
        $this->wali = '';
        $this->waliHubungan = '';
        $this->agreement = '1';
        $this->pihakInfoMedis = [['nama' => '', 'hubungan' => '', 'noHp' => '']];
    }
};
?>

<div>
    @include('pages.transaksi.rj.emr-rj.modul-dokumen.general-consent.partials.kartu-ringkas')

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="rm-general-consent-rj-{{ $rjNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-general-consent-rj', [$rjNo ?? 'new']) }}">

            @include('pages.transaksi.rj.emr-rj.modul-dokumen.general-consent.partials.modal-header')

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    {{-- Display Pasien --}}
                    <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                        wire:key="gc-rj-display-pasien-{{ $rjNo ?? 'init' }}" />

                    <div
                        class="p-6 space-y-6 bg-white border border-gray-200 shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        @if ($isFormLocked)
                            <div
                                class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                EMR terkunci — data tidak dapat diubah.
                            </div>
                        @endif

                        @if ($this->entriFinal() && !$isFormLocked)
                            <div
                                class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-info-deep bg-info-tint border border-info/30 rounded-xl dark:bg-blue-900/20 dark:border-blue-700 dark:text-blue-200">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                Dokumen sudah ditandatangani petugas dan terkunci — buka kunci dulu untuk mengubah.
                            </div>
                        @endif

                        @if (isset($dataDaftarPoliRJ['generalConsentPasienRJ']))

                            @php
                                $consent = $dataDaftarPoliRJ['generalConsentPasienRJ'];
                                // Read-only gabungan: EMR terkunci ATAU entri sudah final (TTD petugas).
                                $formReadOnly = $this->formReadOnly();
                            @endphp

                            @include('pages.transaksi.rj.emr-rj.modul-dokumen.general-consent.partials.form-persetujuan')

                        @else
                            <div
                                class="flex flex-col items-center justify-center py-16 text-gray-300 dark:text-gray-600">
                                <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="text-base font-medium">Data RJ belum dimuat</p>
                            </div>
                        @endif

                    </div>
                </div>
            </div>

            @include('pages.transaksi.rj.emr-rj.modul-dokumen.general-consent.partials.modal-footer')

        </div>
    </x-modal>

    {{-- Cetak component --}}
    <livewire:pages::components.modul-dokumen.rj.general-consent.cetak-general-consent-rj
        wire:key="cetak-general-consent-rj-{{ $rjNo ?? 'init' }}" />
</div>
