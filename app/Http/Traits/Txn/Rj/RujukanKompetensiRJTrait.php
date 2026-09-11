<?php

namespace App\Http\Traits\Txn\Rj;

use App\Support\Rujukan\RujukanKompetensiOptions;
use App\Support\Rujukan\RujukanKompetensiTampil;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bagian NON-UI panel Rujukan Berbasis Kompetensi Rawat Jalan:
 * bentuk node, prasyarat, referensi PCare, dan persist.
 *
 * Dipisah dari komponen Volt-nya supaya berkas panel tetap terbaca (aturan repo:
 * satu berkas blade ≤ 700 baris) — bukan karena akan dipakai panel lain. Semua
 * method di sini mengandalkan properti komponen: $rjNo, $dataDaftarPoliRJ,
 * $formRujukan, $spesialisList, $subSpesialisList, $saranaList, $infoKandidat,
 * $responsJudul, $responsMentah.
 *
 * Dipakai bersama EmrRJTrait (findDataRJ/lockRJRow/updateJsonRJ/appendAdminLogRJ),
 * PcareTrait (referensi spesialis/subspesialis/sarana), dan PcareSisruteTrait.
 * Aturan payload-nya ada di skill `rujukan-kompetensi` — baca sebelum mengubah.
 */
trait RujukanKompetensiRJTrait
{
    /* ═══════════════════ BENTUK NODE ═══════════════════ */

    /**
     * Bentuk baku node `rujukanKompetensi`. Node tersimpan selalu di-merge di ATAS
     * bentuk ini, sehingga kunjungan lama tidak kehilangan key yang baru ditambah.
     */
    protected function defaultFormRujukan(): array
    {
        return [
            'kodeDiagnosa' => '',
            'diagnosaDesc' => '',
            'encounterId' => '',

            // Kriteria — linkId DINAMIS per ICD-10, selalu dari server, jangan dihardcode
            'kriteriaList' => [],
            'kriteriaSumber' => '',
            'kriteriaPilih' => '',
            'kriteriaIcd9' => '',
            'kriteriaIcd9Desc' => '',

            // Jejaring wilayah — pilihannya ikut datang bersama kriteria
            'wilayahList' => [],
            'kabupatenList' => [],
            'kodePropinsi' => '',
            'namaPropinsi' => '',
            'kodeKabupaten' => '',
            'namaKabupaten' => '',

            // Tujuan layanan
            'kodeSpesialis' => '',
            'namaSpesialis' => '',
            'kodeSubSpesialis' => '',
            'namaSubSpesialis' => '',
            'kodeSarana' => '',
            'namaSarana' => '',

            'estimasiRujuk' => Carbon::now(config('app.timezone'))->format(RujukanKompetensiOptions::FORMAT_TANGGAL_TAMPIL),
            'catatan' => '',

            'kandidatList' => [],
            'kandidatIdx' => null,

            'hasil' => [],
            'dibatalkan' => null,

            // Respons mentah postKunjungan terakhir — dibaca layar pemantauan &
            // cetak sebagai bukti kiriman. Sudah dipotong 8.000 karakter oleh
            // catatRespons() supaya CLOB kunjungan tidak menggelembung.
            'responMentah' => '',
        ];
    }

    public function sudahTerkirim(): bool
    {
        return trim((string) ($this->formRujukan['hasil']['noRujukanSatuSehat'] ?? '')) !== '';
    }

    /**
     * Kandidat dihitung dari kombinasi isian; begitu salah satunya berubah, daftar
     * lama menyesatkan — nama faskesnya masih tampil padahal sudah tak relevan.
     */
    protected function lupakanKandidat(): void
    {
        $this->formRujukan['kandidatList'] = [];
        $this->formRujukan['kandidatIdx'] = null;
        $this->infoKandidat = '';
    }

    /* ═══════════════════ IDENTITAS KUNJUNGAN ═══════════════════ */

    /**
     * Diagnosa yang dikirim ke BPJS = diagnosa UTAMA (kategoriDiagnosa 'Primary').
     * Kalau kategorinya belum pernah diisi (data lama), entri pertama yang dipakai.
     */
    protected function diagnosaUtama(): array
    {
        $diagnosis = collect($this->dataDaftarPoliRJ['diagnosis'] ?? []);

        return (array) ($diagnosis->firstWhere('kategoriDiagnosa', 'Primary') ?? $diagnosis->first() ?? []);
    }

