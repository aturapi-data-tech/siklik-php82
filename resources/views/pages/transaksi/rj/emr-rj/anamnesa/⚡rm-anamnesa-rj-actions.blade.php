<?php

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\BPJS\PcareTrait;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait, PcareTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];

    // renderVersions
    public array $renderVersions = [];
    protected array $renderAreas = ['modal-anamnesa-rj'];

    /* -------------------------
     | LOV alergi BPJS PCare (jenis: 01=Makanan, 02=Udara, 03=Obat)
     * ------------------------- */
    public array $alergiMakananOptions = [];
    public array $alergiUdaraOptions   = [];
    public array $alergiObatOptions    = [];

    /* -------------------------
     | Rekonsiliasi Obat — input row buat add (cleared setelah submit)
     * ------------------------- */
    public array $rekonsiliasiObatInput = ['namaObat' => '', 'dosis' => '', 'rute' => ''];

    /* ===============================
     | OPEN REKAM MEDIS PERAWAT - ANAMNESA
     =============================== */
    #[On('open-rm-anamnesa-rj')]
    public function openAnamnesa($rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->$rjNo = $rjNo;

        $this->resetForm();
        $this->resetValidation();
        // Ambil data kunjungan RJ
        $dataDaftarPoliRJ = $this->findDataRJ($rjNo);

        if (!$dataDaftarPoliRJ) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $dataDaftarPoliRJ;

        // Initialize anamnesa data if not exists
        if (!isset($this->dataDaftarPoliRJ['anamnesa'])) {
            $this->dataDaftarPoliRJ['anamnesa'] = $this->getDefaultAnamnesa();
        }

        // ✅ Ambil data pasien dari master pasien (untuk alergi & riwayat penyakit)
        $pasienData = $this->findDataMasterPasien($dataDaftarPoliRJ['regNo']);

        // ✅ Isi alergi jika ada di data pasien
        if (isset($pasienData['pasien']['alergi'])) {
            // Masukkan ke struktur anamnesa
            $this->dataDaftarPoliRJ['anamnesa']['alergi']['alergi'] = $pasienData['pasien']['alergi'];
        }

        // ✅ Isi riwayat penyakit dahulu jika ada
        if (isset($pasienData['pasien']['riwayatPenyakitDahulu'])) {
            $this->dataDaftarPoliRJ['anamnesa']['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] = $pasienData['pasien']['riwayatPenyakitDahulu'];
        }

        // Pre-load 3 alergi options dari cache ref_bpjs_table (silent).
        $this->preloadAlergiOptions();

        // 🔥 INCREMENT: Refresh seluruh modal anamnesa
        $this->incrementVersion('modal-anamnesa-rj');

        // Cek status lock
        if ($this->checkEmrRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }
    }

    /* ===============================
     | GET DEFAULT ANAMNESA STRUCTURE
     =============================== */
    private function getDefaultAnamnesa(): array
    {
        return [
            'pengkajianPerawatanTab' => 'Pengkajian',
            'pengkajianPerawatan' => [
                'perawatPenerima' => '',
                'jamDatang' => '',
            ],

            'keluhanUtamaTab' => 'Keluhan Utama',
            'keluhanUtama' => [
                'keluhanUtama' => '',
                'snomedCode' => '',
                'snomedDisplayEn' => '',
                'snomedDisplayId' => '',
            ],

            'anamnesaDiperolehTab' => 'Anamnesa Diperoleh',
            'anamnesaDiperoleh' => [
                'autoanamnesa' => [],
                'allonanamnesa' => [],
                'anamnesaDiperolehDari' => '',
            ],

            'riwayatPenyakitSekarangUmumTab' => 'Riwayat Penyakit Sekarang (Umum)',
            'riwayatPenyakitSekarangUmum' => [
                'riwayatPenyakitSekarangUmum' => '',
            ],

            'riwayatPenyakitDahuluTab' => 'Riwayat Penyakit (Dahulu)',
            'riwayatPenyakitDahulu' => [
                'riwayatPenyakitDahulu' => '',
            ],

            'rekonsiliasiObatTab' => 'Rekonsiliasi Obat',
            'rekonsiliasiObat'    => [],

            'alergiTab' => 'Alergi',
            'alergi' => [
                'alergi' => '',
                'snomedCode' => '',
                'snomedDisplayEn' => '',
                'snomedDisplayId' => '',
                // BPJS PCare alergi (jenis 01/02/03) — default 00 = Tidak Ada
                'alergiMakan'      => '00',
                'alergiMakanDesc'  => 'Tidak Ada',
                'alergiUdara'      => '00',
                'alergiUdaraDesc'  => 'Tidak Ada',
                'alergiObat'       => '00',
                'alergiObatDesc'   => 'Tidak Ada',
            ],

            // 'rekonsiliasiObatTab' => 'Rekonsiliasi Obat',
            // 'rekonsiliasiObat' => [],

            // 'lainLainTab' => 'lain-Lain',
            // 'lainLain' => [
            //     'merokok' => [],
            //     'terpaparRokok' => [],
            // ],

            // 'faktorResikoTab' => 'Faktor Resiko',
            // 'faktorResiko' => [
            //     'hipertensi' => [],
            //     'diabetesMelitus' => [],
            //     'penyakitJantung' => [],
            //     'asma' => [],
            //     'stroke' => [],
            //     'liver' => [],
            //     'tuberculosisParu' => [],
            //     'rokok' => [],
            //     'minumAlkohol' => [],
            //     'ginjal' => [],
            //     'lainLain' => '',
            // ],

            // 'penyakitKeluargaTab' => 'Riwayat Penyakit Keluarga',
            // 'penyakitKeluarga' => [
            //     'hipertensi' => [],
            //     'diabetesMelitus' => [],
            //     'penyakitJantung' => [],
            //     'asma' => [],
            //     'lainLain' => '',
            // ],

            // 'statusFungsionalTab' => 'Status Fungsional',
            // 'statusFungsional' => [
            //     'tongkat' => [],
            //     'kursiRoda' => [],
            //     'brankard' => [],
            //     'walker' => [],
            //     'lainLain' => '',
            // ],

            // 'cacatTubuhTab' => 'Cacat Tubuh',
            // 'cacatTubuh' => [
            //     'cacatTubuh' => [],
            //     'sebutCacatTubuh' => '',
            // ],

            'statusPsikologisTab' => 'Status Psikologis',
            'statusPsikologis' => [
                'tidakAdaKelainan' => [],
                'marah' => [],
                'ccemas' => [],
                'takut' => [],
                'sedih' => [],
                'cenderungBunuhDiri' => [],
                'sebutstatusPsikologis' => '',
            ],

            'statusMentalTab' => 'Status Mental',
            'statusMental' => [
                'statusMental' => '',
                'statusMentalOption' => [['statusMental' => 'Sadar dan Orientasi Baik'], ['statusMental' => 'Ada Masalah Perilaku'], ['statusMental' => 'Perilaku Kekerasan yang dialami sebelumnya']],
                'keteranganStatusMental' => '',
            ],

            // 'hubunganDgnKeluargaTab' => 'Sosial',
            // 'hubunganDgnKeluarga' => [
            //     'hubunganDgnKeluarga' => '',
            //     'hubunganDgnKeluargaOption' => [['hubunganDgnKeluarga' => 'Baik'], ['hubunganDgnKeluarga' => 'Tidak Baik']],
            // ],

            // 'tempatTinggalTab' => 'Tempat Tinggal',
            // 'tempatTinggal' => [
            //     'tempatTinggal' => '',
            //     'tempatTinggalOption' => [['tempatTinggal' => 'Rumah'], ['tempatTinggal' => 'Panti'], ['tempatTinggal' => 'Lain-lain']],
            //     'keteranganTempatTinggal' => '',
            // ],

            // 'spiritualTab' => 'Spiritual',
            // 'spiritual' => [
            //     'spiritual' => 'Islam',
            //     'ibadahTeratur' => '',
            //     'ibadahTeraturOptions' => [['ibadahTeratur' => 'Ya'], ['ibadahTeratur' => 'Tidak']],
            //     'nilaiKepercayaan' => '',
            //     'nilaiKepercayaanOptions' => [['nilaiKepercayaan' => 'Ya'], ['nilaiKepercayaan' => 'Tidak']],
            //     'keteranganSpiritual' => '',
            // ],

            // 'ekonomiTab' => 'Ekonomi',
            // 'ekonomi' => [
            //     'pengambilKeputusan' => 'Ayah',
            //     'pekerjaan' => 'Swasta',
            //     'penghasilanBln' => '',
            //     'penghasilanBlnOptions' => [['penghasilanBln' => '< 5Jt'], ['penghasilanBln' => '5Jt - 10Jt'], ['penghasilanBln' => '>10Jt']],
            //     'keteranganEkonomi' => '',
            // ],

            // 'edukasiTab' => 'Edukasi',
            // 'edukasi' => [
            //     'pasienKeluargaMenerimaInformasi' => '',
            //     'pasienKeluargaMenerimaInformasiOptions' => [['pasienKeluargaMenerimaInformasi' => 'Ya'], ['pasienKeluargaMenerimaInformasi' => 'Tidak']],
            //     'hambatanEdukasi' => '',
            //     'keteranganHambatanEdukasi' => '',
            //     'hambatanEdukasiOptions' => [['hambatanEdukasi' => 'Ya'], ['hambatanEdukasi' => 'Tidak']],
            //     'penerjemah' => '',
            //     'keteranganPenerjemah' => '',
            //     'penerjemahOptions' => [['penerjemah' => 'Ya'], ['penerjemah' => 'Tidak']],
            //     'diagPenyakit' => [],
            //     'obat' => [],
            //     'dietNutrisi' => [],
            //     'rehabMedik' => [],
            //     'managemenNyeri' => [],
            //     'penggunaanAlatMedis' => [],
            //     'hakKewajibanPasien' => [],
            //     'edukasiFollowUp' => '',
            //     'segeraKembaliRjjika' => '',
            //     'informedConsent' => '',
            //     'keteranganEdukasi' => '',
            // ],

            // 'screeningGiziTab' => 'Screening Gizi',
            // 'screeningGizi' => [
            //     'perubahanBB3Bln' => '',
            //     'perubahanBB3BlnScore' => '0',
            //     'perubahanBB3BlnOptions' => [['perubahanBB3Bln' => 'Ya (1)'], ['perubahanBB3Bln' => 'Tidak (0)']],
            //     'jmlPerubahabBB' => '',
            //     'jmlPerubahabBBScore' => '0',
            //     'jmlPerubahabBBOptions' => [['jmlPerubahabBB' => '0,5Kg-1Kg (1)'], ['jmlPerubahabBB' => '>5Kg-10Kg (2)'], ['jmlPerubahabBB' => '>10Kg-15Kg (3)'], ['jmlPerubahabBB' => '>15Kg-20Kg (4)']],
            //     'intakeMakanan' => '',
            //     'intakeMakananScore' => '0',
            //     'intakeMakananOptions' => [['intakeMakanan' => 'Ya (1)'], ['intakeMakanan' => 'Tidak (0)']],
            //     'keteranganScreeningGizi' => '',
            //     'scoreTotalScreeningGizi' => '0',
            //     'tglScreeningGizi' => '',
            // ],

            'batukTab' => 'Screening Batuk',
            'batuk' => [
                'riwayatDemam' => [],
                'keteranganRiwayatDemam' => '',
                'berkeringatMlmHari' => [],
                'keteranganBerkeringatMlmHari' => '',
                'bepergianDaerahWabah' => [],
                'keteranganBepergianDaerahWabah' => '',
                'riwayatPakaiObatJangkaPanjangan' => [],
                'keteranganRiwayatPakaiObatJangkaPanjangan' => '',
                'BBTurunTanpaSebab' => [],
                'keteranganBBTurunTanpaSebab' => '',
                'pembesaranGetahBening' => [],
                'keteranganPembesaranGetahBening' => '',
            ],
        ];
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-anamnesa-actions');
    }

    protected function rules(): array
    {
        $rules['dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.jamDatang'] = 'date_format:d/m/Y H:i:s';
        return $rules;
    }

    protected function messages(): array
    {
        return [
            'dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.jamDatang.date_format' => ':attribute harus dalam format dd/mm/yyyy hh:mi:ss',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.jamDatang' => 'Waktu Datang',
        ];
    }

    /* ===============================
 | SAVE ANAMNESA
 =============================== */
    #[On('save-rm-anamnesa-rj')]
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

                // 5. Ambil data terkini dari DB (setelah lock)
                $data = $this->findDataRJ($this->rjNo) ?? [];

                // 6. Guard: data DB kosong — jangan overwrite JSON dengan array kosong
                if (empty($data)) {
                    $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan, simpan dibatalkan.');
                    return;
                }

                // Tangkap status baru/lama sebelum overwrite (key anamnesa belum ada saat pertama disimpan)
                $isBaru = empty($data['anamnesa']);

                // 7. Set hanya key 'anamnesa' — key lain tidak tersentuh
                $data['anamnesa'] = $this->dataDaftarPoliRJ['anamnesa'] ?? [];

                // 8. Persist + sync properti lokal
                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;

                // 9. Side effect: sync alergi & riwayat penyakit ke master pasien
                $this->updateRiwayatMedisPasien();

                // 10. Audit log (kategori Rekam Medis)
                $this->appendAdminLogRJ((int) $this->rjNo, ($isBaru ? 'Buat' : 'Update') . ' Anamnesa — jam datang ' . ($this->dataDaftarPoliRJ['anamnesa']['pengkajianPerawatan']['jamDatang'] ?? '-'), 'MR');
            });

            $this->afterSave('Anamnesa berhasil disimpan.');
        } catch (\RuntimeException $e) {
            // lockRJRow() throws RuntimeException jika row tidak ditemukan
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    private function updateRiwayatMedisPasien(): void
    {
        $regNo = $this->dataDaftarPoliRJ['regNo'];

        // Ambil data pasien
        $pasienData = $this->findDataMasterPasien($regNo);

        $updated = false;

        // ✅ Update Alergi (text)
        if (!empty(($alergi = $this->dataDaftarPoliRJ['anamnesa']['alergi']['alergi'] ?? ''))) {
            $pasienData['pasien']['alergi'] = $alergi;
            $updated = true;
        }

        // ✅ Update Riwayat Penyakit Dahulu (text)
        if (!empty(($riwayat = $this->dataDaftarPoliRJ['anamnesa']['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] ?? ''))) {
            $pasienData['pasien']['riwayatPenyakitDahulu'] = $riwayat;
            $updated = true;
        }

        // ✅ Update jika ada perubahan
        if ($updated) {
            $pasienData['pasien']['regNo'] = $regNo;
            $this->updateJsonMasterPasien($regNo, $pasienData);
        }
    }
    /* ===============================
     | SET PERAWAT PENERIMA
     =============================== */
    public function setPerawatPenerima(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        if (auth()->user()->hasRole('Perawat')) {
            $this->dataDaftarPoliRJ['anamnesa']['pengkajianPerawatan']['perawatPenerima'] = auth()->user()->myuser_name;
            $this->dataDaftarPoliRJ['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'] = auth()->user()->myuser_code;

            // ✅ Auto-isi jam datang saat TTD perawat (hanya jika belum diisi)
            if (empty($this->dataDaftarPoliRJ['anamnesa']['pengkajianPerawatan']['jamDatang'])) {
                $this->dataDaftarPoliRJ['anamnesa']['pengkajianPerawatan']['jamDatang'] = now()->format('d/m/Y H:i:s');
            }

            // 🔥 INCREMENT: Refresh untuk menampilkan perawat yang sudah di-set
            $this->incrementVersion('modal-anamnesa-rj');
        } else {
            $this->dispatch('toast', type: 'error', message: 'Hanya user dengan role Perawat yang dapat melakukan TTD-E.');
        }
    }

    private function afterSave(string $message): void
    {
        // 🔥 INCREMENT: Refresh seluruh modal anamnesa
        $this->incrementVersion('modal-anamnesa-rj');

        $this->dispatch('refresh-after-rj.saved');
        $this->dispatch('toast', type: 'success', message: $message);
    }

    /* ===============================
     | LOV SNOMED — Keluhan Utama
     =============================== */
    #[On('lov.selected.keluhanUtamaSnomed')]
    public function onKeluhanUtamaSnomedSelected(string $target, array $payload): void
    {
        $this->dataDaftarPoliRJ['anamnesa']['keluhanUtama']['snomedCode'] = $payload['snomed_code'] ?? '';
        $this->dataDaftarPoliRJ['anamnesa']['keluhanUtama']['snomedDisplayEn'] = $payload['display_en'] ?? '';
        $this->dataDaftarPoliRJ['anamnesa']['keluhanUtama']['snomedDisplayId'] = $payload['display_id'] ?? '';
    }

    #[On('lov.cleared.keluhanUtamaSnomed')]
    public function onKeluhanUtamaSnomedCleared(string $target): void
    {
        $this->dataDaftarPoliRJ['anamnesa']['keluhanUtama']['snomedCode'] = '';
        $this->dataDaftarPoliRJ['anamnesa']['keluhanUtama']['snomedDisplayEn'] = '';
        $this->dataDaftarPoliRJ['anamnesa']['keluhanUtama']['snomedDisplayId'] = '';
    }

    /* ===============================
     | LOV SNOMED — Alergi
     =============================== */
    #[On('lov.selected.alergiSnomed')]
    public function onAlergiSnomedSelected(string $target, array $payload): void
    {
        $this->dataDaftarPoliRJ['anamnesa']['alergi']['snomedCode'] = $payload['snomed_code'] ?? '';
        $this->dataDaftarPoliRJ['anamnesa']['alergi']['snomedDisplayEn'] = $payload['display_en'] ?? '';
        $this->dataDaftarPoliRJ['anamnesa']['alergi']['snomedDisplayId'] = $payload['display_id'] ?? '';
    }

    #[On('lov.cleared.alergiSnomed')]
    public function onAlergiSnomedCleared(string $target): void
    {
        $this->dataDaftarPoliRJ['anamnesa']['alergi']['snomedCode'] = '';
        $this->dataDaftarPoliRJ['anamnesa']['alergi']['snomedDisplayEn'] = '';
        $this->dataDaftarPoliRJ['anamnesa']['alergi']['snomedDisplayId'] = '';
    }

    /* ===============================
     | LOV Alergi BPJS PCare — Makanan/Udara/Obat
     |
     | jenis: 01=Makanan, 02=Udara, 03=Obat (sesuai BPJS PCare).
     | UI: tombol "Cari BPJS" → loadAlergi(jenis) → muncul dropdown
     |     → selectAlergi(jenis, kdAlergi, nmAlergi) → set anamnesa.
     =============================== */
    public function loadAlergiMakanan(): void { $this->loadAlergi('01'); }
    public function loadAlergiUdara(): void   { $this->loadAlergi('02'); }
    public function loadAlergiObat(): void    { $this->loadAlergi('03'); }

    /** Auto-load 3 alergi options (silent — dipanggil saat openAnamnesa). */
    private function preloadAlergiOptions(): void
    {
        foreach (['01', '02', '03'] as $jenis) {
            try {
                $refKey = ['01' => 'Alergi Makanan', '02' => 'Alergi Udara', '03' => 'Alergi Obat'][$jenis];
                $list = $this->loadRefBpjsCache($refKey);
                if ($list === null) continue; // cache kosong → tetap kosong, user bisa klik "Cari BPJS" manual

                $opts = collect($list)
                    ->map(fn($r) => [
                        'kdAlergi' => (string) ($r['kdAlergi'] ?? $r['kode'] ?? ''),
                        'nmAlergi' => (string) ($r['nmAlergi'] ?? $r['nama'] ?? ''),
                    ])
                    ->filter(fn($o) => $o['kdAlergi'] !== '')
                    ->values()
                    ->all();
                array_unshift($opts, ['kdAlergi' => '00', 'nmAlergi' => 'Tidak Ada']);

                match ($jenis) {
                    '01' => $this->alergiMakananOptions = $opts,
                    '02' => $this->alergiUdaraOptions   = $opts,
                    '03' => $this->alergiObatOptions    = $opts,
                };
            } catch (\Throwable $e) {
                \Log::warning('preloadAlergiOptions cache miss', ['jenis' => $jenis, 'err' => $e->getMessage()]);
            }
        }
    }

    /**
     * Wire dari select alergi → simpan kode + lookup desc dari options array.
     * jenis: '01'/'02'/'03'.
     */
    public function changeAlergi(string $jenis, string $kdAlergi): void
    {
        $opts = match ($jenis) {
            '01' => $this->alergiMakananOptions,
            '02' => $this->alergiUdaraOptions,
            '03' => $this->alergiObatOptions,
        };
        $found = collect($opts)->firstWhere('kdAlergi', $kdAlergi);
        $desc = $found['nmAlergi'] ?? 'Tidak Ada';

        $this->selectAlergi($jenis, $kdAlergi, $desc);
    }

    private function loadAlergi(string $jenis): void
    {
        try {
            // ===== 1) Coba ambil dari cache lokal ref_bpjs_table =====
            $refKey = match ($jenis) {
                '01' => 'Alergi Makanan',
                '02' => 'Alergi Udara',
                '03' => 'Alergi Obat',
            };
            $list = $this->loadRefBpjsCache($refKey);
            $source = 'cache';

            // ===== 2) Cache kosong → fallback hit BPJS API =====
            if ($list === null) {
                $resp = $this->getAlergi($jenis)->getOriginalContent();
                $code = $resp['metadata']['code'] ?? 0;
                if ($code != 200) {
                    $msg = $resp['metadata']['message'] ?? "code {$code}";
                    $this->dispatch('toast', type: 'error',
                        message: 'BPJS getAlergi: ' . $msg, title: 'BPJS');
                    return;
                }
                $list = $resp['response']['list'] ?? $resp['response'] ?? [];
                $source = 'api';
            }

            $opts = collect($list)
                ->map(fn($r) => [
                    'kdAlergi' => (string) ($r['kdAlergi'] ?? $r['kode'] ?? ''),
                    'nmAlergi' => (string) ($r['nmAlergi'] ?? $r['nama'] ?? ''),
                ])
                ->filter(fn($o) => $o['kdAlergi'] !== '')
                ->values()
                ->all();

            // Tambahin opsi "Tidak Ada" di paling atas (siklik-lite default)
            array_unshift($opts, ['kdAlergi' => '00', 'nmAlergi' => 'Tidak Ada']);

            match ($jenis) {
                '01' => $this->alergiMakananOptions = $opts,
                '02' => $this->alergiUdaraOptions   = $opts,
                '03' => $this->alergiObatOptions    = $opts,
            };

            if ($source === 'api') {
                $this->dispatch('toast', type: 'info',
                    message: 'Cache lokal kosong — diambil dari BPJS langsung. Sebaiknya sync via Master > Ref BPJS.',
                    title: 'BPJS', duration: 5000);
            }
        } catch (\Exception $e) {
            \Log::error('PCare getAlergi exception', ['jenis' => $jenis, 'error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error',
                message: 'Error PCare: ' . $e->getMessage(), title: 'BPJS');
        }
    }

    /**
     * Read decoded list dari ref_bpjs_table. Return null kalau row tidak ada
     * atau JSON invalid (caller harus fallback ke API).
     * Lookup case-insensitive (siklik-lite convention) — toleran terhadap
     * klinik existing yg casing-nya beda.
     */
    private function loadRefBpjsCache(string $refKey): ?array
    {
        $row = DB::table('ref_bpjs_table')
            ->whereRaw('upper(ref_keterangan) = upper(?)', [$refKey])
            ->value('ref_json');

        if (!$row) return null;

        $decoded = json_decode($row, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function selectAlergi(string $jenis, string $kdAlergi, string $nmAlergi): void
    {
        $field = match ($jenis) {
            '01' => ['alergiMakan',  'alergiMakanDesc'],
            '02' => ['alergiUdara',  'alergiUdaraDesc'],
            '03' => ['alergiObat',   'alergiObatDesc'],
            default => null,
        };
        if (!$field) return;

        [$kdField, $descField] = $field;
        $this->dataDaftarPoliRJ['anamnesa']['alergi'][$kdField]   = $kdAlergi;
        $this->dataDaftarPoliRJ['anamnesa']['alergi'][$descField] = $nmAlergi;

        // Tutup dropdown opsi
        match ($jenis) {
            '01' => $this->alergiMakananOptions = [],
            '02' => $this->alergiUdaraOptions   = [],
            '03' => $this->alergiObatOptions    = [],
        };
    }

    /* ===============================
     | REKONSILIASI OBAT (anamnesa)
     |
     | List obat yg sedang dikonsumsi pasien sebelum kunjungan ini —
     | penting buat skrining interaksi obat saat dokter resepkan.
     | Disimpan di dataDaftarPoliRJ.anamnesa.rekonsiliasiObat[] (array of
     | {namaObat, dosis, rute}). Pola contek dari siklik-lite.
     =============================== */
    public function addRekonsiliasiObat(): void
    {
        $namaObat = trim((string) ($this->rekonsiliasiObatInput['namaObat'] ?? ''));
        $dosis    = trim((string) ($this->rekonsiliasiObatInput['dosis'] ?? ''));
        $rute     = trim((string) ($this->rekonsiliasiObatInput['rute'] ?? ''));

        if ($namaObat === '') {
            $this->dispatch('toast', type: 'error', message: 'Nama obat wajib diisi.');
            return;
        }

        $existing = $this->dataDaftarPoliRJ['anamnesa']['rekonsiliasiObat'] ?? [];
        $isDuplicate = collect($existing)
            ->contains(fn($i) => mb_strtolower($i['namaObat'] ?? '') === mb_strtolower($namaObat));

        if ($isDuplicate) {
            $this->dispatch('toast', type: 'warning', message: "Obat '{$namaObat}' sudah ada di daftar.");
            return;
        }

        $existing[] = [
            'namaObat' => $namaObat,
            'dosis'    => $dosis,
            'rute'     => $rute,
        ];
        $this->dataDaftarPoliRJ['anamnesa']['rekonsiliasiObat'] = $existing;

        $this->rekonsiliasiObatInput = ['namaObat' => '', 'dosis' => '', 'rute' => ''];
        $this->dispatch('toast', type: 'success', message: "Obat '{$namaObat}' ditambahkan.");
    }

    public function removeRekonsiliasiObat(string $namaObat): void
    {
        $list = collect($this->dataDaftarPoliRJ['anamnesa']['rekonsiliasiObat'] ?? [])
            ->reject(fn($i) => ($i['namaObat'] ?? '') === $namaObat)
            ->values()
            ->all();

        $this->dataDaftarPoliRJ['anamnesa']['rekonsiliasiObat'] = $list;
        $this->dispatch('toast', type: 'success', message: "Obat '{$namaObat}' dihapus.");
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->alergiMakananOptions = [];
        $this->alergiUdaraOptions   = [];
        $this->alergiObatOptions    = [];
        $this->rekonsiliasiObatInput = ['namaObat' => '', 'dosis' => '', 'rute' => ''];
    }

    public function mount()
    {
        $this->registerAreas(['modal-anamnesa-rj']);
    }

    public function rendering(): void
    {
        $default = $this->getDefaultAnamnesa();
        $current = $this->dataDaftarPoliRJ['anamnesa'] ?? [];
        $this->dataDaftarPoliRJ['anamnesa'] = array_replace_recursive($default, $current);
    }
};

?>
<div>
    {{-- CONTAINER UTAMA - SATU-SATUNYA WIRE:KEY --}}
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-anamnesa-rj', [$rjNo ?? 'new']) }}">
        {{-- BODY --}}
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                {{-- jika anamnesa ada --}}
                @if (isset($dataDaftarPoliRJ['anamnesa']))
                    <div class="w-full">
                        <div id="TransaksiRawatJalan" x-data="{ activeTab: '{{ $dataDaftarPoliRJ['anamnesa']['pengkajianPerawatanTab'] ?? 'Pengkajian' }}' }" class="w-full">

                            {{-- TAB NAVIGATION --}}
                            <x-scrollable-tabs class="w-full px-2 mb-2 border-b border-gray-200 dark:border-gray-700">
                                <ul
                                    class="flex flex-nowrap whitespace-nowrap w-full -mb-px text-xs font-medium text-center text-gray-500 dark:text-gray-400">

                                    {{-- PENGKAJIAN PERAWATAN TAB --}}
                                    <li class="mr-2">
                                        <label
                                            class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarPoliRJ['anamnesa']['pengkajianPerawatanTab'] ?? 'Pengkajian' }}'
                                                ?
                                                'text-primary border-primary bg-gray-100' : ''"
                                            @click="activeTab ='{{ $dataDaftarPoliRJ['anamnesa']['pengkajianPerawatanTab'] ?? 'Pengkajian' }}'">
                                            {{ $dataDaftarPoliRJ['anamnesa']['pengkajianPerawatanTab'] ?? 'Pengkajian' }}
                                        </label>
                                    </li>

                                    {{-- STATUS PSIKOLOGIS TAB --}}
                                    <li class="mr-2">
                                        <label
                                            class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarPoliRJ['anamnesa']['statusPsikologisTab'] ?? 'Status Psikologis' }}'
                                                ?
                                                'text-primary border-primary bg-gray-100' : ''"
                                            @click="activeTab ='{{ $dataDaftarPoliRJ['anamnesa']['statusPsikologisTab'] ?? 'Status Psikologis' }}'">
                                            {{ $dataDaftarPoliRJ['anamnesa']['statusPsikologisTab'] ?? 'Status Psikologis' }}
                                        </label>
                                    </li>

                                    {{-- BATUK TAB --}}
                                    <li class="mr-2">
                                        <label
                                            class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarPoliRJ['anamnesa']['batukTab'] ?? 'Screening Batuk' }}'
                                                ?
                                                'text-primary border-primary bg-gray-100' : ''"
                                            @click="activeTab ='{{ $dataDaftarPoliRJ['anamnesa']['batukTab'] ?? 'Screening Batuk' }}'">
                                            {{ $dataDaftarPoliRJ['anamnesa']['batukTab'] ?? 'Screening Batuk' }}
                                        </label>
                                    </li>

                                    {{-- REKONSILIASI OBAT TAB --}}
                                    <li class="mr-2">
                                        <label
                                            class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarPoliRJ['anamnesa']['rekonsiliasiObatTab'] ?? 'Rekonsiliasi Obat' }}'
                                                ?
                                                'text-primary border-primary bg-gray-100' : ''"
                                            @click="activeTab ='{{ $dataDaftarPoliRJ['anamnesa']['rekonsiliasiObatTab'] ?? 'Rekonsiliasi Obat' }}'">
                                            {{ $dataDaftarPoliRJ['anamnesa']['rekonsiliasiObatTab'] ?? 'Rekonsiliasi Obat' }}
                                        </label>
                                    </li>
                                </ul>
                            </x-scrollable-tabs>

                            {{-- TAB CONTENTS --}}
                            <div class="w-full p-2">
                                {{-- PENGKAJIAN PERAWATAN TAB --}}
                                @if (isset($dataDaftarPoliRJ['anamnesa']['pengkajianPerawatanTab']))
                                    <div class="w-full"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarPoliRJ['anamnesa']['pengkajianPerawatanTab'] ?? 'Pengkajian' }}'">
                                        @include('pages.transaksi.rj.emr-rj.anamnesa.tabs.pengkajian-perawatan-tab')
                                    </div>
                                @endif

                                {{-- STATUS PSIKOLOGIS TAB --}}
                                @if (isset($dataDaftarPoliRJ['anamnesa']['statusPsikologisTab']))
                                    <div class="w-full"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarPoliRJ['anamnesa']['statusPsikologisTab'] ?? 'Status Psikologis' }}'">
                                        @include('pages.transaksi.rj.emr-rj.anamnesa.tabs.status-psikologis-tab')
                                    </div>
                                @endif

                                {{-- BATUK TAB --}}
                                @if (isset($dataDaftarPoliRJ['anamnesa']['batukTab']))
                                    <div class="w-full"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarPoliRJ['anamnesa']['batukTab'] ?? 'Screening Batuk' }}'">
                                        @include('pages.transaksi.rj.emr-rj.anamnesa.tabs.batuk-tab')
                                    </div>
                                @endif

                                {{-- REKONSILIASI OBAT TAB (no @if guard — selalu render, visibility via x-show) --}}
                                <div class="w-full"
                                    x-show.transition.in.opacity.duration.600="activeTab === 'Rekonsiliasi Obat'">
                                    @include('pages.transaksi.rj.emr-rj.anamnesa.tabs.rekonsiliasi-obat-tab')
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
