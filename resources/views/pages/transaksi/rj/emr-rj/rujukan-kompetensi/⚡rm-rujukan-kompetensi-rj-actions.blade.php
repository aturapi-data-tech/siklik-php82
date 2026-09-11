<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Txn\Rj\KunjunganPcarePayloadTrait;
use App\Http\Traits\Txn\Rj\RujukanKompetensiRJTrait;
use App\Http\Traits\BPJS\PcareTrait;
use App\Http\Traits\BPJS\PcareSisruteTrait;
use App\Support\Rujukan\RujukanKompetensiOptions;

new class extends Component {
    // RujukanKompetensiRJTrait memuat bagian non-UI panel ini (bentuk node,
    // prasyarat, referensi PCare, persist); PcareSisruteTrait empat endpoint
    // Sisrute; KunjunganPcarePayloadTrait merakit isi kunjungan yang IDENTIK
    // dengan yang dikirim Daftar Kunjungan RJ.
    use EmrRJTrait, KunjunganPcarePayloadTrait, RujukanKompetensiRJTrait, PcareTrait, PcareSisruteTrait;

    public ?int $rjNo = null;
    public bool $isFormLocked = false;

    /** Referensi kunjungan — TIDAK di-bind ke isian. */
    public array $dataDaftarPoliRJ = [];

    /** Isian rujukan — dipersist ke node `rujukanKompetensi` di JSON kunjungan. */
    public array $formRujukan = [];

    /**
     * Referensi PCare. BUKAN bagian node: ini data master yang bisa berubah di
     * pusat, jadi dimuat ulang saat modal dibuka, bukan dibekukan di CLOB.
     */
    public array $spesialisList = [];
    public array $subSpesialisList = [];
    public array $saranaList = [];

    /** Pesan status per langkah (menetap di panel, bukan toast yang lewat). */
    public string $infoKriteria = '';
    public string $infoKandidat = '';

    /** Respons mentah panggilan terakhir — bahan lapor saat pusat bermasalah. */
    public string $responsJudul = '';
    public string $responsMentah = '';

    /* ═══════════════════════════════════════
     | MOUNT & BUKA/TUTUP MODAL
    ═══════════════════════════════════════ */
    public function mount(): void
    {
        $this->formRujukan = $this->defaultFormRujukan();

        if (empty($this->rjNo)) {
            return;
        }

        $dataDaftarPoliRJ = $this->findDataRJ($this->rjNo);
        if (empty($dataDaftarPoliRJ)) {
            return;
        }

        $this->dataDaftarPoliRJ = $dataDaftarPoliRJ;
        $this->pulihkanNode();
    }

    public function openModal(): void
    {
        if (empty($this->rjNo)) {
            return;
        }

        // Baca ulang saat dibuka: Encounter, diagnosa, dan pendaftaran PCare bisa
        // terbit SETELAH panel pertama kali digambar, jadi prasyarat harus dinilai
        // dari data terkini — bukan dari salinan saat halaman EMR dimuat.
        $dataDaftarPoliRJ = $this->findDataRJ($this->rjNo);
        if (empty($dataDaftarPoliRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan RJ tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $dataDaftarPoliRJ;
        $this->pulihkanNode();

        // Diagnosa belum pernah dipilih → ikuti diagnosa utama EMR terkini.
        if (!$this->sudahTerkirim() && trim((string) $this->formRujukan['kodeDiagnosa']) === '') {
            $this->prefillDariKunjungan();
        }

        $this->formRujukan['encounterId'] = $this->encounterUuid();
        $this->isFormLocked = $this->sudahTerkirim() || $this->checkEmrRJStatus($this->rjNo);

        $this->muatReferensiPcare();

        $this->dispatch('open-modal', name: 'rujukan-kompetensi-rj-' . $this->rjNo);
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'rujukan-kompetensi-rj-' . $this->rjNo);
    }

    /** Muat node tersimpan di atas bentuk baku supaya key baru tetap ada. */
    private function pulihkanNode(): void
    {
        $tersimpan = $this->dataDaftarPoliRJ['rujukanKompetensi'] ?? [];

        if (!empty($tersimpan) && is_array($tersimpan)) {
            $this->formRujukan = array_replace($this->defaultFormRujukan(), $tersimpan);
            return;
        }

        $this->formRujukan = $this->defaultFormRujukan();
        $this->prefillDariKunjungan();
    }

    /** Pra-isi diagnosa rujukan dari diagnosa utama EMR. */
    private function prefillDariKunjungan(): void
    {
        $diagnosaUtama = $this->diagnosaUtama();
        $this->formRujukan['kodeDiagnosa'] = (string) ($diagnosaUtama['icdX'] ?? '');
        $this->formRujukan['diagnosaDesc'] = (string) ($diagnosaUtama['diagDesc'] ?? '');
    }

    /* ═══════════════════════════════════════
     | ISIAN — diagnosa & tindakan lewat LOV
    ═══════════════════════════════════════ */
    #[On('lov.selected.rujukanKompetensiDiagnosaRJ')]
    public function onLovDiagnosaSelected(string $target, array $payload): void
    {
        if ($this->isFormLocked) {
            return;
        }

        // Ke sistem eksternal BPJS yang dikirim ICDX, bukan diag_id internal
        // (lihat skill diagnosa-flow).
        $icdx = trim((string) ($payload['icdx'] ?? ''));
        if ($icdx === '') {
            $this->dispatch('toast', type: 'error', message: 'Diagnosa itu belum punya kode ICD-10 (icdx) di master — pilih kode lain.');
            return;
        }

        $this->formRujukan['kodeDiagnosa'] = $icdx;
        $this->formRujukan['diagnosaDesc'] = trim((string) ($payload['diag_desc'] ?? ''));

        // Diagnosa ganti → linkId kriteria lama tidak berlaku lagi (dinamis per ICD-10).
        $this->formRujukan['kriteriaList'] = [];
        $this->formRujukan['kriteriaSumber'] = '';
        $this->formRujukan['kriteriaPilih'] = '';
        $this->formRujukan['kriteriaIcd9'] = '';
        $this->formRujukan['kriteriaIcd9Desc'] = '';
        $this->infoKriteria = '';
        $this->lupakanKandidat();
        $this->simpanNode();
    }

    #[On('lov.selected.rujukanKompetensiIcd9RJ')]
    public function onLovIcd9Selected(string $target, array $payload): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $kode = trim((string) ($payload['proc_id'] ?? ''));
        if ($kode === '') {
            $this->dispatch('toast', type: 'error', message: 'Data tindakan tidak valid.');
            return;
        }

        $this->formRujukan['kriteriaIcd9'] = $kode;
        $this->formRujukan['kriteriaIcd9Desc'] = trim((string) ($payload['proc_desc'] ?? ''));
        $this->lupakanKandidat();
        $this->simpanNode();
    }

    #[On('lov.cleared.rujukanKompetensiIcd9RJ')]
    public function onLovIcd9Cleared(string $target = ''): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->formRujukan['kriteriaIcd9'] = '';
        $this->formRujukan['kriteriaIcd9Desc'] = '';
        $this->lupakanKandidat();
        $this->simpanNode();
    }

    /* ═══════════════════════════════════════
     | ISIAN — wilayah & tujuan layanan
    ═══════════════════════════════════════ */
    public function pilihPropinsi(string $kode): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $propinsi = collect($this->formRujukan['wilayahList'])->firstWhere('kode', $kode);

        $this->formRujukan['kodePropinsi'] = $propinsi ? (string) $propinsi['kode'] : '';
        $this->formRujukan['namaPropinsi'] = $propinsi ? (string) $propinsi['nama'] : '';
        // Kabupaten lama ada di propinsi lain — dikosongkan supaya pasangan kode
        // tak pernah tidak sinkron (gateway menolak pasangan yang tak sejalan).
        $this->formRujukan['kodeKabupaten'] = '';
        $this->formRujukan['namaKabupaten'] = '';
        $this->lupakanKandidat();
        $this->simpanNode();
    }

    public function pilihKabupaten(string $kode): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $kabupaten = collect($this->formRujukan['kabupatenList'])->firstWhere('kode', $kode);

        $this->formRujukan['kodeKabupaten'] = $kabupaten ? (string) $kabupaten['kode'] : '';
        $this->formRujukan['namaKabupaten'] = $kabupaten ? (string) $kabupaten['nama'] : '';
        $this->lupakanKandidat();
        $this->simpanNode();
    }

    public function pilihSpesialis(string $kode): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->formRujukan['kodeSpesialis'] = $kode;
        $this->formRujukan['namaSpesialis'] = (string) (collect($this->spesialisList)->firstWhere('kd', $kode)['nm'] ?? '');
        $this->formRujukan['kodeSubSpesialis'] = '';
        $this->formRujukan['namaSubSpesialis'] = '';
        $this->subSpesialisList = [];
        $this->lupakanKandidat();
        $this->muatSubSpesialis();
        $this->simpanNode();
    }

    public function pilihSubSpesialis(string $kode): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->formRujukan['kodeSubSpesialis'] = $kode;
        $this->formRujukan['namaSubSpesialis'] = (string) (collect($this->subSpesialisList)->firstWhere('kd', $kode)['nm'] ?? '');
        $this->lupakanKandidat();
        $this->simpanNode();
    }

    public function pilihSarana(string $kode): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->formRujukan['kodeSarana'] = $kode;
        $this->formRujukan['namaSarana'] = (string) (collect($this->saranaList)->firstWhere('kd', $kode)['nm'] ?? '');
        $this->lupakanKandidat();
        $this->simpanNode();
    }

    /**
     * Radio kriteria & tanggal estimasi di-bind langsung ke formRujukan; keduanya
     * ikut menentukan kandidat, jadi perubahannya membatalkan daftar kandidat.
     */
    public function updated(string $name): void
    {
        if (!in_array($name, ['formRujukan.kriteriaPilih', 'formRujukan.estimasiRujuk'], true)) {
            return;
        }

        if ($name === 'formRujukan.kriteriaPilih') {
            $this->formRujukan['kriteriaIcd9'] = '';
            $this->formRujukan['kriteriaIcd9Desc'] = '';
        }

        $this->lupakanKandidat();
        $this->simpanNode();
    }

    /* ═══════════════════════════════════════
     | LANGKAH 1 — AMBIL KRITERIA
    ═══════════════════════════════════════ */
    public function ambilKriteria(): void
    {
        $this->infoKriteria = '';

        if ($this->isFormLocked) {
            return;
        }

        $kodeDiagnosa = trim((string) $this->formRujukan['kodeDiagnosa']);
        if (!preg_match('/^[A-Z][0-9]{2}(\.[0-9]{1,2})?$/', $kodeDiagnosa)) {
            $this->dispatch('toast', type: 'error', message: "Kode diagnosa \"{$kodeDiagnosa}\" bukan format ICD-10 (contoh N40 atau J18.0) — pilih lewat pencarian diagnosa.");
            return;
        }

        $hasil = $this->sisruteGetKriteriaRujukan($kodeDiagnosa, $this->encounterUuid());
        $this->catatRespons('GetKriteriaRujukan', $hasil);

        if (empty($hasil['ok'])) {
            $this->toastGagal('Ambil kriteria gagal', $hasil);
            return;
        }

        $respon = (array) ($hasil['response'] ?? []);
        $kriteria = $this->normalisasiKriteria($respon);

        if ($kriteria === []) {
            $this->dispatch('toast', type: 'error', message: 'Respons tidak memuat kriteria rujukan. Diagnosa terlalu umum sering tidak punya kriteria (mis. A02 kosong, A02.9 jalan) — coba kode yang lebih rinci.');
            return;
        }

        // JejaringWilayah datang sebagai Questionnaire FHIR (34 provinsi + ±508
        // kab/kota); perataannya dipusatkan di PcareSisruteTrait.
        $wilayah = $this->sisruteWilayahDariKriteria($respon);

        $this->formRujukan['kriteriaList'] = $kriteria;
        $this->formRujukan['kriteriaSumber'] = 'server';
        $this->formRujukan['kriteriaPilih'] = '';
        $this->formRujukan['kriteriaIcd9'] = '';
        $this->formRujukan['kriteriaIcd9Desc'] = '';
        $this->formRujukan['wilayahList'] = $wilayah['propinsiList'];
        $this->formRujukan['kabupatenList'] = $wilayah['kabupatenList'];
        $this->lupakanKandidat();
        $this->simpanNode('Rujukan Kompetensi — ambil kriteria diagnosa ' . $kodeDiagnosa);

        $this->infoKriteria = '✓ Kriteria dimuat dari server (' . count($kriteria) . ' item). ' . RujukanKompetensiOptions::PETUNJUK_UMUM;
    }

    /* ═══════════════════════════════════════
     | LANGKAH 2 — CARI & PILIH KANDIDAT
    ═══════════════════════════════════════ */
    public function cariKandidat(): void
    {
        $this->infoKandidat = '';

        if ($this->isFormLocked) {
            return;
        }

        $itemKriteria = $this->bangunItemKriteria();
        if (!$itemKriteria) {
            return;
        }

        $estimasi = $this->estimasiRujukCarbon();
        if (!$estimasi) {
            $this->dispatch('toast', type: 'error', message: 'Estimasi tanggal rujuk tidak valid (format dd/mm/yyyy).');
            return;
        }
        if (trim((string) $this->formRujukan['kodeSubSpesialis']) === '') {
            $this->dispatch('toast', type: 'error', message: 'Pilih spesialis lalu subspesialis dulu — kandidat dicari berdasarkan subspesialis.');
            return;
        }
        if (trim((string) $this->formRujukan['kodePropinsi']) === '') {
            $this->dispatch('toast', type: 'error', message: 'Pilih jejaring wilayah rujukan (provinsi) dulu.');
            return;
        }

        $hasil = $this->sisruteGetFaskesRujukan([
            'encounterId' => $this->encounterUuid(),
            'kodeSubSpesialis' => (string) $this->formRujukan['kodeSubSpesialis'],
            'kodeSarana' => (string) $this->formRujukan['kodeSarana'],
            'kodeDiagnosa' => (string) $this->formRujukan['kodeDiagnosa'],
            'estimasiRujuk' => $estimasi->format(RujukanKompetensiOptions::FORMAT_TANGGAL_BPJS),
            'kriteriaRujukan' => $itemKriteria,
            'codeJejaringWilayah' => $this->codeJejaringWilayah(),
        ]);
        $this->catatRespons('GetFaskesRujukan', $hasil);

        if (empty($hasil['ok'])) {
            $this->toastGagal('Cari kandidat gagal', $hasil);
            return;
        }

        $respon = (array) ($hasil['response'] ?? []);
        $list = $respon['list'] ?? ($respon['faskes'] ?? (array_is_list($respon) ? $respon : []));

        $this->formRujukan['kandidatList'] = array_values(is_array($list) ? $list : []);
        $this->formRujukan['kandidatIdx'] = null;
        $this->simpanNode('Rujukan Kompetensi — cari kandidat (' . count($this->formRujukan['kandidatList']) . ' hasil)');

        // Kandidat kosong BUKAN error: server menilai tidak ada faskes yang cocok.
        $this->infoKandidat = $this->formRujukan['kandidatList'] === []
            ? 'Tidak ada kandidat untuk kombinasi diagnosa/kriteria/subspesialis/wilayah ini. Periksa lagi isiannya.'
            : '✓ ' . count($this->formRujukan['kandidatList']) . ' kandidat ditemukan — pilih satu.';
    }

    public function pilihKandidat(int $index): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $kandidat = $this->kandidatBaris($index);
        if (!$kandidat) {
            return;
        }

        if ($kandidat['kdppk'] === '') {
            $this->dispatch('toast', type: 'error', message: "\"{$kandidat['nama']}\" tidak punya kode PPK BPJS — tidak bisa jadi tujuan rujukan JKN.");
            return;
        }

        // Menekan baris yang SUDAH terpilih = membatalkan pilihan.
        if ((int) ($this->formRujukan['kandidatIdx'] ?? -1) === $index) {
            $this->formRujukan['kandidatIdx'] = null;
            $this->infoKandidat = '';
            $this->simpanNode();
            return;
        }

        $this->formRujukan['kandidatIdx'] = $index;
        $this->infoKandidat = \App\Support\Rujukan\RujukanKompetensiTampil::infoTujuan($this->formRujukan['kandidatList'][$index]);
        $this->simpanNode();
    }

    /* ═══════════════════════════════════════
     | LANGKAH 3 — KIRIM (kunjungan PCare + rujukan sekaligus)
    ═══════════════════════════════════════ */
    public function kirimRujukan(): void
    {
        abort_unless(auth()->user()?->can('rujukan.kirim'), 403, 'Tidak berwenang mengirim rujukan.');

        if ($this->isFormLocked || $this->sudahTerkirim()) {
            $this->dispatch('toast', type: 'error', message: 'Rujukan sudah terbit / formulir terkunci.');
            return;
        }

        $kurang = $this->prasyaratKurang();
        if ($kurang !== []) {
            $this->dispatch('toast', type: 'error', message: 'Data belum siap: ' . implode('; ', $kurang) . '.');
            return;
        }

        $kandidat = $this->kandidatBaris($this->formRujukan['kandidatIdx'] ?? null);
        if (!$kandidat) {
            $this->dispatch('toast', type: 'error', message: 'Pilih kandidat faskes tujuan dulu (Langkah 2). Tujuan WAJIB dari daftar kandidat.');
            return;
        }

        $itemKriteria = $this->bangunItemKriteria();
        $estimasi = $this->estimasiRujukCarbon();
        if (!$itemKriteria || !$estimasi) {
            $this->dispatch('toast', type: 'error', message: 'Kriteria / estimasi tanggal rujuk belum lengkap.');
            return;
        }

        $kunjungan = $this->buildKunjunganPayload((string) $this->rjNo, $this->dataDaftarPoliRJ);
        if ($kunjungan === null) {
            return;
        }
        $kunjungan['kdStatusPulang'] = RujukanKompetensiOptions::STATUS_PULANG_RUJUK;

        $catatan = trim((string) $this->formRujukan['catatan']);
        $keterangan = $catatan !== '' ? $catatan : 'Rujukan ke ' . $kandidat['nama'];

        $hasilKirim = $this->sisrutePostKunjungan(
            $kunjungan,
            [
                'tglEstRujuk' => $estimasi->format(RujukanKompetensiOptions::FORMAT_TANGGAL_BPJS),
                'kdppk' => $kandidat['kdppk'],
                'subSpesialis' => [
                    'kdSubSpesialis1' => (string) $this->formRujukan['kodeSubSpesialis'],
                    'kdSarana' => (string) $this->formRujukan['kodeSarana'],
                ],
                'khusus' => null,
            ],
            [
                'kodeFaskesSatuSehat' => $this->kodeFaskesSatuSehat(),
                'idPasienSatuSehat' => $this->patientUuid(),
                // Org ID SATUSEHAT & kdppk WAJIB dari BARIS KANDIDAT YANG SAMA.
                'kdppkSatuSehatTujuanRujukan' => $kandidat['orgId'],
                'kdDokterSatuSehat' => $this->dokterUuid(),
                'encounter' => ['reference' => $this->encounterUuid()],
                'patientInstruction' => $keterangan,
                'kriteriaRujukan' => $itemKriteria,
                'keteranganRujukan' => $keterangan,
                'codeJejaringWilayah' => $this->codeJejaringWilayah(),
            ],
        );
        $this->catatRespons('postKunjungan', $hasilKirim);

        // Nomor dibaca LEBIH DULU, bahkan saat gateway melaporkan gagal: rujukan
        // yang sudah terbentuk tidak boleh hilang cuma karena BPJS gagal
        // menerbitkan nomornya di percobaan yang sama.
        $nomor = $this->sisruteBacaNomorRujukan($hasilKirim);

        if ($nomor['noRujukanSatuSehat'] === '') {
            if (empty($hasilKirim['ok'])) {
                $this->toastGagal('Kirim rujukan gagal', $hasilKirim);
                return;
            }

            $this->dispatch('toast', type: 'error', message: 'Server merespons sukses tapi No. Rujukan SATUSEHAT tidak terbit — gangguan pusat yang dikenal. Data TIDAK disimpan; JANGAN kirim ulang berturut-turut, cek dulu di PCare.', duration: 12000);
            return;
        }

        $this->formRujukan['hasil'] = [
            'noRujukanPcare' => $nomor['noRujukanPcare'],
            'noRujukanSatuSehat' => $nomor['noRujukanSatuSehat'],
            'serviceRequestId' => $nomor['serviceRequestId'],
            'traceId' => $nomor['traceId'],
            'noKunjunganPcare' => $nomor['noKunjunganPcare'] ?: (string) ($kunjungan['noKunjungan'] ?? ''),
            'tglRujukan' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
            'tujuanNama' => $kandidat['nama'],
            'tujuanPpk' => $kandidat['kdppk'],
            'tujuanSatuSehat' => $kandidat['orgId'],
            // Strata & alamat ikut dibekukan: daftar kandidat bisa berubah di pusat,
            // sedangkan surat rujukan harus mencetak keterangan faskes sebagaimana
            // saat rujukan diterbitkan.
            'tujuanStrata' => $kandidat['strata'],
            'tujuanAlamat' => $kandidat['alamat'],
            'dikirimOleh' => auth()->user()->myuser_name ?? (auth()->user()->name ?? 'SYSTEM'),
            // Kode pengirim dipakai TtdUser untuk menempelkan TTD di cetakan.
            'dikirimOlehCode' => (string) (auth()->user()->myuser_code ?? ''),
            'dikirimPada' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
        ];
        $this->formRujukan['responMentah'] = $this->responsMentah;
        $this->formRujukan['dibatalkan'] = null;
        $this->isFormLocked = true;

        $this->simpanNode(
            'Rujukan Kompetensi — TERKIRIM ke ' . $kandidat['nama'] .
                ' (PCare ' . ($nomor['noRujukanPcare'] ?: '-') . ' / SATUSEHAT ' . $nomor['noRujukanSatuSehat'] . ')',
            true,
        );

        $this->dispatch('toast', type: 'success', message: 'Rujukan terkirim. No. PCare ' . ($nomor['noRujukanPcare'] ?: '-') . ', No. SATUSEHAT ' . $nomor['noRujukanSatuSehat'], duration: 8000);
    }

    /* ═══════════════════════════════════════
     | BATALKAN — menghapus SAMPAI pendaftaran PCare
    ═══════════════════════════════════════ */
    public function batalkanRujukan(): void
    {
        abort_unless(auth()->user()?->can('rujukan.batal'), 403, 'Tidak berwenang membatalkan rujukan.');

        if (!$this->sudahTerkirim()) {
            return;
        }

        $noKunjungan = (string) ($this->formRujukan['hasil']['noKunjunganPcare'] ?? '');
        if ($noKunjungan === '') {
            $noKunjungan = 'RJ-' . $this->rjNo;
        }

        $hasil = $this->sisruteDeleteKunjungan($noKunjungan);
        $this->catatRespons('deleteKunjungan', $hasil);

        if (empty($hasil['ok'])) {
            $this->toastGagal('Pembatalan gagal', $hasil);
            return;
        }

        $hasilLama = (array) $this->formRujukan['hasil'];
        $this->formRujukan['hasil'] = [];
        $this->formRujukan['dibatalkan'] = [
            'oleh' => auth()->user()->myuser_name ?? (auth()->user()->name ?? 'SYSTEM'),
            'pada' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
            'alasan' => 'Dibatalkan dari panel Rujukan Kompetensi (pendaftaran PCare ikut terhapus)',
        ];
        $this->lupakanKandidat();
        $this->isFormLocked = $this->checkEmrRJStatus($this->rjNo);

        $this->simpanNode(
            'Rujukan Kompetensi — DIBATALKAN (PCare ' . ($hasilLama['noRujukanPcare'] ?? '-') .
                ' / SATUSEHAT ' . ($hasilLama['noRujukanSatuSehat'] ?? '-') . '); pendaftaran PCare ikut terhapus',
            true,
        );

        $this->dispatch('toast', type: 'success', message: 'Rujukan dibatalkan. Pendaftaran PCare pasien ini IKUT TERHAPUS — daftarkan ulang dari Daftar kunjungan.', duration: 12000);
    }

    /* ═══ CETAK ═══ */
    public function cetakSuratRujukan(): void
    {
        if (!$this->sudahTerkirim()) {
            $this->dispatch('toast', type: 'error', message: 'Surat rujukan baru bisa dicetak setelah nomor rujukan terbit.');
            return;
        }

        $this->dispatch('cetak-surat-rujukan-rj.open', rjNo: $this->rjNo);
    }
};
?>