    public function encounterUuid(): string
    {
        return trim((string) ($this->dataDaftarPoliRJ['satusehat']['encounterId'] ?? ''));
    }

    public function patientUuid(): string
    {
        $regNo = (string) ($this->dataDaftarPoliRJ['regNo'] ?? '');
        if ($regNo === '') {
            return '';
        }

        return (string) (DB::table('skmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '');
    }

    public function dokterUuid(): string
    {
        $drId = (string) ($this->dataDaftarPoliRJ['drId'] ?? '');
        if ($drId === '') {
            return '';
        }

        return (string) (DB::table('skmst_doctors')->where('dr_id', $drId)->value('dr_uuid') ?? '');
    }

    public function kodeDokterBpjs(): string
    {
        return trim((string) ($this->dataDaftarPoliRJ['kddrbpjs'] ?? ''));
    }

    /** Kode faskes klinik di SATUSEHAT, tanpa prefix "Organization/". */
    public function kodeFaskesSatuSehat(): string
    {
        return RujukanKompetensiTampil::orgIdPolos((string) config('satusehat.organization_id'));
    }

    /** Pendaftaran PCare sukses = code 200/201 di taskIdPelayanan.pcarePendaftaran. */
    protected function pendaftaranPcareTerkirim(): bool
    {
        $code = $this->dataDaftarPoliRJ['taskIdPelayanan']['pcarePendaftaran']['code'] ?? '';

        return $code == 200 || $code == 201;
    }

    /**
     * Daftar prasyarat yang BELUM terpenuhi — tampil sebagai kotak merah.
     * Kosong = siap kirim.
     *
     * @return array<int, string>
     */
    public function prasyaratKurang(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }

        $kurang = [];

        if ($this->kodeFaskesSatuSehat() === '') {
            $kurang[] = 'SATUSEHAT_ORGANIZATION_ID belum diset di server';
        }
        if ($this->encounterUuid() === '') {
            $kurang[] = 'Encounter SATUSEHAT belum terkirim (Daftar kunjungan → menu Satu Sehat → Encounter)';
        }
        if ($this->patientUuid() === '') {
            $kurang[] = 'IHS Pasien (patient_uuid) kosong di Master Pasien';
        }
        if ($this->dokterUuid() === '') {
            $kurang[] = 'IHS Dokter (dr_uuid) kosong di Master Dokter';
        }
        if ($this->kodeDokterBpjs() === '') {
            $kurang[] = 'Kode Dokter BPJS (kd_dr_bpjs) kosong di Master Dokter';
        }
        if (trim((string) ($this->diagnosaUtama()['icdX'] ?? '')) === '') {
            $kurang[] = 'Diagnosa utama (ICD-10) belum dientri di EMR → tab Diagnosa';
        }
        if (!$this->pendaftaranPcareTerkirim()) {
            $kurang[] = 'Pendaftaran PCare kunjungan ini belum terkirim (Daftar kunjungan → Kirim Pendaftaran BPJS)';
        }

        return $kurang;
    }

    /* ═══════════════════ REFERENSI PCARE ═══════════════════ */

    protected function muatReferensiPcare(): void
    {
        $this->spesialisList = $this->referensiPcare('Spesialis', ['kdSpesialis'], ['nmSpesialis'], fn() => $this->getSpesialis());
        $this->saranaList = $this->referensiPcare('Sarana', ['kdSarana'], ['nmSarana'], fn() => $this->getSarana());

        if (trim((string) $this->formRujukan['kodeSpesialis']) !== '') {
            $this->muatSubSpesialis();
        }
    }

    /**
     * Referensi PCare dari cache lokal ref_bpjs_table dulu (di-sync lewat Master →
     * Ref BPJS); baru memanggil BPJS bila cache kosong. Daftar Sarana saja 40 KB —
     * menariknya tiap kali modal dibuka membebani gateway tanpa guna.
     */
    private function referensiPcare(string $keterangan, array $kunciKode, array $kunciNama, callable $pemanggilApi): array
    {
        $json = DB::table('ref_bpjs_table')
            ->whereRaw('upper(ref_keterangan) = upper(?)', [$keterangan])
            ->value('ref_json');

        $list = $json ? json_decode((string) $json, true) : null;

        if (!is_array($list) || $list === []) {
            $respon = $this->responFromPcare($pemanggilApi);
            $list = $respon['response']['list'] ?? ($respon['response'] ?? []);
        }

        return $this->opsiKodeNama(is_array($list) ? $list : [], $kunciKode, $kunciNama);
    }

    public function muatSubSpesialis(): void
    {
        $kodeSpesialis = trim((string) $this->formRujukan['kodeSpesialis']);
        if ($kodeSpesialis === '') {
            $this->subSpesialisList = [];
            return;
        }

        $respon = $this->responFromPcare(fn() => $this->getReferensiSubSpesialis($kodeSpesialis));
        $list = $respon['response']['list'] ?? ($respon['response'] ?? []);

        // Ejaan kunci pernah berbeda antar rilis gateway (kdSubSpesialis vs kdSubspesialis).
        $this->subSpesialisList = $this->opsiKodeNama(
            is_array($list) ? $list : [],
            ['kdSubSpesialis', 'kdSubspesialis'],
            ['nmSubSpesialis', 'nmSubspesialis'],
        );

        if ($this->subSpesialisList === []) {
            $this->dispatch('toast', type: 'warning', message: 'Subspesialis untuk spesialis ini tidak terbaca dari PCare — coba lagi atau pilih spesialis lain.');
        }
    }

    /** Ratakan daftar referensi BPJS ke bentuk [['kd' => …, 'nm' => …], …]. */
    private function opsiKodeNama(array $list, array $kunciKode, array $kunciNama): array
    {
        $ambil = function (array $item, array $kunci): string {
            foreach ($kunci as $nama) {
                if (isset($item[$nama]) && (string) $item[$nama] !== '') {
                    return (string) $item[$nama];
                }
            }

            return '';
        };

        return collect($list)
            ->map(fn($item) => [
                'kd' => $ambil((array) $item, $kunciKode),
                'nm' => $ambil((array) $item, $kunciNama),
            ])
            ->filter(fn($opsi) => $opsi['kd'] !== '')
            ->values()
            ->all();
    }

    /** PcareTrait mengembalikan response()->json — isinya diambil lewat getOriginalContent(). */
    private function responFromPcare(callable $pemanggil): array
    {
        try {
            $hasil = $pemanggil();

            return is_object($hasil) && method_exists($hasil, 'getOriginalContent')
                ? (array) $hasil->getOriginalContent()
                : (array) $hasil;
        } catch (\Throwable $e) {
            return ['metadata' => ['code' => 500, 'message' => $e->getMessage()], 'response' => []];
        }
    }

    /* ═══════════════════ KRITERIA, WILAYAH, KANDIDAT ═══════════════════ */

    /** linkId dinamis per ICD-10 — beberapa bentuk respons pernah terpantau di lapangan. */
    protected function normalisasiKriteria(array $respon): array
    {
        $kriteria = $respon['kriteriaRujukan'] ?? ($respon['kriteria'] ?? []);
        if (isset($kriteria['item']) && is_array($kriteria['item'])) {
            $kriteria = $kriteria['item'];
        }

        return collect(is_array($kriteria) ? $kriteria : [])
            ->map(fn($item) => [
                'linkId' => (string) (((array) $item)['linkId'] ?? ''),
                'text' => (string) (((array) $item)['text'] ?? ''),
                'type' => (string) (((array) $item)['type'] ?? 'boolean'),
            ])
            ->filter(fn($item) => $item['linkId'] !== '')
            ->values()
            ->all();
    }

    /** Kriteria yang sedang dipilih (satu elemen kriteriaList), atau null. */
    public function kriteriaTerpilih(): ?array
    {
        $terpilih = collect($this->formRujukan['kriteriaList'] ?? [])
            ->firstWhere('linkId', $this->formRujukan['kriteriaPilih'] ?? '');

        return is_array($terpilih) ? $terpilih : null;
    }

    /** Teks kriteria BPJS tidak konsisten antar rilis — pencocokannya di Options. */
    public function kriteriaButuhIcd9(?array $kriteria): bool
    {
        return $kriteria !== null && RujukanKompetensiOptions::butuhIcd9((string) ($kriteria['text'] ?? ''));
    }

    /**
     * Blok `kriteriaRujukan` yang dikirim: objek {item:[…]} berisi TEPAT SATU item.
     * Perakitannya dipusatkan di RujukanKompetensiOptions supaya aturan
     * valueBoolean vs valueString tidak ditulis ulang di dua tempat.
     */
    protected function bangunItemKriteria(): ?array
    {
        if (empty($this->formRujukan['kriteriaSumber'])) {
            $this->dispatch('toast', type: 'error', message: 'Ambil kriteria dari server dulu (Langkah 1).');
            return null;
        }

        $terpilih = $this->kriteriaTerpilih();
        if (!$terpilih) {
            $this->dispatch('toast', type: 'error', message: 'Pilih TEPAT SATU kriteria rujukan dulu. ' . RujukanKompetensiOptions::PETUNJUK_UMUM);
            return null;
        }

        $kodeIcd9 = trim((string) $this->formRujukan['kriteriaIcd9']);
        if ($this->kriteriaButuhIcd9($terpilih) && $kodeIcd9 === '') {
            $this->dispatch('toast', type: 'error', message: "Kriteria \"{$terpilih['text']}\" wajib disertai kode tindakan ICD-9-CM (ikut menentukan kandidat).");
            return null;
        }

        return RujukanKompetensiOptions::bangunKriteriaRujukan($terpilih, $kodeIcd9);
    }

    protected function codeJejaringWilayah(): array
    {
        return [
            'kodePropinsi' => (string) $this->formRujukan['kodePropinsi'],
            'namaPropinsi' => (string) $this->formRujukan['namaPropinsi'],
            'kodeKabupaten' => (string) $this->formRujukan['kodeKabupaten'],
            'namaKabupaten' => (string) $this->formRujukan['namaKabupaten'],
        ];
    }

    /** Kabupaten yang berada di propinsi terpilih (kode kabupaten berawalan kode propinsi). */
    public function kabupatenPropinsiIni(): array
    {
        $kodePropinsi = trim((string) $this->formRujukan['kodePropinsi']);
        if ($kodePropinsi === '') {
            return [];
        }

        return collect($this->formRujukan['kabupatenList'] ?? [])
            ->filter(fn($item) => (string) (((array) $item)['kodePropinsi'] ?? '') === $kodePropinsi)
            ->values()
            ->all();
    }

    /**
     * Parser tanggal WAJIB lewat checkdate(): Carbon::createFromFormat menerima
     * 31/02/2026 dan menggesernya sendiri ke 03/03 tanpa memberi tahu siapa pun.
     */
    protected function estimasiRujukCarbon(): ?Carbon
    {
        $nilai = trim((string) ($this->formRujukan['estimasiRujuk'] ?? ''));
        if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $nilai, $bagian)) {
            return null;
        }
        if (!checkdate((int) $bagian[2], (int) $bagian[1], (int) $bagian[3])) {
            return null;
        }

