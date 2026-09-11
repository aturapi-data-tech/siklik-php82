<?php
// resources/views/pages/transaksi/rj/emr-rj/modul-dokumen/inform-consent/rm-inform-consent-rj-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait, DokumenViewSupportTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public bool $disabled = false;
    public array $dataDaftarPoliRJ = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-inform-consent-rj'];

    public array $newConsent = [
        'tindakan' => '',
        'diagnosa' => '',
        'komplikasi' => '',
        'tujuan' => '',
        'resiko' => '',
        'alternatif' => '',
        'dokter' => '',
        'wali' => '',
        'waliHubungan' => '',
        'saksi' => '',
        'agreement' => '1',
        'dokterCode' => '',
        'dokterDate' => '',
        'petugasPemeriksa' => '',
        'petugasPemeriksaCode' => '',
        'petugasPemeriksaDate' => '',
    ];

    public string $signature = '';
    public string $signatureSaksi = '';

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

    public array $consentList = [];

    // Layar aktif di modal: 'daftar' (tabel entri) atau 'form' (isi/lanjutkan draft).
    // Formulir sengaja tidak nongkrong bersama daftarnya: dulu ia tampil terus lalu
    // dikosongkan diam-diam sesudah tersimpan, dan petugas yang mengira itu masih
    // formulir yang tadi diisi mengetik ulang — tersimpan sebagai draft baru.
    public string $layar = 'daftar';

    // Kunci entri yang sedang dilanjutkan = nilai signatureDate (stabil, bukan index array).
    public ?string $editingKey = null;

    // ── Layar "Lihat" (preview read-only = render blade cetak ke iframe) ──
    // Pola docs/dokumen-view-pattern.md; payload dibuat oleh buatDataCetak().
    public ?array $entriDilihat = null;
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.rj.inform-consent.cetak-inform-consent-rj-print';

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?int $rjNo = null, bool $disabled = false): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-inform-consent-rj']);

        if ($this->rjNo) {
            $data = $this->findDataRJ($this->rjNo);
            if ($data) {
                $this->dataDaftarPoliRJ = $data;
                $this->consentList = $data['informConsentPasienRJ'] ?? [];
                $this->isFormLocked = $this->checkEmrRJStatus($this->rjNo) || $disabled;
            }
        }
    }

    /* ===============================
     | OPEN MODAL
     =============================== */
    public function openModal(): void
    {
        if (!$this->rjNo || $this->disabled) {
            return;
        }

        $this->cancelEdit();
        $this->previewHtml = '';
        $this->entriDilihat = null;

        $data = $this->findDataRJ($this->rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $data;
        if (!isset($this->dataDaftarPoliRJ['informConsentPasienRJ']) || !is_array($this->dataDaftarPoliRJ['informConsentPasienRJ'])) {
            $this->dataDaftarPoliRJ['informConsentPasienRJ'] = [];
        }
        $this->consentList = $this->dataDaftarPoliRJ['informConsentPasienRJ'];
        $this->isFormLocked = $this->checkEmrRJStatus($this->rjNo) || $this->disabled;
        $this->incrementVersion('modal-inform-consent-rj');

        $this->dispatch('open-modal', name: "rm-inform-consent-rj-{$this->rjNo}");
    }

    /* ===============================
     | CLOSE
     =============================== */
    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-inform-consent-rj-{$this->rjNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newConsent.tindakan' => 'required|string|max:500',
            'newConsent.diagnosa' => 'nullable|string|max:500',
            'newConsent.komplikasi' => 'nullable|string|max:500',
            'newConsent.tujuan' => 'nullable|string',
            'newConsent.resiko' => 'nullable|string',
            'newConsent.alternatif' => 'nullable|string',
            // TTD pemberi informasi = aksi pengunci entri → wajib (aturan modul dokumen #1)
            'newConsent.dokter' => 'required|string',
            'newConsent.petugasPemeriksa' => 'required|string|max:150',
            'newConsent.wali' => 'required|string|max:200',
            'newConsent.waliHubungan' => 'required|string|max:50',
            // Saksi WAJIB saat kunci di semua dokumen ber-saksi (keputusan TTD #12a)
            'newConsent.saksi' => 'required|string|max:200',
            'newConsent.agreement' => 'required|in:0,1',
            'signature' => 'required|string',
            'signatureSaksi' => 'required|string',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'in' => ':attribute tidak valid.',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newConsent.tindakan' => 'Nama tindakan',
            'newConsent.diagnosa' => 'Diagnosa',
            'newConsent.komplikasi' => 'Komplikasi',
            'newConsent.tujuan' => 'Tujuan tindakan',
            'newConsent.resiko' => 'Risiko tindakan',
            'newConsent.alternatif' => 'Alternatif tindakan',
            'newConsent.dokter' => 'Tanda tangan pemberi informasi',
            'newConsent.wali' => 'Nama pasien/wali',
            'newConsent.waliHubungan' => 'Hubungan dengan pasien',
            'newConsent.saksi' => 'Nama saksi',
            'newConsent.agreement' => 'Persetujuan',
            'signature' => 'Tanda tangan pasien/wali',
            'signatureSaksi' => 'Tanda tangan saksi',
        ];
    }

    /* ===============================
     | SET SIGNATURES
     =============================== */
    public function setSignature(string $dataUrl): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signature = $dataUrl;
        $this->incrementVersion('modal-inform-consent-rj');
    }

    public function clearSignature(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signature = '';
        $this->incrementVersion('modal-inform-consent-rj');
    }

    public function setSignatureSaksi(string $dataUrl): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signatureSaksi = $dataUrl;
        $this->incrementVersion('modal-inform-consent-rj');
    }

    public function clearSignatureSaksi(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signatureSaksi = '';
        $this->incrementVersion('modal-inform-consent-rj');
    }

    /* ===============================
     | TTD PEMBERI INFORMASI = validasi penuh + stempel + KUNCI entri
     | (aksi terakhir sekaligus pengunci — tidak ada tombol "Simpan & Kunci" terpisah)
     =============================== */
    public function setDokterPenjelas(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        if (!empty($this->newConsent['dokter'])) {
            $this->dispatch('toast', type: 'warning', message: 'Tanda tangan pemberi informasi sudah ada.');
            return;
        }

        $stempelLama = [
            'dokter' => $this->newConsent['dokter'] ?? '',
            'dokterCode' => $this->newConsent['dokterCode'] ?? '',
            'dokterDate' => $this->newConsent['dokterDate'] ?? '',
        ];
        $this->newConsent['dokter'] = auth()->user()->myuser_name ?? '';
        $this->newConsent['dokterCode'] = auth()->user()->myuser_code ?? '';
        $this->newConsent['dokterDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->stempelPpa();

        // Stempel yang ditulis sebelum validate() WAJIB dicabut lagi saat validasi gagal —
        // kalau tidak, tombol TTD hilang (komponen mengira sudah TTD) padahal tak ada yang tersimpan.
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->newConsent = array_replace($this->newConsent, $stempelLama);
            $this->dispatch('toast', type: 'error', message: 'Lengkapi isian & tanda tangan pasien/saksi sebelum TTD pemberi informasi.');
            throw $e;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        if ($this->persistEntry($key, 'Kunci Inform Consent (TTD pemberi informasi)')) {
            $this->dispatch('toast', type: 'success', message: 'Inform Consent ditandatangani & dikunci.');
            $this->cancelEdit(); // kosongkan formulir = kembali ke daftar
        } else {
            $this->newConsent = array_replace($this->newConsent, $stempelLama);
        }
    }

    /* ===============================
     | PPA (Profesional Pemberi Asuhan) — combobox sumber tabel users
     | Nama ditulis langsung ke petugasPemeriksa via wire:model (combobox).
     | Tombol "Saya" mengisi PPA = akun login. Kode di-resolve saat simpan.
     =============================== */
    public function isiPpaSebagaiSaya(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->newConsent['petugasPemeriksa'] = auth()->user()->myuser_name ?? '';
        $this->newConsent['petugasPemeriksaCode'] = auth()->user()->myuser_code ?? '';
        $this->newConsent['petugasPemeriksaDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | SIMPAN DRAFT — tanpa validasi penuh; hanya nama tindakan yang wajib.
     | Upsert by editingKey supaya menyimpan dua kali tidak membuat entri kembar.
     =============================== */
    #[On('save-rm-inform-consent-rj')]
    public function addConsent(): void
    {
        $this->saveDraft();
    }

    public function saveDraft(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }
        if (!$this->diForm()) {
            return;
        }

        $this->validateOnly('newConsent.tindakan');
        $this->stempelPpa();

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        if ($this->persistEntry($key, $this->editingKey ? 'Ubah draft Inform Consent' : 'Simpan draft Inform Consent')) {
            $this->editingKey = $key; // lanjut mengedit entri yang sama, bukan membuat duplikat
            $this->dispatch('toast', type: 'success', message: 'Draft Inform Consent tersimpan. Lanjutkan tanda tangan untuk mengunci.');
        }
    }

    /** PPA (combobox): resolve kode dari nama (users.myuser_code); nama ketik-bebas tanpa match → kode kosong. */
    private function stempelPpa(): void
    {
        $namaPpa = trim($this->newConsent['petugasPemeriksa'] ?? '');
        if ($namaPpa === '') {
            $this->newConsent['petugasPemeriksaCode'] = '';
            $this->newConsent['petugasPemeriksaDate'] = '';
            return;
        }
        $kode = DB::table('users')->where('myuser_name', $namaPpa)->value('myuser_code') ?? '';
        if ($kode !== ($this->newConsent['petugasPemeriksaCode'] ?? '') || empty($this->newConsent['petugasPemeriksaDate'])) {
            $this->newConsent['petugasPemeriksaCode'] = $kode;
            $this->newConsent['petugasPemeriksaDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        }
    }

    /** Bentuk entri tersimpan — bentuk node lama dipertahankan (tanpa flag finalized; final = dokter terisi). */
    private function buildConsentEntry(string $key, array $lama = []): array
    {
        $now = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $saksiDate = $this->signatureSaksi !== '' ? (($lama['signatureSaksi'] ?? '') === $this->signatureSaksi ? ($lama['signatureSaksiDate'] ?? $now) : $now) : '';

        return [
            'tindakan' => $this->newConsent['tindakan'] ?? '',
            'diagnosa' => $this->newConsent['diagnosa'] ?? '',
            'komplikasi' => $this->newConsent['komplikasi'] ?? '',
            'tujuan' => $this->newConsent['tujuan'] ?? '',
            'resiko' => $this->newConsent['resiko'] ?? '',
            'alternatif' => $this->newConsent['alternatif'] ?? '',
            'dokter' => $this->newConsent['dokter'] ?? '',
            'dokterCode' => $this->newConsent['dokterCode'] ?? '',
            'dokterDate' => $this->newConsent['dokterDate'] ?? '',
            'signature' => $this->signature,
            'signatureDate' => $key,
            'wali' => $this->newConsent['wali'] ?? '',
            'waliHubungan' => $this->newConsent['waliHubungan'] ?? '',
            'signatureSaksi' => $this->signatureSaksi,
            'signatureSaksiDate' => $saksiDate,
            'saksi' => $this->newConsent['saksi'] ?? '',
            'agreement' => $this->newConsent['agreement'] ?? '1',
            'petugasPemeriksa' => $this->newConsent['petugasPemeriksa'] ?? '',
            'petugasPemeriksaCode' => $this->newConsent['petugasPemeriksaCode'] ?? '',
            'petugasPemeriksaDate' => $this->newConsent['petugasPemeriksaDate'] ?? '',
        ];
    }

    /** Upsert entri by signatureDate di node informConsentPasienRJ. Entri final tak boleh ditimpa. */
    private function persistEntry(string $key, string $logVerb): bool
    {
        try {
            DB::transaction(function () use ($key, $logVerb) {
                $this->lockRJRow($this->rjNo);

                $data = $this->findDataRJ($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
                }
                if (!isset($data['informConsentPasienRJ']) || !is_array($data['informConsentPasienRJ'])) {
                    $data['informConsentPasienRJ'] = [];
                }

                $idx = collect($data['informConsentPasienRJ'])->search(fn($e) => ($e['signatureDate'] ?? '') === $key);
                $lama = $idx !== false ? $data['informConsentPasienRJ'][$idx] : [];
                if ($lama !== [] && $this->entriFinal($lama)) {
                    throw new \RuntimeException('Entri sudah dikunci (TTD pemberi informasi). Buka kunci dulu untuk mengubahnya.');
                }

                $entri = $this->buildConsentEntry($key, $lama);
                if ($idx !== false) {
                    $data['informConsentPasienRJ'][$idx] = $entri;
                } else {
                    $data['informConsentPasienRJ'][] = $entri;
                }

                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
                $this->consentList = array_values($data['informConsentPasienRJ']);
                $this->appendAdminLogRJ((int) $this->rjNo, $logVerb . ' — ' . ($entri['tindakan'] ?: '-') . ', ' . $key, 'MR');
            });

            $this->incrementVersion('modal-inform-consent-rj');
            $this->dispatch('refresh-modul-dokumen-rj-data', rjNo: $this->rjNo);

            return true;
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }

        return false;
    }

    /* ===============================
     | DUA LAYAR: daftar ⇄ form
     =============================== */
    public function diForm(): bool
    {
        return !$this->isFormLocked && ($this->editingKey !== null || $this->layar === 'form');
    }

    public function tambahEntri(): void
    {
        if ($this->isFormLocked || $this->disabled) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $this->cancelEdit(); // kosongkan formulir (sekaligus balik ke daftar)…
        $this->layar = 'form'; // …lalu naikkan formulirnya
        $this->incrementVersion('modal-inform-consent-rj');
    }

    public function kembaliKeDaftar(): void
    {
        $this->cancelEdit();
        $this->incrementVersion('modal-inform-consent-rj');
    }

    /** Lanjutkan pengisian draft: muat entri ke formulir. Entri final ditolak (Lihat/Cetak saja). */
    public function editEntri(string $signatureDate): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entri = collect($this->consentList)->firstWhere('signatureDate', $signatureDate);
        if (!$entri) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }
        if ($this->entriFinal($entri)) {
            $this->dispatch('toast', type: 'warning', message: 'Entri sudah dikunci. Buka kunci dulu bila perlu dikoreksi.');
            return;
        }

        $this->cancelEdit();
        $this->hydrateFormFromEntry($entri);
        $this->editingKey = $signatureDate;
        $this->layar = 'form';
        $this->incrementVersion('modal-inform-consent-rj');
    }

    private function hydrateFormFromEntry(array $entri): void
    {
        foreach (array_keys($this->newConsent) as $k) {
            $this->newConsent[$k] = (string) ($entri[$k] ?? ($k === 'agreement' ? '1' : ''));
        }
        $this->signature = (string) ($entri['signature'] ?? '');
        $this->signatureSaksi = (string) ($entri['signatureSaksi'] ?? '');
    }

    private function cancelEdit(): void
    {
        $this->editingKey = null;
        $this->resetNewConsent(); // ikut menyetel layar = 'daftar'
        $this->signature = '';
        $this->signatureSaksi = '';
        $this->resetValidation();
    }

    /* ===============================
     | BUKA KUNCI — cabut TTD pemberi informasi saja; TTD pasien/wali & saksi dipertahankan
     =============================== */
    public function bukaKunci(string $signatureDate): void
    {
        if (!auth()->user()?->can('dokumen.bukaKunci')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang membuka kunci dokumen.');
            return;
        }
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'EMR terkunci, dokumen tidak dapat dibuka.');
            return;
        }

        try {
            DB::transaction(function () use ($signatureDate) {
                $this->lockRJRow($this->rjNo);

                $data = $this->findDataRJ($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan.');
                }
                $idx = collect($data['informConsentPasienRJ'] ?? [])->search(fn($e) => ($e['signatureDate'] ?? '') === $signatureDate);
                if ($idx === false) {
                    throw new \RuntimeException('Entri tidak ditemukan.');
                }
                if (!$this->entriFinal($data['informConsentPasienRJ'][$idx])) {
                    throw new \RuntimeException('Entri belum dikunci.');
                }

                $petugasLama = $data['informConsentPasienRJ'][$idx]['dokter'] ?? '-';
                $data['informConsentPasienRJ'][$idx]['dokter'] = '';
                $data['informConsentPasienRJ'][$idx]['dokterCode'] = '';
                $data['informConsentPasienRJ'][$idx]['dokterDate'] = '';

                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
                $this->consentList = array_values($data['informConsentPasienRJ']);
                $this->appendAdminLogRJ((int) $this->rjNo, 'Buka Kunci Inform Consent ' . $signatureDate . ' — TTD pemberi informasi ' . $petugasLama . ' dicabut oleh ' . (auth()->user()->myuser_name ?? '-'), 'MR');
            });

            $this->incrementVersion('modal-inform-consent-rj');
            $this->dispatch('toast', type: 'success', message: 'Kunci dibuka, entri kembali menjadi draft.');
            $this->dispatch('refresh-modul-dokumen-rj-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK
     =============================== */
    public function cetak(string $signatureDate): void
    {
        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor RJ tidak ditemukan.');
            return;
        }

        $consent = collect($this->consentList)->firstWhere('signatureDate', $signatureDate);
        if (!$consent) {
            $this->dispatch('toast', type: 'error', message: 'Data consent tidak ditemukan.');
            return;
        }

        $this->dispatch('cetak-inform-consent-rj.open', rjNo: $this->rjNo, signatureDate: $signatureDate);
    }

    /* ===============================
     | HAPUS
     =============================== */
    public function hapus(string $signatureDate): void
    {
        // Gate dua lapis: @can('dokumen.hapus') di tombol TIDAK cukup — wire:click
        // memanggil method publik, jadi guard server ini statement PERTAMA.
        if (!auth()->user()?->can('dokumen.hapus')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang menghapus dokumen.');
            return;
        }

        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menghapus.');
            return;
        }

        try {
            DB::transaction(function () use ($signatureDate) {
                $this->lockRJRow($this->rjNo);

                $data = $this->findDataRJ($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan.');
                }

                if (!isset($data['informConsentPasienRJ'])) {
                    throw new \RuntimeException('Data consent tidak ditemukan.');
                }

                $tindakanDihapus = collect($data['informConsentPasienRJ'])->firstWhere('signatureDate', $signatureDate)['tindakan'] ?? '-';

                $data['informConsentPasienRJ'] = collect($data['informConsentPasienRJ'])->reject(fn($item) => ($item['signatureDate'] ?? '') === $signatureDate)->values()->toArray();

                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
                $this->consentList = $data['informConsentPasienRJ'];
                $this->appendAdminLogRJ((int) $this->rjNo, 'Hapus Inform Consent — ' . $tindakanDihapus . ', TTD ' . $signatureDate, 'MR');
            });

            if ($this->editingKey === $signatureDate) {
                $this->cancelEdit();
            }
            $this->incrementVersion('modal-inform-consent-rj');
            $this->dispatch('toast', type: 'success', message: 'Inform Consent berhasil dihapus.');
            $this->dispatch('refresh-modul-dokumen-rj-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | STATUS & URUTAN ENTRI
     =============================== */

    /**
     * Entri final = tanda tangan PEMBERI INFORMASI (petugas) sudah terisi.
     * Tidak ada flag 'finalized' di JSON siklik — dan sengaja tidak ditambahkan
     * supaya bentuk node lama tetap sama.
     */
    public function entriFinal(array $entri): bool
    {
        return !empty($entri['dokter'] ?? '');
    }

    /**
     * Entri terbaru di atas. Kunci urut = tanggal yang TAMPIL di kolom.
     * JANGAN array_reverse (itu urutan simpan), jangan Carbon::parse (menebak m/d/Y),
     * jangan Carbon::createFromFormat (exception untuk satu entri berformat menyimpang).
     */
    public function daftarEntri(): array
    {
        return collect($this->consentList)
            ->sortByDesc(fn($entri) => strtotime(strtr(($entri['signatureDate'] ?? '') ?: '', '/', '-')))
            ->values()
            ->all();
    }

    /* ===============================
     | LIHAT (preview read-only = render blade cetak ke iframe)
     =============================== */
    public function lihat(string $signatureDate): void
    {
        $data = $this->buatDataCetak($signatureDate);
        if (!$data) {
            return;
        }

        $this->entriDilihat = collect($this->consentList)->firstWhere('signatureDate', $signatureDate) ?: null;
        $this->previewHtml = $this->renderDokumenPreview($this->printView, $data);
        $this->dispatch('open-modal', name: "view-inform-consent-rj-{$this->rjNo}-actions");
    }

    /** Payload cetak/preview — satu sumber supaya isi Lihat = persis hasil Cetak. */
    private function buatDataCetak(string $signatureDate): ?array
    {
        $dataRJ = $this->rjNo ? ($this->findDataRJ($this->rjNo) ?: []) : [];
        $consent = collect($dataRJ['informConsentPasienRJ'] ?? [])->firstWhere('signatureDate', $signatureDate);

        if (empty($consent)) {
            $this->dispatch('toast', type: 'error', message: 'Data Inform Consent tidak ditemukan.');
            return null;
        }

        $namaPpa = null;
        if (!empty($consent['petugasPemeriksaCode'])) {
            $namaPpa = DB::table('users')->where('myuser_code', $consent['petugasPemeriksaCode'])->value('myuser_name');
            if (empty($namaPpa)) {
                $namaPpa = DB::table('skmst_doctors')->where('dr_id', $consent['petugasPemeriksaCode'])->value('dr_name');
            }
        }

        return array_merge($this->dvPasien($dataRJ['regNo'] ?? ''), [
            'dataRJ' => $dataRJ,
            'consent' => $consent,
            'identitasRs' => $this->dvIdentitasRs(),
            // Path TTD WAJIB lewat TtdUser (dvTtdPath sudah mendelegasikan ke sana).
            'ttdDokterPath' => $this->dvTtdPath($consent['dokterCode'] ?? null),
            'ttdDokterTindakanPath' => $this->dvTtdPath($consent['petugasPemeriksaCode'] ?? null),
            'dokterTindakanName' => $namaPpa ?? ($consent['petugasPemeriksa'] ?? null),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);
    }

    /* ===============================
     | RESET
     =============================== */
    private function resetNewConsent(): void
    {
        $this->newConsent = [
            'tindakan' => '',
            'diagnosa' => '',
            'komplikasi' => '',
            'tujuan' => '',
            'resiko' => '',
            'alternatif' => '',
            'dokter' => '',
            'wali' => '',
            'waliHubungan' => '',
            'saksi' => '',
            'agreement' => '1',
            'dokterCode' => '',
            'dokterDate' => '',
            'petugasPemeriksa' => '',
            'petugasPemeriksaCode' => '',
            'petugasPemeriksaDate' => '',
        ];
        $this->layar = 'daftar'; // mengosongkan formulir = kembali ke daftar
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarPoliRJ = [];
        $this->consentList = [];
        $this->editingKey = null;
        $this->resetNewConsent();
        $this->signature = '';
        $this->signatureSaksi = '';
        $this->previewHtml = '';
        $this->entriDilihat = null;
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline) ══ --}}
    @php $icCount = count($consentList ?? []); @endphp

    <div
        class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-3">
                {{-- Baris judul: judul · badge · deskripsi (min-w-0 wajib, kalau tidak truncate tak menggigit) --}}
                <div class="flex items-baseline flex-1 gap-2 min-w-0">
                    <h3 class="text-base font-semibold truncate shrink-0 text-gray-800 dark:text-gray-200">
                        Inform Consent
                    </h3>
                    @if ($icCount > 0)
                        <x-badge variant="success" class="shrink-0 whitespace-nowrap">{{ $icCount }} tindakan</x-badge>
                    @else
                        <x-badge variant="warning" class="shrink-0 whitespace-nowrap">Belum ada</x-badge>
                    @endif

                    <x-deskripsi-ringkas>
                        Persetujuan tindakan medis per-tindakan: diagnosa, tujuan, risiko dan alternatif tindakan,
                        beserta tanda tangan pasien/wali, saksi, dan pemberi informasi. Setiap tindakan berdiri
                        sendiri sebagai satu entri.
                    </x-deskripsi-ringkas>
                </div>

                @if ($icCount > 0)
                    <ul class="space-y-1 text-base text-gray-600 dark:text-gray-300 list-disc pl-5">
                        @foreach (array_slice($this->daftarEntri(), 0, 3) as $ic)
                            <li>
                                <span
                                    class="font-medium">{{ \Illuminate\Support\Str::limit($ic['tindakan'] ?? '-', 60) }}</span>
                                @if (!empty($ic['signatureDate']))
                                    <span class="text-sm text-gray-400">— {{ $ic['signatureDate'] }}</span>
                                @endif
                            </li>
                        @endforeach
                        @if ($icCount > 3)
                            <li class="text-sm italic text-gray-400">
                                +{{ $icCount - 3 }} lainnya…
                            </li>
                        @endif
                    </ul>
                @endif
            </div>

            <div class="flex shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                    wire:target="openModal" :disabled="$disabled || !$rjNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        Buka Inform Consent
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                        <x-loading class="w-4 h-4" /> Memuat...
                    </span>
                </x-primary-button>
            </div>
        </div>
    </div>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="rm-inform-consent-rj-{{ $rjNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-inform-consent-rj', [$rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <svg class="w-6 h-6 text-brand-green dark:text-brand-lime" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                            </div>

                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    Inform Consent
                                </h2>
                                <p class="mt-0.5 text-base text-gray-500 dark:text-gray-400">
                                    Persetujuan tindakan medis — tampilan ini dapat diputar ke arah pasien
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="success">Rawat Jalan</x-badge>
                            @if (count($consentList) > 0)
                                <x-badge variant="info">{{ count($consentList) }} tersimpan</x-badge>
                            @endif
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    {{-- Display Pasien --}}
                    <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                        wire:key="ic-rj-display-pasien-{{ $rjNo ?? 'init' }}" />

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

                        {{-- Dua layar: formulir hanya dirender di layar 'form' (diForm()), daftar entri di
                             layar 'daftar'. Guard dipasang TEPAT di sini, bukan di header modal, supaya
                             badge + display pasien tetap tampil di layar daftar. --}}
                        @if ($this->diForm())
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($editingKey)
                                <x-badge variant="warning">Melanjutkan draft {{ $editingKey }}</x-badge>
                            @else
                                <x-badge variant="info">Entri baru</x-badge>
                            @endif
                            <span class="text-sm text-muted">Simpan Draft kapan saja; TTD pemberi informasi = validasi penuh + kunci.</span>
                        </div>

                        {{-- ══ INFORMASI TINDAKAN ══ --}}
                        <section class="space-y-4">
                            <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                                Informasi Tindakan
                            </h3>

                            <div>
                                <x-input-label value="PPA — Profesional Pemberi Asuhan *" class="mb-1" />
                                @if (!$isFormLocked)
                                    <div class="flex items-start gap-2"
                                        wire:key="ppa-ic-rj-{{ $rjNo ?? 'init' }}-{{ $renderVersions['modal-inform-consent-rj'] ?? 0 }}">
                                        <div class="flex-1">
                                            <x-ppa-combobox wireModel="newConsent.petugasPemeriksa"
                                                :disabled="$isFormLocked"
                                                inputId="ppa-ic-rj-{{ $rjNo ?? 'init' }}" />
                                        </div>
                                        <x-secondary-button type="button" wire:click="isiPpaSebagaiSaya"
                                            class="!py-2 shrink-0" title="Isi PPA sebagai akun saya (login)">
                                            Saya
                                        </x-secondary-button>
                                    </div>
                                    @if (!empty($newConsent['petugasPemeriksa']))
                                        <p class="mt-1 text-sm text-gray-500">
                                            Dipilih: {{ $newConsent['petugasPemeriksa'] }}@if (!empty($newConsent['petugasPemeriksaCode'])) (ID: {{ $newConsent['petugasPemeriksaCode'] }})@endif
                                        </p>
                                    @endif
                                @elseif (!empty($newConsent['petugasPemeriksa']))
                                    <div
                                        class="p-3 border border-gray-200 bg-gray-50 rounded-xl dark:bg-gray-800 dark:border-gray-700">
                                        <div class="font-semibold text-gray-800 dark:text-gray-200">
                                            {{ $newConsent['petugasPemeriksa'] }}
                                        </div>
                                        @if (!empty($newConsent['petugasPemeriksaCode']))
                                            <div class="text-sm text-gray-500 mt-0.5">
                                                ID: {{ $newConsent['petugasPemeriksaCode'] }}
                                            </div>
                                        @endif
                                        <div class="mt-1 text-sm text-gray-500">
                                            {{ $newConsent['petugasPemeriksaDate'] ?? '-' }}
                                        </div>
                                    </div>
                                @else
                                    <p class="text-base italic text-gray-400">Belum dipilih.</p>
                                @endif
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Diagnosa" class="mb-1" />
                                    <x-text-input wire:model.live="newConsent.diagnosa"
                                        placeholder="Diagnosa kerja / penyakit..." :disabled="$isFormLocked"
                                        class="w-full" />
                                    <x-input-error :messages="$errors->get('newConsent.diagnosa')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Komplikasi" class="mb-1" />
                                    <x-text-input wire:model.live="newConsent.komplikasi"
                                        placeholder="Kemungkinan komplikasi..." :disabled="$isFormLocked"
                                        class="w-full" />
                                    <x-input-error :messages="$errors->get('newConsent.komplikasi')" class="mt-1" />
                                </div>
                            </div>

                            <div>
                                <x-input-label value="Nama Tindakan / Prosedur *" class="mb-1" />
                                <x-text-input wire:model.live="newConsent.tindakan"
                                    placeholder="Contoh: Injeksi IM, Hecting Ringan, Nebulizer..."
                                    :disabled="$isFormLocked" class="w-full" />
                                <x-input-error :messages="$errors->get('newConsent.tindakan')" class="mt-1" />
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <x-input-label value="Tujuan Tindakan / Terapi" class="mb-1" />
                                    <x-textarea wire:model.live="newConsent.tujuan" rows="3"
                                        placeholder="Uraian singkat mengenai tujuan tindakan..."
                                        :disabled="$isFormLocked" />
                                </div>

                                <div>
                                    <x-input-label value="Risiko Tindakan / Terapi" class="mb-1" />
                                    <x-textarea wire:model.live="newConsent.resiko" rows="3"
                                        placeholder="Kemungkinan risiko / efek samping..."
                                        :disabled="$isFormLocked" />
                                </div>

                                <div>
                                    <x-input-label value="Alternatif Tindakan / Terapi" class="mb-1" />
                                    <x-textarea wire:model.live="newConsent.alternatif" rows="3"
                                        placeholder="Alternatif lain yang dapat dilakukan..."
                                        :disabled="$isFormLocked" />
                                </div>
                            </div>

                            <div class="md:max-w-xs">
                                <x-input-label value="Persetujuan *" class="mb-1" />
                                <x-select-input wire:model.live="newConsent.agreement" :disabled="$isFormLocked"
                                    class="w-full">
                                    @foreach ($agreementOptions as $opt)
                                        <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('newConsent.agreement')" class="mt-1" />
                            </div>

                            @if (($newConsent['agreement'] ?? '1') === '1')
                                <div
                                    class="flex items-start gap-3 px-4 py-3 text-base border rounded-xl bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-900/20 dark:border-emerald-700 dark:text-emerald-200">
                                    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <div>
                                        <p class="font-semibold">Pasien MENYETUJUI tindakan</p>
                                        <p class="mt-0.5">
                                            Setelah ditandatangani, dokumen dicetak sebagai
                                            <strong>Persetujuan Tindakan Medis (Inform Consent)</strong> dan tindakan
                                            dapat dilakukan.
                                        </p>
                                    </div>
                                </div>
                            @else
                                <div
                                    class="flex items-start gap-3 px-4 py-3 text-base border rounded-xl bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-900/20 dark:border-rose-700 dark:text-rose-200">
                                    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <div>
                                        <p class="font-semibold">Pasien MENOLAK tindakan</p>
                                        <p class="mt-0.5">
                                            Dokumen akan tercatat sebagai
                                            <strong>Penolakan Tindakan Medis</strong>. Pasien/wali memahami risiko medis
                                            atas penolakan tersebut dan bersedia menandatangani sebagai bukti penolakan.
                                            Tindakan tidak akan dilakukan.
                                        </p>
                                    </div>
                                </div>
                            @endif
                        </section>

                        {{-- ══ TANDA TANGAN ══ --}}
                        <section class="pt-6 space-y-4 border-t border-gray-200 dark:border-gray-700">
                            <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                                Tanda Tangan
                            </h3>

                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                {{-- Pasien / Wali --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-sm font-semibold tracking-wide text-center text-gray-500 uppercase dark:text-gray-400">
                                        Pasien / Wali
                                    </div>
                                    <x-input-error :messages="$errors->get('signature')" class="mb-2" />
                                    @if (!empty($signature))
                                        <x-signature.signature-result :signature="$signature" :date="''"
                                            :disabled="$isFormLocked" wireMethod="clearSignature" />
                                    @elseif (!$isFormLocked)
                                        <x-signature.signature-pad wireMethod="setSignature" />
                                    @else
                                        <p class="py-8 text-base italic text-center text-gray-400">Belum
                                            ditandatangani.</p>
                                    @endif

                                    <div class="mt-3">
                                        <x-input-label value="Nama Pasien / Wali *" class="mb-1" />
                                        <x-text-input wire:model.live="newConsent.wali"
                                            placeholder="Nama lengkap pasien atau wali..." :disabled="$isFormLocked"
                                            class="w-full" />
                                        <x-input-error :messages="$errors->get('newConsent.wali')" class="mt-1" />
                                    </div>

                                    <div class="mt-2">
                                        <x-input-label value="Hubungan dengan Pasien *" class="mb-1" />
                                        <x-select-input wire:model.live="newConsent.waliHubungan"
                                            :disabled="$isFormLocked" class="w-full">
                                            <option value="">— Pilih hubungan —</option>
                                            @foreach ($waliHubunganOptions as $opt)
                                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                            @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('newConsent.waliHubungan')"
                                            class="mt-1" />
                                    </div>
                                </div>

                                {{-- Saksi --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-sm font-semibold tracking-wide text-center text-gray-500 uppercase dark:text-gray-400">
                                        Saksi
                                    </div>
                                    <x-input-error :messages="$errors->get('signatureSaksi')" class="mb-2" />
                                    @if (!empty($signatureSaksi))
                                        <x-signature.signature-result :signature="$signatureSaksi" :date="''"
                                            :disabled="$isFormLocked" wireMethod="clearSignatureSaksi" />
                                    @elseif (!$isFormLocked)
                                        <x-signature.signature-pad wireMethod="setSignatureSaksi" />
                                    @else
                                        <p class="py-8 text-base italic text-center text-gray-400">Belum
                                            ditandatangani.</p>
                                    @endif

                                    <div class="mt-3">
                                        <x-input-label value="Nama Saksi *" class="mb-1" />
                                        <x-text-input wire:model.live="newConsent.saksi" placeholder="Nama saksi..."
                                            :disabled="$isFormLocked" class="w-full" />
                                        <x-input-error :messages="$errors->get('newConsent.saksi')" class="mt-1" />
                                    </div>
                                </div>

                                {{-- Pemberi Informasi — stempel baku x-signature.ttd-petugas.
                                     Kartu stempel bespoke (nama/Kode/tanggal rata tengah) DILARANG:
                                     komponen ini menampilkan gambar TTD user (myuser_ttd_image) di kotak
                                     putih selebar kolom, sejajar kolom TTD pasien & saksi. --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-sm font-semibold tracking-wide text-center text-gray-500 uppercase dark:text-gray-400">
                                        Pemberi Informasi
                                    </div>
                                    <x-input-error :messages="$errors->get('newConsent.dokter')" class="mb-2" />

                                    <x-signature.ttd-petugas :framed="false" :locked="$isFormLocked"
                                        :allowClear="false" :ttd="$newConsent['dokter'] ?? ''"
                                        :code="$newConsent['dokterCode'] ?? ''" :date="$newConsent['dokterDate'] ?? ''"
                                        sign="setDokterPenjelas" nameLabel="Pemberi Informasi" dateLabel="Waktu TTD"
                                        signLabel="TTD Pemberi Informasi & Kunci" />
                                </div>

                            </div>
                        </section>
                        @endif

                        {{-- ══ DAFTAR ENTRI TERSIMPAN ══
                             Bentuk baku tabel daftar modul dokumen: tanpa kolom No, kolom
                             pertama panah rincian, entri TERBARU DI ATAS, sel Aksi satu baris
                             rata kanan dengan kelompok berisiko dipisah garis. --}}
                        @unless ($this->diForm())
                        <section class="space-y-3">
                            <div class="overflow-x-auto rounded-2xl">
                                <table class="ds-table ds-table-entri min-w-full">
                                    <thead class="sticky top-0 z-10">
                                        <tr>
                                            <th class="w-8"><span class="sr-only">Rincian</span></th>
                                            <th class="whitespace-nowrap">Tanggal</th>
                                            <th class="whitespace-nowrap">Tindakan</th>
                                            <th class="whitespace-nowrap">Persetujuan</th>
                                            <th class="whitespace-nowrap">Petugas (TTD)</th>
                                            <th class="whitespace-nowrap">Status</th>
                                            <th class="whitespace-nowrap ds-c">Aksi</th>
                                        </tr>
                                    </thead>

                                    @forelse ($this->daftarEntri() as $entri)
                                        @php
                                            $entriTgl = $entri['signatureDate'] ?? '';
                                            $entriIsFinal = $this->entriFinal($entri);
                                            $bolehHapus = auth()->user()?->can('dokumen.hapus');
                                            $bolehBukaKunci = auth()->user()?->can('dokumen.bukaKunci');
                                        @endphp

                                        {{-- Satu <tbody> per entri: baris ringkas + baris rincian yang
                                             mulai TERTUTUP (x-data di tbody, bukan di tr). --}}
                                        <tbody wire:key="ic-entri-{{ $loop->index }}-{{ $entriTgl }}"
                                            x-data="{ open: false }">
                                            <tr class="cursor-pointer" @click="open = !open">
                                                <td class="ds-c">
                                                    <svg class="w-4 h-4 transition-transform text-muted"
                                                        :class="{ 'rotate-90': open }" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </td>
                                                <td class="ds-td-token">{{ $entriTgl ?: '-' }}</td>
                                                <td class="ds-td-strong">{{ Str::limit($entri['tindakan'] ?? '-', 50) }}</td>
                                                <td>
                                                    @if (($entri['agreement'] ?? '1') === '1')
                                                        <x-badge variant="success">Menyetujui</x-badge>
                                                    @else
                                                        <x-badge variant="danger">Menolak</x-badge>
                                                    @endif
                                                </td>
                                                <td>
                                                    {{-- Nama petugas hanya tampil bila entri final (aturan TTD #12d) --}}
                                                    @if ($entriIsFinal)
                                                        {{ $entri['dokter'] }}
                                                    @else
                                                        <x-badge variant="danger">Belum TTD</x-badge>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($entriIsFinal)
                                                        <x-badge variant="info">Terkunci</x-badge>
                                                    @else
                                                        <x-badge variant="warning">Draft</x-badge>
                                                    @endif
                                                </td>
                                                <td class="whitespace-nowrap" @click.stop>
                                                    <div class="flex items-center justify-end gap-2">
                                                        @if (!$isFormLocked && !$entriIsFinal)
                                                            <x-primary-button type="button" wire:click="editEntri('{{ $entriTgl }}')"
                                                                wire:loading.attr="disabled" title="Lanjutkan mengisi draft ini">
                                                                Lanjutkan Pengisian
                                                            </x-primary-button>
                                                        @endif
                                                        <x-lihat-button wire:click="lihat('{{ $entriTgl }}')" />
                                                        <x-cetak-button wire:click="cetak('{{ $entriTgl }}')" />

                                                        {{-- Kelompok berisiko: dipisah garis, hanya dirender bila
                                                             user berhak (supaya tak menyisakan garis kosong). --}}
                                                        @if (!$isFormLocked && ($bolehHapus || ($entriIsFinal && $bolehBukaKunci)))
                                                            <div
                                                                class="flex items-center gap-2 pl-3 ml-1 border-l border-hairline dark:border-gray-700">
                                                                @if ($entriIsFinal)
                                                                    @can('dokumen.bukaKunci')
                                                                        <x-confirm-button variant="warning-soft" action="bukaKunci('{{ $entriTgl }}')"
                                                                            title="Buka Kunci Inform Consent"
                                                                            message="TTD pemberi informasi akan dicabut & entri kembali menjadi draft untuk dikoreksi. TTD pasien/wali dan saksi tetap dipertahankan. Lanjutkan?"
                                                                            confirmText="Ya, buka kunci" cancelText="Batal">
                                                                            Buka Kunci
                                                                        </x-confirm-button>
                                                                    @endcan
                                                                @endif
                                                                @can('dokumen.hapus')
                                                                    <x-hapus-button :action="'hapus(\'' . $entriTgl . '\')'"
                                                                        title="Hapus Inform Consent"
                                                                        message="Entri Inform Consent ini akan dihapus beserta tanda tangannya. Lanjutkan?" />
                                                                @endcan
                                                            </div>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>

                                            {{-- Baris rincian — ringkasan isian yang tidak muat di kolom --}}
                                            <tr x-show="open" x-cloak>
                                                <td colspan="7">
                                                    <dl class="grid grid-cols-1 gap-x-6 gap-y-2 md:grid-cols-2">
                                                        <div>
                                                            <dt class="ds-caption-up">Diagnosa</dt>
                                                            <dd class="text-muted">{{ $entri['diagnosa'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="ds-caption-up">Komplikasi</dt>
                                                            <dd class="text-muted">{{ $entri['komplikasi'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="ds-caption-up">Tujuan Tindakan</dt>
                                                            <dd class="text-muted">{{ $entri['tujuan'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="ds-caption-up">Risiko Tindakan</dt>
                                                            <dd class="text-muted">{{ $entri['resiko'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="ds-caption-up">Alternatif Tindakan</dt>
                                                            <dd class="text-muted">{{ $entri['alternatif'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="ds-caption-up">PPA</dt>
                                                            <dd class="text-muted">{{ $entri['petugasPemeriksa'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="ds-caption-up">Pasien / Wali</dt>
                                                            <dd class="text-muted">
                                                                {{ $entri['wali'] ?: '-' }}
                                                                @if (!empty($entri['waliHubungan']))
                                                                    ({{ $entri['waliHubungan'] }})
                                                                @endif
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt class="ds-caption-up">Saksi</dt>
                                                            <dd class="text-muted">{{ $entri['saksi'] ?: '-' }}</dd>
                                                        </div>
                                                    </dl>
                                                </td>
                                            </tr>
                                        </tbody>
                                    @empty
                                        <tbody>
                                            <tr>
                                                <td colspan="7" class="ds-c text-muted">Belum ada data tersimpan</td>
                                            </tr>
                                        </tbody>
                                    @endforelse
                                </table>
                            </div>

                            <p class="text-sm text-muted">
                                Setiap entri berdiri sendiri — satu baris untuk satu tindakan. <strong>Isi Formulir
                                Baru</strong> untuk entri baru, <strong>Lanjutkan Pengisian</strong> untuk melanjutkan draft.
                            </p>
                        </section>
                        @endunless

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-end gap-3">
                    @if ($this->diForm())
                        <x-secondary-button type="button" wire:click="kembaliKeDaftar">
                            Kembali ke Daftar
                        </x-secondary-button>
                        <x-primary-button wire:click.prevent="saveDraft" wire:loading.attr="disabled"
                            wire:target="saveDraft" class="gap-2 min-w-[180px] justify-center">
                            <span wire:loading.remove wire:target="saveDraft">{{ $editingKey ? 'Simpan Perubahan' : 'Simpan Draft' }}</span>
                            <span wire:loading wire:target="saveDraft"><x-loading class="w-4 h-4" />
                                Menyimpan...</span>
                        </x-primary-button>
                    @else
                        <x-secondary-button wire:click="closeModal">
                            Tutup
                        </x-secondary-button>
                        @if ($rjNo && !$isFormLocked)
                            <x-primary-button type="button" wire:click="tambahEntri" wire:loading.attr="disabled"
                                wire:target="tambahEntri" class="gap-2 min-w-[180px] justify-center">
                                Isi Formulir Baru
                            </x-primary-button>
                        @endif
                    @endif
                </div>
            </div>

        </div>
    </x-modal>

    {{-- Layar LIHAT — preview read-only; isinya render blade cetak (docs/dokumen-view-pattern.md) --}}
    <x-rm.dokumen-view-modal name="view-inform-consent-rj-{{ $rjNo }}-actions"
        :title="'Inform Consent' . ($entriDilihat && filled(data_get($entriDilihat, 'tindakan')) ? ' — ' . data_get($entriDilihat, 'tindakan') : '')"
        :subtitle="data_get($entriDilihat, 'signatureDate')" :cetakId="data_get($entriDilihat, 'signatureDate')"
        :previewHtml="$previewHtml" />

    {{-- Cetak component --}}
    <livewire:pages::components.modul-dokumen.rj.inform-consent.cetak-inform-consent-rj
        wire:key="cetak-inform-consent-rj-{{ $rjNo ?? 'init' }}" />
</div>