<div>
    @php
        $sudahTerkirim = $this->sudahTerkirim();
        $hasil = $formRujukan['hasil'] ?? [];
    @endphp

    {{-- ══ KARTU RINGKAS (inline di tab Tindak Lanjut) ══ --}}
    <div class="p-5 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex flex-wrap items-center min-w-0 gap-2">
                    <svg class="w-5 h-5 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                    </svg>
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Rujukan Berbasis Kompetensi</h3>
                    @if ($sudahTerkirim)
                        <x-badge variant="success">Terkirim</x-badge>
                    @else
                        <x-badge variant="warning">Belum dikirim</x-badge>
                    @endif
                </div>

                <div class="flex shrink-0">
                    <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                        wire:target="openModal" :disabled="!$rjNo" class="gap-2">
                        <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                            {{ $sudahTerkirim ? 'Lihat Rujukan' : 'Buat Rujukan' }}
                        </span>
                        <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                            <x-loading class="w-4 h-4" /> Memuat...
                        </span>
                    </x-primary-button>
                </div>
            </div>

            <p class="text-base text-muted dark:text-gray-400">
                Rujukan pasien rawat jalan ke faskes lanjutan lewat BPJS PCare, yang meneruskannya ke SATUSEHAT.
                Tiga langkah: ambil kriteria sesuai diagnosa, cari kandidat faskes, lalu kirim.
            </p>

            @if ($sudahTerkirim)
                <div class="flex flex-wrap text-sm gap-x-6 gap-y-1 text-muted dark:text-gray-400">
                    <span>No. Rujukan PCare:
                        <strong class="font-mono text-ink dark:text-gray-200">{{ $hasil['noRujukanPcare'] ?: '-' }}</strong></span>
                    <span>No. SATUSEHAT:
                        <strong class="font-mono text-ink dark:text-gray-200">{{ $hasil['noRujukanSatuSehat'] }}</strong></span>
                    <span>Tujuan: <strong class="text-ink dark:text-gray-200">{{ $hasil['tujuanNama'] ?? '-' }}</strong></span>
                </div>
            @endif
        </div>
    </div>

    {{-- ══ MODAL FORMULIR ══ --}}
    <x-modal name="rujukan-kompetensi-rj-{{ $rjNo }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]">

            {{-- HEADER --}}
            <div class="px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-500/10">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Rujukan Berbasis Kompetensi</h2>
                            <p class="mt-0.5 text-xs text-muted dark:text-gray-400">
                                Rawat Jalan &rarr; faskes lanjutan &middot; lewat BPJS PCare yang meneruskan ke SATUSEHAT
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @if ($sudahTerkirim)
                            <x-badge variant="success">Terkirim</x-badge>
                        @endif
                        <x-icon-button color="gray" type="button" wire:click="closeModal" title="Tutup">
                            <span class="sr-only">Tutup</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </x-icon-button>
                    </div>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">
                    @include('pages.transaksi.rj.emr-rj.rujukan-kompetensi.rm-rujukan-kompetensi-rj-prasyarat')

                    @if ($sudahTerkirim)
                        @include('pages.transaksi.rj.emr-rj.rujukan-kompetensi.rm-rujukan-kompetensi-rj-hasil')
                    @else
                        @include('pages.transaksi.rj.emr-rj.rujukan-kompetensi.rm-rujukan-kompetensi-rj-isian')
                    @endif

                    @include('pages.transaksi.rj.emr-rj.rujukan-kompetensi.rm-rujukan-kompetensi-rj-respons')
                </div>
            </div>

            @include('pages.transaksi.rj.emr-rj.rujukan-kompetensi.rm-rujukan-kompetensi-rj-footer')

        </div>
    </x-modal>
</div>
