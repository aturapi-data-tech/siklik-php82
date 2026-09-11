<?php
// resources/views/pages/components/modul-dokumen/rj/surat-rujukan/cetak-surat-rujukan-rj.blade.php
//
// Cetak SURAT PENGANTAR RUJUKAN + RESUME KLINIS PASIEN RUJUKAN (format Kemkes,
// Permenkes 16/2024 Pasal 17) untuk jalur FKTP: Rawat Jalan → FKRTL lewat
// PCare-SISRUTE. Komponen HEADLESS — tidak punya tampilan sendiri, hanya
// mendengar event dan membalas dengan unduhan PDF.
//
// Pemanggilan:
//     $this->dispatch('cetak-surat-rujukan-rj.open', rjNo: $rjNo);
//
// Sumber data: node `rujukanKompetensi` di CLOB sktxn_rjhdrs.datadaftarpolirj_json
// (diisi panel EMR Tindak Lanjut) + identitas pasien/dokter/poli/diagnosa/anamnesa/
// pemeriksaan/terapi dari node EMR yang sama, lewat EmrRJTrait & MasterPasienTrait.
//
// Surat hanya boleh terbit bila `hasil.noRujukanSatuSehat` sudah terisi — nomor itu
// penanda rujukan benar-benar diterbitkan pusat. Draft (isian tersimpan tapi belum
// terkirim) ditolak dengan toast.

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Support\TtdUser;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait;

    public ?string $rjNo = null;

    #[On('cetak-surat-rujukan-rj.open')]
    public function open(string $rjNo): mixed
    {
        $this->rjNo = $rjNo;

        $dataRJ = $this->findDataRJ($rjNo);
        if (empty($dataRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
            return null;
        }

        $rujukan = $dataRJ['rujukanKompetensi'] ?? [];
        $hasil = $rujukan['hasil'] ?? [];

        if (trim((string) ($hasil['noRujukanSatuSehat'] ?? '')) === '') {
            $this->dispatch(
                'toast',
                type: 'error',
                message: 'Rujukan belum terkirim — surat pengantar baru bisa dicetak setelah nomor Rujukan SATUSEHAT terbit.',
            );
            return null;
        }

        $pasienData = $this->findDataMasterPasien($dataRJ['regNo'] ?? '');
        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        $data = $this->susunData($dataRJ, $pasienData['pasien'] ?? [], $rujukan, $hasil);

        set_time_limit(300);

        $pdf = Pdf::loadView(
            'pages.components.modul-dokumen.rj.surat-rujukan.cetak-surat-rujukan-rj-print',
            ['data' => $data],
        )->setPaper('A4');

        $namaBerkas = 'surat-rujukan-rj-' . ($dataRJ['regNo'] ?? $rjNo) . '-' . $hasil['noRujukanSatuSehat'] . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $namaBerkas);
    }

    /* ══════════════════════════════════════════════
     | Penyusunan payload cetak — blade print hanya
     | menampilkan, semua pengolahan selesai di sini.
     ══════════════════════════════════════════════ */
    private function susunData(array $dataRJ, array $pasien, array $rujukan, array $hasil): array
    {
        $identitas = $pasien['identitas'] ?? [];
        $tandaVital = $dataRJ['pemeriksaan']['tandaVital'] ?? [];

        $identitasKlinik = DB::table('skmst_identitases')
            ->select('int_name', 'int_phone1', 'int_fax', 'int_address', 'int_city')
            ->first();

        $klaimDesc = DB::table('skmst_klaimtypes')
            ->where('klaim_id', $dataRJ['klaimId'] ?? '')
            ->value('klaim_desc');

        // TTD dokter: myuser_code pada tabel users bernilai sama dengan dr_id
        // (lihat docs/ttd-pattern-pdf-print.md §6). Pengirim rujukan didahulukan
        // bila panel menyimpan kodenya.
        $kodeTtd = trim((string) ($hasil['dikirimOlehCode'] ?? '')) !== ''
            ? (string) $hasil['dikirimOlehCode']
            : (string) ($dataRJ['drId'] ?? '');

        $sistolik = trim((string) ($tandaVital['sistolik'] ?? ''));
        $distolik = trim((string) ($tandaVital['distolik'] ?? ''));

        $alamatPasien = trim(
            (string) ($identitas['alamat'] ?? '') .
                (!empty($identitas['rt']) ? ' RT ' . $identitas['rt'] : '') .
                (!empty($identitas['rw']) ? '/RW ' . $identitas['rw'] : '') .
                (!empty($identitas['desaName']) ? ', ' . $identitas['desaName'] : '') .
                (!empty($identitas['kecamatanName']) ? ', ' . $identitas['kecamatanName'] : '') .
                (!empty($identitas['kotaName']) ? ', ' . $identitas['kotaName'] : ''),
            ' ,',
        );

        return [
            'noRujukanSatuSehat' => (string) ($hasil['noRujukanSatuSehat'] ?? ''),
            'noRujukanPcare' => (string) ($hasil['noRujukanPcare'] ?? ''),
            'noKunjunganPcare' => (string) ($hasil['noKunjunganPcare'] ?? ''),
            'serviceRequestId' => (string) ($hasil['serviceRequestId'] ?? ''),
            'tanggal' => $this->tanggalSurat($hasil),
            'estimasiRujuk' => (string) ($rujukan['estimasiRujuk'] ?? ''),

            'perujuk' => [
                'nama' => (string) ($identitasKlinik->int_name ?? 'Klinik Madinah Pratama'),
                'kode' => $this->kodeRegisterPerujuk(),
                'alamat' => (string) ($identitasKlinik->int_address ?? ''),
                'kota' => (string) ($identitasKlinik->int_city ?? 'Tulungagung'),
                'telp' => (string) ($identitasKlinik->int_phone1 ?? ''),
            ],
            'tujuan' => [
                'nama' => (string) ($hasil['tujuanNama'] ?? ''),
                'kode' => $this->kodeRegisterTujuan($hasil),
                'strata' => (string) ($hasil['tujuanStrata'] ?? ''),
                'alamat' => (string) ($hasil['tujuanAlamat'] ?? ''),
            ],
            'layanan' => [
                'spesialis' => trim((string) ($rujukan['namaSpesialis'] ?? '')),
                'subSpesialis' => $this->gabung($rujukan['kodeSubSpesialis'] ?? '', $rujukan['namaSubSpesialis'] ?? ''),
                'sarana' => $this->gabung($rujukan['kodeSarana'] ?? '', $rujukan['namaSarana'] ?? ''),
            ],

            'pasien' => [
                'rm' => (string) ($dataRJ['regNo'] ?? ''),
                'nama' => (string) ($pasien['regName'] ?? ($dataRJ['regName'] ?? '')),
                'nik' => (string) ($identitas['nik'] ?? ''),
                'umur' => $this->umur($pasien),
                'tempatLahir' => (string) ($pasien['tempatLahir'] ?? ''),
                'tglLahir' => (string) ($pasien['tglLahir'] ?? ''),
                'jenisKelamin' => (string) ($pasien['jenisKelamin']['jenisKelaminDesc'] ?? ''),
                'alamat' => $alamatPasien,
                'jenisJaminan' => (string) ($klaimDesc ?? ($dataRJ['klaimStatus'] ?? '')),
                'nomorJaminan' => (string) ($identitas['idbpjs'] ?? ''),
            ],

            'diagnosaSementara' => $this->gabung($rujukan['kodeDiagnosa'] ?? '', $rujukan['diagnosaDesc'] ?? ''),
            'terapiDiberikan' => implode("\n", $this->daftarTerapi($dataRJ)),
            'poliAsal' => (string) ($dataRJ['poliDesc'] ?? ''),
            'tglKunjungan' => (string) ($dataRJ['rjDate'] ?? ''),
            'dpjp' => (string) ($dataRJ['drDesc'] ?? ''),
            'ttdDokterPath' => TtdUser::pathBerkasDariKode($kodeTtd),

            'resume' => [
                'keluhanUtama' => (string) ($dataRJ['anamnesa']['keluhanUtama']['keluhanUtama'] ?? ''),
                'riwayatPenyakit' => (string) ($dataRJ['anamnesa']['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] ?? ''),
                'alergi' => (string) ($dataRJ['anamnesa']['alergi']['alergi'] ?? ''),
                'keadaanUmum' => (string) ($tandaVital['keadaanUmum'] ?? ''),
                'kesadaran' => (string) ($tandaVital['tingkatKesadaran'] ?? ''),
                'ttv' => [
                    'tensi' => $sistolik !== '' || $distolik !== '' ? trim($sistolik . '/' . $distolik) . ' mmHg' : '',
                    'nadi' => $this->satuan($tandaVital['frekuensiNadi'] ?? '', 'x/mnt'),
                    'suhu' => $this->satuan($tandaVital['suhu'] ?? '', '°C'),
                    'nafas' => $this->satuan($tandaVital['frekuensiNafas'] ?? '', 'x/mnt'),
                    'spo2' => $this->satuan($tandaVital['spo2'] ?? '', '%'),
                ],
                'kelainan' => trim((string) ($dataRJ['pemeriksaan']['fisik'] ?? '')),
                'penunjang' => trim((string) ($dataRJ['pemeriksaan']['penunjang'] ?? '')),
                'diagnosa' => $this->daftarDiagnosa($dataRJ, $rujukan),
                'kriteria' => $this->daftarKriteria($rujukan),
                'tindakan' => $this->daftarTindakan($dataRJ),
                'terapi' => $this->daftarTerapi($dataRJ),
                'alasan' => trim((string) ($rujukan['catatan'] ?? '')),
            ],

            'tglCetak' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i'),
        ];
    }

    /** Tanggal surat: tglRujukan node ("d/m/Y H:i:s"); jatuh ke waktu kirim. */
    private function tanggalSurat(array $hasil): string
    {
        $tanggal = trim((string) ($hasil['tglRujukan'] ?? ''));
        if ($tanggal === '') {
            $tanggal = trim((string) ($hasil['dikirimPada'] ?? ''));
        }

        return trim(explode(' ', $tanggal)[0]);
    }

    /** Kode register perujuk: kdppk PCare + id organisasi SATUSEHAT klinik. */
    private function kodeRegisterPerujuk(): string
    {
        return collect([config('bpjs.pcare.provider'), config('satusehat.organization_id')])
            ->map(fn($kode) => trim((string) $kode))
            ->filter()
            ->implode(' / ');
    }

    /** Kode register tujuan: kdppk BPJS + id organisasi SATUSEHAT faskes tujuan. */
    private function kodeRegisterTujuan(array $hasil): string
    {
        return collect([$hasil['tujuanPpk'] ?? null, $hasil['tujuanSatuSehat'] ?? null])
            ->map(fn($kode) => trim((string) $kode))
            ->filter()
            ->implode(' / ');
    }

    /** "kode — nama", toleran bila salah satunya kosong. */
    private function gabung(mixed $kode, mixed $nama): string
    {
        $kode = trim((string) $kode);
        $nama = trim((string) $nama);

        if ($kode !== '' && $nama !== '') {
            return $kode . ' — ' . $nama;
        }

        return $kode !== '' ? $kode : $nama;
    }

    private function umur(array $pasien): string
    {
        $tglLahir = trim((string) ($pasien['tglLahir'] ?? ''));
        if ($tglLahir === '') {
            return '';
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $tglLahir)
                ->diff(Carbon::now(config('app.timezone')))
                ->format('%y Th %m Bl %d Hr');
        } catch (\Throwable) {
            return '';
        }
    }

    private function satuan(mixed $nilai, string $satuan): string
    {
        $nilai = trim((string) $nilai);

        return $nilai === '' ? '' : $nilai . ' ' . $satuan;
    }

    /**
     * Diagnosa EMR: icdX bila ada, jatuh ke diagId (lihat skill diagnosa-flow).
     * Bila EMR belum mencatat diagnosa, pakai diagnosa yang dikirim bersama rujukan
     * supaya halaman 1 dan halaman 2 tidak saling bertentangan.
     */
    private function daftarDiagnosa(array $dataRJ, array $rujukan): array
    {
        $daftar = collect($dataRJ['diagnosis'] ?? [])
            ->map(function ($baris) {
                $kode = trim((string) ($baris['icdX'] ?? ($baris['diagId'] ?? '')));
                $desc = trim((string) ($baris['diagDesc'] ?? ''));

                return trim($kode . ' ' . $desc);
            })
            ->filter()->values()->all();

        if ($daftar !== []) {
            return $daftar;
        }

        $diagnosaRujukan = $this->gabung($rujukan['kodeDiagnosa'] ?? '', $rujukan['diagnosaDesc'] ?? '');

        return $diagnosaRujukan === '' ? [] : [$diagnosaRujukan];
    }

    private function daftarTindakan(array $dataRJ): array
    {
        return collect($dataRJ['procedure'] ?? [])
            ->map(function ($baris) {
                $kode = trim((string) ($baris['procedureId'] ?? ''));
                $desc = trim((string) ($baris['procedureDesc'] ?? ''));

                return trim($kode . ' ' . $desc);
            })
            ->filter()->values()->all();
    }

    /**
     * Terapi diambil dari e-resep (terstruktur). Bila kosong, teks bebas
     * perencanaan.terapi dipecah per baris — di situ resep ditulis "R/ ..." per baris.
     */
    private function daftarTerapi(array $dataRJ): array
    {
        $eresep = collect($dataRJ['eresep'] ?? [])
            ->map(function ($obat) {
                $nama = trim((string) ($obat['productName'] ?? ''));
                if ($nama === '') {
                    return '';
                }

                // Bentuk signa mengikuti cetak e-resep: "S 1dd1".
                $signaX = trim((string) ($obat['signaX'] ?? ''));
                $signaHari = trim((string) ($obat['signaHari'] ?? ''));
                $signa = $signaX !== '' || $signaHari !== ''
                    ? 'S ' . ($signaX ?: '-') . 'dd' . ($signaHari ?: '-')
                    : '';
                $qty = trim((string) ($obat['qty'] ?? ''));

                return trim($nama . ($qty !== '' ? ' | No. ' . $qty : '') . ($signa !== '' ? ' | ' . $signa : ''));
            })
            ->filter()->values()->all();

        if ($eresep !== []) {
            return $eresep;
        }

        return collect(preg_split('/\r\n|\r|\n/', (string) ($dataRJ['perencanaan']['terapi']['terapi'] ?? '')))
            ->map(fn($baris) => trim($baris))
            ->filter()->values()->all();
    }

    /**
     * Kriteria rujukan yang BENAR-BENAR dikirim: TEPAT SATU item terpilih dari
     * kriteriaList (linkId dinamis per ICD-10). Untuk "Tindakan Medis" jawabannya
     * berupa ICD-9-CM, jadi kodenya ikut dicetak.
     */
    private function daftarKriteria(array $rujukan): array
    {
        $icd9 = trim((string) ($rujukan['kriteriaIcd9'] ?? ''));
        $icd9Desc = trim((string) ($rujukan['kriteriaIcd9Desc'] ?? ''));
        $keteranganIcd9 = $icd9 !== '' ? ' — ' . trim($icd9 . ' ' . $icd9Desc) : '';

        $terpilih = collect($rujukan['kriteriaList'] ?? [])
            ->firstWhere('linkId', $rujukan['kriteriaPilih'] ?? null);

        $teks = trim((string) ($terpilih['text'] ?? ''));

        return $teks === '' ? [] : [$teks . $keteranganIcd9];
    }
};
?>
<div></div>
