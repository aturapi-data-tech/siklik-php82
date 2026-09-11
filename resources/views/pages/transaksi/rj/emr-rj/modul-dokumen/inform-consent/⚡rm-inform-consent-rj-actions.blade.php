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
    @include('pages.transaksi.rj.emr-rj.modul-dokumen.inform-consent.partials.kartu-ringkas')

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="rm-inform-consent-rj-{{ $rjNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-inform-consent-rj', [$rjNo ?? 'new']) }}">

            @include('pages.transaksi.rj.emr-rj.modul-dokumen.inform-consent.partials.modal-header')

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

                        @include('pages.transaksi.rj.emr-rj.modul-dokumen.inform-consent.partials.form-entri')

                        @include('pages.transaksi.rj.emr-rj.modul-dokumen.inform-consent.partials.tabel-entri')

                    </div>
                </div>
            </div>

            @include('pages.transaksi.rj.emr-rj.modul-dokumen.inform-consent.partials.modal-footer')

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
