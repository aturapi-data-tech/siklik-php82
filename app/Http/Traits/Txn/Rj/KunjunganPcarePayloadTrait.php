<?php

namespace App\Http\Traits\Txn\Rj;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Perakit payload kunjungan PCare (endpoint `kunjungan` maupun
 * `Sisrute/postKunjungan`) untuk satu kunjungan Rawat Jalan.
 *
 * Dipindah dari ⚡daftar-rj-actions supaya DUA pemakai memakai bentuk yang sama:
 *   - Daftar Kunjungan RJ  → kunjungan biasa (PcareTrait::addKunjungan)
 *   - Panel Rujukan Kompetensi (EMR, tab Tindak Lanjut) → kunjungan + rujukLanjut
 *     + satuSehatRujukan lewat Sisrute/postKunjungan
 *
 * Dua jalur itu mengirim ISI KUNJUNGAN YANG SAMA (tanda vital, diagnosa, terapi,
 * alergi, prognosa). Menyalinnya ke panel berarti satu hari nanti keduanya
 * berbeda diam-diam; karena itu dipusatkan di sini.
 *
 * Pemanggil boleh menyuplai data yang sudah dipegangnya ($dataRJ/$dataPasien)
 * supaya tidak ada query ulang; bila tidak, trait ini membacanya sendiri —
 * sehingga panel tak perlu ikut memakai MasterPasienTrait.
 */
trait KunjunganPcarePayloadTrait
{
    /**
     * @param  string      $rjNo        nomor kunjungan RJ
     * @param  array|null  $dataRJ      isi node JSON kunjungan; null = baca sendiri
     * @param  array|null  $dataPasien  hasil MasterPasienTrait::getMasterPasien; null = baca sendiri
     * @return array|null  null bila prasyarat kurang (sudah diberi toast)
     */
    protected function buildKunjunganPayload(string $rjNo, ?array $dataRJ = null, ?array $dataPasien = null): ?array
    {
        $dataRJ = $dataRJ ?? ($this->dataDaftarPoliRJ ?? []);
        if (empty($dataRJ) || (string) ($dataRJ['rjNo'] ?? '') !== (string) $rjNo) {
            $dataRJ = $this->findDataRJ($rjNo);
        }
        if (empty($dataRJ)) {
            return null;
        }

        $diagnosa = $dataRJ['diagnosis'] ?? [];
        $kdDiag1 = $diagnosa[0]['icdX'] ?? '';
        $kdDiag2 = $diagnosa[1]['icdX'] ?? null;
        $kdDiag3 = $diagnosa[2]['icdX'] ?? null;

        if (!$kdDiag1) {
            $this->dispatch('toast', type: 'warning', message: 'Diagnosa primer wajib diisi sebelum kirim Kunjungan.', title: 'Diagnosa Belum');
            return null;
        }

        $pf = $dataRJ['pemeriksaanFisik'] ?? ($dataRJ['tandaVital'] ?? []);
        $perencanaan = $dataRJ['perencanaan'] ?? [];
        $anamnesa = $dataRJ['anamnesa'] ?? [];

        $rjDate = Carbon::createFromFormat('d/m/Y H:i:s', $dataRJ['rjDate']);
        $noKartu = $this->noKartuBpjsKunjungan($dataRJ, $dataPasien);

        return [
            'noKunjungan' => 'RJ-' . $rjNo,
            'noKartu' => $noKartu,
            'tglDaftar' => $rjDate->format('d-m-Y'),
            'kdPoli' => $dataRJ['kdpolibpjs'] ?? '',
            'keluhan' => $anamnesa['keluhanUtama'] ?? '-',
            'kdSadar' => $pf['kdSadar'] ?? '01',
            'sistole' => (int) ($pf['sistole'] ?? 0),
            'diastole' => (int) ($pf['diastole'] ?? 0),
            'beratBadan' => (int) ($pf['beratBadan'] ?? 0),
            'tinggiBadan' => (int) ($pf['tinggiBadan'] ?? 0),
            'respRate' => (int) ($pf['rr'] ?? ($pf['respirasi'] ?? 0)),
            'heartRate' => (int) ($pf['nadi'] ?? 0),
            'lingkarPerut' => (int) ($pf['lingkarPerut'] ?? 0),
            'kdStatusPulang' => $perencanaan['kdStatusPulang'] ?? '4',
            'tglPulang' => Carbon::now()->format('d-m-Y'),
            'kdDokter' => $dataRJ['kddrbpjs'] ?? '',
            'kdDiag1' => $kdDiag1,
            'kdDiag2' => $kdDiag2,
            'kdDiag3' => $kdDiag3,
            'kdPoliRujukInternal' => null,
            'rujukLanjut' => null,
            'kdTacc' => -1,
            'alasanTacc' => '',
            'anamnesa' => $anamnesa['anamnesa'] ?? ($anamnesa['keluhanUtama'] ?? '-'),
            'alergiMakan' => $anamnesa['alergi']['alergiMakan'] ?? ($anamnesa['alergiMakan'] ?? '00'),
            'alergiUdara' => $anamnesa['alergi']['alergiUdara'] ?? ($anamnesa['alergiUdara'] ?? '00'),
            'alergiObat' => $anamnesa['alergi']['alergiObat'] ?? ($anamnesa['alergiObat'] ?? '00'),
            'kdPrognosa' => $perencanaan['kdPrognosa'] ?? '01',
            'terapiObat' => $perencanaan['terapiObat'] ?? '-',
            'terapiNonObat' => $perencanaan['terapiNonObat'] ?? '',
            'bmhp' => $perencanaan['bmhp'] ?? '',
            'suhu' => (string) ($pf['suhu'] ?? '36.5'),
        ];
    }

    /**
     * Nomor kartu BPJS 13 digit. Diambil dari data pasien yang sudah dipegang
     * pemanggil; kalau tidak ada, dibaca langsung dari master supaya panel
     * rujukan tidak perlu ikut memuat MasterPasienTrait.
     */
    private function noKartuBpjsKunjungan(array $dataRJ, ?array $dataPasien): string
    {
        $dataPasien = $dataPasien ?? ($this->dataPasien ?? []);
        $noKartu = $dataPasien['pasien']['identitas']['nokartuBpjs'] ?? '';

        if ($noKartu === '') {
            $regNo = (string) ($dataRJ['regNo'] ?? '');
            if ($regNo !== '') {
                $noKartu = (string) (DB::table('skmst_pasiens')->where('reg_no', $regNo)->value('nokartu_bpjs') ?? '');
            }
        }

        return (string) preg_replace('/\D/', '', (string) $noKartu);
    }
}