        return Carbon::createFromFormat(RujukanKompetensiOptions::FORMAT_TANGGAL_TAMPIL, $nilai)->startOfDay();
    }

    /**
     * Satu baris kandidat yang sudah rata. kdppk (BPJS) dan orgId (SATUSEHAT) WAJIB
     * datang dari BARIS YANG SAMA — tertukar berarti rujukan nyasar ke faskes lain.
     */
    public function kandidatBaris(?int $index): ?array
    {
        if ($index === null) {
            return null;
        }

        $mentah = $this->formRujukan['kandidatList'][$index] ?? null;

        return is_array($mentah) ? RujukanKompetensiTampil::kandidatBaris($mentah) : null;
    }

    /* ═══════════════════ PENANDA LANGKAH ═══════════════════ */

    /**
     * Keadaan tiap langkah — DIHITUNG dari data, bukan disimpan: state tersendiri
     * akan berbohong begitu kandidat/kriteria ter-reset karena isian diganti.
     *
     * Hanya TIGA langkah: jalur FKTP tidak punya tahap persetujuan faskes tujuan.
     */
    public function langkahRujukan(): array
    {
        $sudahKirim = $this->sudahTerkirim();
        $adaKandidat = ($this->formRujukan['kandidatIdx'] ?? null) !== null;

        $terpilih = $this->kriteriaTerpilih();
        $kriteriaTerisi = $terpilih !== null
            && (!$this->kriteriaButuhIcd9($terpilih) || trim((string) $this->formRujukan['kriteriaIcd9']) !== '');
        $dasarTerisi = trim((string) $this->formRujukan['kodeDiagnosa']) !== '' && $kriteriaTerisi;

        $keadaan = fn(bool $selesai, bool $aktif) => $selesai ? 'done' : ($aktif ? 'current' : 'todo');

        return [
            [
                'n' => 1,
                'title' => 'Diagnosa & Kriteria',
                'hint' => $dasarTerisi ? ($this->formRujukan['kodeDiagnosa'] ?: null) : 'ambil kriteria lalu pilih satu',
                'state' => $keadaan($dasarTerisi, true),
            ],
            [
                'n' => 2,
                'title' => 'Pilih Kandidat',
                'hint' => $adaKandidat ? ($this->kandidatBaris($this->formRujukan['kandidatIdx'])['nama'] ?? null) : 'cari lalu pilih faskes tujuan',
                'state' => $keadaan($adaKandidat, $dasarTerisi),
            ],
            [
                'n' => 3,
                'title' => 'Kirim Rujukan',
                'hint' => $sudahKirim ? 'No. ' . $this->formRujukan['hasil']['noRujukanSatuSehat'] : 'terbit nomor PCare & SATUSEHAT',
                'state' => $keadaan($sudahKirim, $adaKandidat),
            ],
        ];
    }

    /* ═══════════════════ PERSIST & PESAN ═══════════════════ */

    /**
     * Simpan node `rujukanKompetensi` ke CLOB kunjungan.
     *
     * @param  string|null  $catatanAudit  isi log Rekam Medis; null = tak dicatat
     * @param  bool  $lemparGalat  true dipakai SESUDAH rujukan terbit: kegagalan
     *                             simpan di titik itu berarti nomor rujukan bisa
     *                             hilang dari berkas walau sudah ada di BPJS, jadi
     *                             tidak boleh lewat sebagai toast yang terlewat.
     */
    protected function simpanNode(?string $catatanAudit = null, bool $lemparGalat = false): void
    {
        if (empty($this->rjNo)) {
            return;
        }

        try {
            DB::transaction(function () use ($catatanAudit) {
                $this->lockRJRow($this->rjNo);

                // Baca ulang SEGAR di dalam lock: tab EMR lain (diagnosa, terapi)
                // bisa menulis CLOB yang sama sementara panel ini terbuka.
                $data = $this->findDataRJ($this->rjNo);
                if (empty($data)) {
                    return;
                }

                $data['rujukanKompetensi'] = $this->formRujukan;
                $this->updateJsonRJ((int) $this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;

                if ($catatanAudit) {
                    $this->appendAdminLogRJ((int) $this->rjNo, $catatanAudit, 'MR');
                }
            });
        } catch (\Throwable $e) {
            if ($lemparGalat) {
                throw $e;
            }
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan isian rujukan: ' . $e->getMessage());
        }
    }

    /** Simpan respons mentah panggilan terakhir — bahan lapor saat pusat bermasalah. */
    protected function catatRespons(string $judul, array $hasil): void
    {
        $this->responsJudul = $judul . ' · ' . Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s')
            . ' · code ' . (string) ($hasil['code'] ?? '-');

        $mentah = (string) ($hasil['raw'] ?? '');
        if ($mentah === '') {
            $mentah = json_encode($hasil['response'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        // Dipotong: respons kandidat bisa puluhan KB dan ikut menggelembungkan
        // payload Livewire pada SETIAP render berikutnya.
        $this->responsMentah = mb_substr($mentah, 0, 8000);
    }

    /** Galat gateway → toast + petunjuk tindakan dari katalog error PcareSisruteTrait. */
    protected function toastGagal(string $judul, array $hasil): void
    {
        $pesan = trim((string) ($hasil['message'] ?? 'Gangguan tidak dikenal'));
        $petunjuk = trim((string) $this->sisruteHintKatalog($pesan));

        $this->dispatch(
            'toast',
            type: 'error',
            title: $judul,
            message: '[' . (string) ($hasil['code'] ?? '-') . '] ' . $pesan . ($petunjuk !== '' ? ' — ' . $petunjuk : ''),
            duration: 12000,
        );
    }
}
