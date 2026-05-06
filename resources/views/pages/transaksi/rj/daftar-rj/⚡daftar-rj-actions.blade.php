<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\BPJS\PcareTrait;
use App\Http\Traits\BPJS\AntrianTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait, WithRenderVersioningTrait, PcareTrait, AntrianTrait;

    public string $formMode = 'create';
    public bool $isFormLocked = false;

    public ?string $rjNo = null;
    public array $dataDaftarPoliRJ = ['passStatus' => 'O'];
    public array $dataPasien = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal', 'pasien', 'dokter'];

    public string $klaimId = 'UM';
    public array $klaimOptions = [['klaimId' => 'UM', 'klaimDesc' => 'UMUM'], ['klaimId' => 'JM', 'klaimDesc' => 'BPJS'], ['klaimId' => 'JR', 'klaimDesc' => 'JASA RAHARJA'], ['klaimId' => 'JML', 'klaimDesc' => 'Asuransi Lain']];

    /* -------------------------
     | BPJS PCare klinik pratama: Kunjungan Sakit/Sehat & Tkp
     * ------------------------- */
    public string $kunjSakit = '1'; // 1=Sakit (default), 0=Sehat
    public array $kunjSakitOptions = [['kunjSakitId' => '1', 'kunjSakitDesc' => 'Kunjungan Sakit'], ['kunjSakitId' => '0', 'kunjSakitDesc' => 'Kunjungan Sehat']];

    public string $kdTkp = '10'; // 10=RJTP (default), 50=Promotif
    public array $kdTkpOptions = [['kdTkpId' => '10', 'kdTkpDesc' => 'RJTP'], ['kdTkpId' => '50', 'kdTkpDesc' => 'Promotif']];

    /* -------------------------
     | Riwayat Kunjungan BPJS state
     * ------------------------- */
    public bool $showRiwayatBpjs = false;
    public array $riwayatBpjsList = [];
    public string $riwayatBpjsTitle = '';

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal', 'pasien', 'dokter', 'satu-sehat']);
    }

    /* ===============================
     | OPEN CREATE
     =============================== */
    #[On('daftar-rj.create.open')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode = 'create';
        $this->resetValidation();

        $this->dataDaftarPoliRJ = $this->getDefaultRJTemplate();

        $now = Carbon::now();
        $this->dataDaftarPoliRJ['rjDate'] = $now->format('d/m/Y H:i:s');
        $this->dataDaftarPoliRJ['shift'] = $this->resolveShiftByTime($now->format('H:i:s'));

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'rj-actions');
        $this->dispatch('focus-cari-pasien');
    }

    /* ===============================
     | OPEN EDIT
     =============================== */
    #[On('daftar-rj.edit.open')]
    public function openEdit(string $rjNo): void
    {
        $this->resetForm();
        $this->rjNo = $rjNo;
        $this->formMode = 'edit';
        $this->resetValidation();

        $data = $this->findDataRJ($rjNo);

        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }

        if ($this->checkRJStatus($rjNo)) {
            $this->isFormLocked = true;
            $this->dispatch('toast', type: 'warning', message: 'Data Rawat Jalan ini sudah selesai dan tidak bisa diubah.');
        }

        $this->dataDaftarPoliRJ = $data;
        $this->dataPasien = $this->findDataMasterPasien($this->dataDaftarPoliRJ['regNo'] ?? '');
        $this->syncFromDataDaftarPoliRJ();

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'rj-actions');

        if (empty($this->dataDaftarPoliRJ['regNo'])) {
            $this->dispatch('focus-cari-pasien');
        }
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rj-actions');
    }

    /* ===============================
     | SAVE
     =============================== */
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan data.');
            return;
        }

        $this->setDataPrimer();
        $this->validateDataRJ();

        $rjNo = $this->dataDaftarPoliRJ['rjNo'] ?? null;

        if (!$rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor RJ tidak valid.');
            return;
        }

        try {
            // Klinik pratama — tidak pakai BPJS Antrol (queue specialist) atau VClaim SEP.
            // Pendaftaran BPJS klinik via PCare nanti di-trigger setelah save sukses
            // (lihat PcareTrait::addPedaftaran).

            // ============================================================
            // 4. DB TRANSACTION
            // ============================================================
            $message = '';

            if ($this->formMode === 'create') {
                $drId = $this->dataDaftarPoliRJ['drId'];
                $rjDateCarbon = Carbon::createFromFormat('d/m/Y H:i:s', $this->dataDaftarPoliRJ['rjDate']);
                $lockKey = "lock:antrian:{$drId}:" . $rjDateCarbon->format('Ymd');

                Cache::lock($lockKey, 15)->block(5, function () use ($rjNo, $drId, $rjDateCarbon, &$message) {
                    DB::transaction(function () use ($rjNo, $drId, $rjDateCarbon, &$message) {
                        // Re-hitung noAntrian di dalam lock untuk cegah race condition
                        if (!empty($this->dataDaftarPoliRJ['klaimId'])) {
                            $this->dataDaftarPoliRJ['noAntrian'] = $this->hitungNoAntrian($drId, $rjDateCarbon);
                        }
                        DB::table('rstxn_rjhdrs')->insert($this->buildPayload($rjNo));
                        $this->updateJsonData($rjNo);
                        $message = 'Data Rawat Jalan berhasil disimpan.';
                    });
                });
            } else {
                DB::transaction(function () use ($rjNo, &$message) {
                    $this->lockRJRow($rjNo);
                    DB::table('rstxn_rjhdrs')->where('rj_no', $rjNo)->update($this->buildPayload($rjNo));
                    $this->updateJsonData($rjNo);
                    $message = 'Data Rawat Jalan berhasil diperbarui.';
                });
            }

            // ============================================================
            // 5. PUSH BPJS ANTREAN RS — tambah_antrean + panggil_antrean
            //    Cuma untuk mode CREATE (pendaftaran baru). Edit tidak push.
            //    Best-effort: gagal di sini gak rollback transaksi lokal.
            //    Kalau klinik belum pakai BPJS Antrean RS, env ANTRIAN_URL
            //    kosong → trait return error 408 ke web_log_status, lokal aman.
            // ============================================================
            $bpjsMsg = '';
            if ($this->formMode === 'create') {
                $bpjsMsg = $this->pushBpjsAntrean();
            }

            // ============================================================
            // 6. AFTER SAVE
            // ============================================================
            $this->afterSave($message . $bpjsMsg);
        } catch (LockTimeoutException $e) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sedang sibuk, silakan coba lagi.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }

    /* ===============================
     | PUSH BPJS ANTREAN RS  (tambah_antrean + panggil_antrean)
     |
     | Dipanggil setelah save sukses (mode create), HANYA untuk pasien BPJS
     | (klaim_id = JM). Pasien non-JKN (UM/JR/JML) di-skip — gak perlu daftar
     | ke BPJS Antrean RS.
     |
     | Pasien udah hadir di loket → langsung dikirim status Hadir setelah
     | tambah_antrean berhasil. Best-effort: gagal di sini tetep return msg
     | tanpa rollback transaksi lokal.
     =============================== */
    private function pushBpjsAntrean(): string
    {
        $data = $this->dataDaftarPoliRJ;

        // Filter: hanya pasien BPJS yang di-push
        if (($data['klaimId'] ?? '') !== 'JM') {
            return '';
        }

        $pasien = DB::table('rsmst_pasiens')->select('reg_no', 'reg_name', 'nokartu_bpjs', 'nik_bpjs', 'phone')->where('reg_no', $data['regNo'])->first();
        if (!$pasien) {
            return '';
        }

        // Pasien BPJS harus punya nokartu — kalau gak ada, skip
        if (empty($pasien->nokartu_bpjs)) {
            return ' · BPJS push dilewati (pasien BPJS tapi nokartu_bpjs kosong)';
        }

        $poli = DB::table('rsmst_polis')->select('poli_id', 'poli_desc', 'kd_poli_bpjs')->where('poli_id', $data['poliId'])->first();
        if (!$poli || empty($poli->kd_poli_bpjs)) {
            return ' · BPJS push dilewati (poli belum mapping kd_poli_bpjs)';
        }

        $doctor = DB::table('rsmst_doctors')->select('dr_id', 'dr_name', 'kd_dr_bpjs')->where('dr_id', $data['drId'])->first();
        if (!$doctor || empty($doctor->kd_dr_bpjs)) {
            return ' · BPJS push dilewati (dokter belum mapping kd_dr_bpjs)';
        }

        $rjDateCarbon = Carbon::createFromFormat('d/m/Y H:i:s', $data['rjDate']);
        $tanggalperiksa = $rjDateCarbon->format('Y-m-d');

        // jampraktek dari SCVIEW_SCPOLIS — match by dokter + poli + hari + shift
        $hariMap = [
            'Sunday' => 'MINGGU',
            'Monday' => 'SENIN',
            'Tuesday' => 'SELASA',
            'Wednesday' => 'RABU',
            'Thursday' => 'KAMIS',
            'Friday' => 'JUMAT',
            'Saturday' => 'SABTU',
        ];
        $hari = $hariMap[$rjDateCarbon->dayName] ?? null;

        $jadwal = DB::table('scview_scpolis')->select('sc_poli_ket')->where('poli_id', $poli->poli_id)->where('dr_id', $doctor->dr_id)->where('day_desc', $hari)->where('shift', $data['shift'])->where('sc_poli_status_', '1')->first();
        $jampraktek = $jadwal->sc_poli_ket ?? '';

        $noAntrian = (int) ($data['noAntrian'] ?? 0);
        $nomorAntrean = $poli->kd_poli_bpjs . '-' . $noAntrian;

        // 1) tambah_antrean
        $tambah = self::tambah_antrean([
            'nomorkartu' => $pasien->nokartu_bpjs ?? '',
            'nik' => $pasien->nik_bpjs ?? '',
            'nohp' => $pasien->phone ?? '',
            'kodepoli' => $poli->kd_poli_bpjs,
            'namapoli' => $poli->poli_desc,
            'norm' => $pasien->reg_no,
            'tanggalperiksa' => $tanggalperiksa,
            'kodedokter' => $doctor->kd_dr_bpjs,
            'namadokter' => $doctor->dr_name,
            'jampraktek' => $jampraktek,
            'nomorantrean' => $nomorAntrean,
            'angkaantrean' => $noAntrian,
            'keterangan' => 'Pendaftaran loket',
        ]);

        $msg = $tambah['ok'] ? '' : " · BPJS add: {$tambah['code']}/{$tambah['msg']}";

        // 2) panggil_antrean (status hadir) — cuma kalau tambah berhasil
        if ($tambah['ok']) {
            // Waktu = jam rjDate (pendaftaran loket = jam hadir).
            // Pakai timestamp dari form, bukan now(), supaya kalau admin save
            // beberapa menit setelah pasien dateng, BPJS tetep dapet jam yg benar.
            $waktuMs = $rjDateCarbon->timestamp * 1000;

            $panggil = self::panggil_antrean(
                $tanggalperiksa,
                $poli->kd_poli_bpjs,
                $pasien->nokartu_bpjs,
                1, // 1=Hadir
                $waktuMs,
            );
            if (!$panggil['ok']) {
                $msg .= " · BPJS panggil: {$panggil['code']}/{$panggil['msg']}";
            }
        }

        return $msg;
    }

    /* ===============================
     | BUILD PAYLOAD
     =============================== */
    private function buildPayload(string $rjNo): array
    {
        return [
            'rj_no' => $rjNo,
            'rj_date' => DB::raw("to_date('" . $this->dataDaftarPoliRJ['rjDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
            'reg_no' => $this->dataDaftarPoliRJ['regNo'],
            'nobooking' => $this->dataDaftarPoliRJ['noBooking'],
            'no_antrian' => $this->dataDaftarPoliRJ['noAntrian'],
            'klaim_id' => $this->dataDaftarPoliRJ['klaimId'],
            'poli_id' => $this->dataDaftarPoliRJ['poliId'],
            'dr_id' => $this->dataDaftarPoliRJ['drId'],
            'shift' => $this->dataDaftarPoliRJ['shift'],
            'txn_status' => $this->dataDaftarPoliRJ['txnStatus'] ?? 'A',
            'rj_status' => $this->dataDaftarPoliRJ['rjStatus'] ?? 'A',
            'erm_status' => $this->dataDaftarPoliRJ['ermStatus'] ?? 'A',
            'pass_status' => $this->dataDaftarPoliRJ['passStatus'] ?? 'O',
            'cek_lab' => $this->dataDaftarPoliRJ['cekLab'] ?? '0',
            'sl_codefrom' => $this->dataDaftarPoliRJ['slCodeFrom'] ?? '02',
            'waktu_masuk_pelayanan' => DB::raw("to_date('" . $this->dataDaftarPoliRJ['rjDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
        ];
    }

    /* ===============================
     | SET DATA PRIMER
     =============================== */
    private function setDataPrimer(): void
    {
        $data = &$this->dataDaftarPoliRJ;

        if (empty($data['noBooking'])) {
            $data['noBooking'] = Carbon::now()->format('YmdHis') . 'RSIM';
        }

        if (empty($data['rjNo'])) {
            $maxRjNo = DB::table('rstxn_rjhdrs')->max('rj_no');
            $data['rjNo'] = $maxRjNo ? $maxRjNo + 1 : 1;
        }

        if (empty($data['noAntrian'])) {
            if (!empty($data['klaimId'])) {
                if (!empty($data['rjDate']) && !empty($data['drId'])) {
                    $rjDateCarbon = Carbon::createFromFormat('d/m/Y H:i:s', $data['rjDate']);

                    $data['noAntrian'] = $this->hitungNoAntrian($data['drId'], $rjDateCarbon);
                }
            } else {
                $data['noAntrian'] = 999;
            }
        }

        $data['taskIdPelayanan'] ??= [];

        if (empty($data['taskIdPelayanan']['taskId3']) && !empty($data['rjDate'])) {
            $data['taskIdPelayanan']['taskId3'] = $data['rjDate'];
        }
    }

    /* ===============================
     | VALIDATE DATA RJ
     =============================== */
    private function validateDataRJ(): array
    {
        $attributes = [
            'dataDaftarPoliRJ.regNo' => 'Nomor Registrasi Pasien',
            'dataDaftarPoliRJ.drId' => 'ID Dokter',
            'dataDaftarPoliRJ.drDesc' => 'Nama Dokter',
            'dataDaftarPoliRJ.poliId' => 'ID Poli',
            'dataDaftarPoliRJ.poliDesc' => 'Nama Poli',
            'dataDaftarPoliRJ.rjDate' => 'Tanggal Kunjungan',
            'dataDaftarPoliRJ.rjNo' => 'Nomor Kunjungan',
            'dataDaftarPoliRJ.shift' => 'Shift',
            'dataDaftarPoliRJ.noAntrian' => 'Nomor Antrian',
            'dataDaftarPoliRJ.noBooking' => 'Nomor Booking',
            'dataDaftarPoliRJ.slCodeFrom' => 'Kode Sumber',
            'dataDaftarPoliRJ.klaimId' => 'ID Klaim',
            'dataDaftarPoliRJ.kunjSakit' => 'Kunjungan Sakit/Sehat',
            'dataDaftarPoliRJ.kdTkp' => 'Tempat Pelayanan (Tkp)',
        ];

        $rules = [
            'dataDaftarPoliRJ.regNo' => 'bail|required|exists:rsmst_pasiens,reg_no',
            'dataDaftarPoliRJ.drId' => 'required|exists:rsmst_doctors,dr_id',
            'dataDaftarPoliRJ.drDesc' => 'required|string',
            'dataDaftarPoliRJ.poliId' => 'required|exists:rsmst_polis,poli_id',
            'dataDaftarPoliRJ.poliDesc' => 'required|string',
            'dataDaftarPoliRJ.kddrbpjs' => 'nullable|string',
            'dataDaftarPoliRJ.kdpolibpjs' => 'nullable|string',
            'dataDaftarPoliRJ.rjDate' => 'required|date_format:d/m/Y H:i:s',
            'dataDaftarPoliRJ.rjNo' => 'required|numeric',
            'dataDaftarPoliRJ.shift' => 'required|in:1,2,3',
            'dataDaftarPoliRJ.noAntrian' => 'required|numeric|min:1|max:999',
            'dataDaftarPoliRJ.noBooking' => 'required|string',
            'dataDaftarPoliRJ.slCodeFrom' => 'required|in:01,02',
            'dataDaftarPoliRJ.passStatus' => 'nullable|in:N,O',
            'dataDaftarPoliRJ.rjStatus' => 'required|in:A,L,I,F',
            'dataDaftarPoliRJ.txnStatus' => 'required|in:A,L,H',
            'dataDaftarPoliRJ.ermStatus' => 'required|in:A,L',
            'dataDaftarPoliRJ.cekLab' => 'required|in:0,1',
            'dataDaftarPoliRJ.klaimId' => 'required|exists:rsmst_klaimtypes,klaim_id',
        ];

        if (($this->dataDaftarPoliRJ['klaimStatus'] ?? '') === 'BPJS' || ($this->dataDaftarPoliRJ['klaimId'] ?? '') === 'JM') {
            $rules['dataDaftarPoliRJ.kunjSakit'] = 'required|in:0,1';
            $rules['dataDaftarPoliRJ.kdTkp'] = 'required|in:10,50';
            $rules['dataDaftarPoliRJ.kdpolibpjs'] = 'required|string';
        }

        return $this->validate($rules, [], $attributes);
    }

    /* ===============================
     | UPDATE JSON DATA
     =============================== */
    private function updateJsonData(string $rjNo): void
    {
        $allowedFields = ['regNo', 'regName', 'drId', 'drDesc', 'kddrbpjs', 'poliId', 'poliDesc', 'kdpolibpjs', 'klaimId', 'klaimStatus', 'rjDate', 'shift', 'noAntrian', 'noBooking', 'slCodeFrom', 'passStatus', 'rjStatus', 'txnStatus', 'ermStatus', 'cekLab', 'taskIdPelayanan', 'kunjSakit', 'kdTkp', 'noKartu', 'noUrutBpjs'];

        if ($this->formMode === 'create') {
            $this->updateJsonRJ($rjNo, $this->dataDaftarPoliRJ);
            return;
        }

        $existingData = $this->findDataRJ($rjNo);

        if (empty($existingData)) {
            throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
        }

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $this->dataDaftarPoliRJ)) {
                $existingData[$field] = $this->dataDaftarPoliRJ[$field];
            }
        }

        $this->updateJsonRJ($rjNo, $existingData);
    }

    /* ===============================
     | AFTER SAVE
     =============================== */
    private function afterSave(string $message): void
    {
        // Jika create → switch ke edit mode, tetap di modal
        if ($this->formMode === 'create') {
            $this->formMode = 'edit';
            $this->rjNo = $this->dataDaftarPoliRJ['rjNo'];
        }

        $this->syncFromDataDaftarPoliRJ();

        $this->dispatch('toast', type: 'success', message: $message);
        $this->dispatch('refresh-after-rj.saved');

        // Kirim ke BPJS PCare kalau klaim BPJS — silent skip kalau belum lengkap
        $this->pushPendaftaranBPJS();
    }

    /* ===============================
     | PCARE — Kirim Pendaftaran ke BPJS (klinik pratama)
     |
     | Trigger setelah save sukses (dari afterSave) atau via event
     | external 'rj.pcare.push-pendaftaran' (rjNo).
     | Silent skip kalau klaim bukan BPJS atau vital signs belum lengkap.
     =============================== */
    #[On('rj.pcare.push-pendaftaran')]
    public function pushPendaftaranByRjNo(string $rjNo): void
    {
        $this->rjNo = $rjNo;
        $rjData = $this->findDataRJ($rjNo);
        if (!$rjData) {
            return;
        }
        $this->dataDaftarPoliRJ = $rjData;
        $this->dataPasien = $this->getMasterPasien($rjData['regNo'] ?? '') ?? [];
        $this->pushPendaftaranBPJS();
    }

    private function pushPendaftaranBPJS(): void
    {
        if (($this->dataDaftarPoliRJ['klaimId'] ?? '') !== 'JM') {
            return; // Bukan BPJS, skip
        }

        $rjNo = $this->dataDaftarPoliRJ['rjNo'] ?? null;
        if (!$rjNo) {
            return;
        }

        // Skip kalau sudah pernah berhasil
        $sentCode = $this->dataDaftarPoliRJ['taskIdPelayanan']['pcarePendaftaran']['code'] ?? '';
        if ($sentCode == 200 || $sentCode == 201) {
            \Log::info('PCare addPedaftaran skipped — already sent', ['rjNo' => $rjNo, 'code' => $sentCode]);
            return;
        }

        // Build vital signs dari pemeriksaanFisik (perawat) dgn fallback tandaVital
        $pf = $this->dataDaftarPoliRJ['pemeriksaanFisik'] ?? ($this->dataDaftarPoliRJ['tandaVital'] ?? []);

        $sistole = (int) ($pf['sistole'] ?? 0);
        $diastole = (int) ($pf['diastole'] ?? 0);
        $nadi = (int) ($pf['nadi'] ?? 0);
        $rr = (int) ($pf['rr'] ?? ($pf['respirasi'] ?? 0));
        $bb = (int) ($pf['beratBadan'] ?? 0);
        $tb = (int) ($pf['tinggiBadan'] ?? 0);
        $lp = (int) ($pf['lingkarPerut'] ?? 0);

        // Skip kalau vital signs belum lengkap (perawat belum input pemeriksaan)
        if ($sistole === 0 || $diastole === 0 || $nadi === 0 || $rr === 0) {
            \Log::info('PCare addPedaftaran skipped — vital signs belum lengkap', [
                'rjNo' => $rjNo,
                'sistole' => $sistole,
                'diastole' => $diastole,
                'nadi' => $nadi,
                'rr' => $rr,
            ]);
            return;
        }

        $rjDate = Carbon::createFromFormat('d/m/Y H:i:s', $this->dataDaftarPoliRJ['rjDate']);
        $noKartu = preg_replace('/\D/', '', $this->dataPasien['pasien']['identitas']['nokartuBpjs'] ?? '');
        $keluhan = $this->dataDaftarPoliRJ['anamnesa']['keluhanUtama'] ?? ($this->dataDaftarPoliRJ['anamnesa']['anamnesa'] ?? '-');

        $payload = [
            'kdProviderPeserta' => env('PCARE_PROVIDER'),
            'tglDaftar' => $rjDate->format('d-m-Y'),
            'noKartu' => $noKartu,
            'kdPoli' => $this->dataDaftarPoliRJ['kdpolibpjs'] ?? '',
            'keluhan' => $keluhan,
            'kunjSakit' => (int) ($this->dataDaftarPoliRJ['kunjSakit'] ?? 1), // 1=sakit, 0=sehat
            'sistole' => $sistole,
            'diastole' => $diastole,
            'beratBadan' => $bb,
            'tinggiBadan' => $tb,
            'respRate' => $rr,
            'lingkarPerut' => $lp,
            'heartRate' => $nadi,
            'rujukBalik' => 'N',
            'kdTkp' => (string) ($this->dataDaftarPoliRJ['kdTkp'] ?? '10'), // 10=RJTP, 50=Promotif
        ];

        try {
            \Log::info('PCare addPedaftaran request', ['rjNo' => $rjNo, 'payload' => $payload]);
            $response = $this->addPedaftaran($payload)->getOriginalContent();
            $code = $response['metadata']['code'] ?? 0;
            $msg = $response['metadata']['message'] ?? '';

            // Simpan status ke JSON
            $rjData = $this->findDataRJ($rjNo) ?: [];
            $rjData['taskIdPelayanan'] ??= [];
            $rjData['taskIdPelayanan']['pcarePendaftaran'] = [
                'code' => $code,
                'message' => $msg,
                'sentAt' => now()->format('Y-m-d H:i:s'),
                'response' => $response['response'] ?? null,
            ];
            DB::transaction(function () use ($rjNo, $rjData) {
                $this->lockRJRow($rjNo);
                $this->updateJsonRJ($rjNo, $rjData);
            });

            $isOk = $code == 200 || $code == 201;
            $this->dispatch('toast', type: $isOk ? 'success' : 'warning', message: 'PCare Pendaftaran: ' . $msg, title: $isOk ? 'BPJS Berhasil' : 'BPJS Gagal', duration: 6000);
        } catch (\Exception $e) {
            \Log::error('PCare addPedaftaran exception', ['rjNo' => $rjNo, 'error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error', message: 'Error PCare: ' . $e->getMessage(), title: 'BPJS Error');
        }
    }

    /* ===============================
     | PCARE — Kirim Kunjungan ke BPJS
     |
     | Trigger setelah dokter selesai diagnosa+perencanaan, via event
     | 'rj.pcare.push-kunjungan' dgn rjNo. Build payload dari diagnosa
     | + terapi + vital signs di JSON.
     =============================== */
    #[On('rj.pcare.push-kunjungan')]
    public function pushKunjunganByRjNo(string $rjNo): void
    {
        $this->rjNo = $rjNo;
        $rjData = $this->findDataRJ($rjNo);
        if (!$rjData) {
            return;
        }
        $this->dataDaftarPoliRJ = $rjData;
        $this->dataPasien = $this->getMasterPasien($rjData['regNo'] ?? '') ?? [];
        $this->pushKunjunganBPJS();
    }

    private function pushKunjunganBPJS(): void
    {
        if (($this->dataDaftarPoliRJ['klaimId'] ?? '') !== 'JM') {
            return;
        }

        $rjNo = $this->dataDaftarPoliRJ['rjNo'] ?? null;
        if (!$rjNo) {
            return;
        }

        // Wajib: pendaftaran sudah sukses dulu
        $pendaftaranCode = $this->dataDaftarPoliRJ['taskIdPelayanan']['pcarePendaftaran']['code'] ?? '';
        if ($pendaftaranCode != 200 && $pendaftaranCode != 201) {
            $this->dispatch('toast', type: 'warning', message: 'Kirim Pendaftaran BPJS dulu sebelum kirim Kunjungan.', title: 'BPJS Pendaftaran Belum');
            return;
        }

        // Skip kalau sudah berhasil
        $kunjunganCode = $this->dataDaftarPoliRJ['taskIdPelayanan']['pcareKunjungan']['code'] ?? '';
        if ($kunjunganCode == 200 || $kunjunganCode == 201) {
            \Log::info('PCare addKunjungan skipped — already sent', ['rjNo' => $rjNo]);
            return;
        }

        $payload = $this->buildKunjunganPayload($rjNo);
        if ($payload === null) {
            return;
        }

        try {
            \Log::info('PCare addKunjungan request', ['rjNo' => $rjNo, 'payload' => $payload]);
            $response = $this->addKunjungan($payload)->getOriginalContent();
            $code = $response['metadata']['code'] ?? 0;
            $msg = $response['metadata']['message'] ?? '';

            $rjData = $this->findDataRJ($rjNo) ?: [];
            $rjData['taskIdPelayanan'] ??= [];
            $rjData['taskIdPelayanan']['pcareKunjungan'] = [
                'code' => $code,
                'message' => $msg,
                'sentAt' => now()->format('Y-m-d H:i:s'),
                'response' => $response['response'] ?? null,
            ];
            DB::transaction(function () use ($rjNo, $rjData) {
                $this->lockRJRow($rjNo);
                $this->updateJsonRJ($rjNo, $rjData);
            });

            $isOk = $code == 200 || $code == 201;
            $this->dispatch('toast', type: $isOk ? 'success' : 'warning', message: 'PCare Kunjungan: ' . $msg, title: $isOk ? 'BPJS Berhasil' : 'BPJS Gagal', duration: 6000);
        } catch (\Exception $e) {
            \Log::error('PCare addKunjungan exception', ['rjNo' => $rjNo, 'error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error', message: 'Error PCare: ' . $e->getMessage(), title: 'BPJS Error');
        }
    }

    /* ===============================
     | PCARE — Riwayat Kunjungan BPJS
     |
     | Trigger via event 'rj.pcare.riwayat-kunjungan' dgn rjNo.
     | Ambil noKartu dari master pasien lalu panggil getRiwayatKunjungan.
     =============================== */
    #[On('rj.pcare.riwayat-kunjungan')]
    public function showRiwayatKunjunganByRjNo(string $rjNo): void
    {
        $rjData = $this->findDataRJ($rjNo);
        if (!$rjData) {
            $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
            return;
        }

        $pasien = $this->getMasterPasien($rjData['regNo'] ?? '') ?? [];
        $noKartu = preg_replace('/\D/', '', $pasien['pasien']['identitas']['nokartuBpjs'] ?? '');
        $nama = $pasien['pasien']['regName'] ?? ($rjData['regName'] ?? '');

        if (strlen($noKartu) !== 13) {
            $this->dispatch('toast', type: 'warning', message: 'No. Kartu BPJS pasien belum diisi (atau bukan 13 digit).', title: 'BPJS Riwayat');
            return;
        }

        try {
            $resp = $this->getRiwayatKunjungan($noKartu)->getOriginalContent();
            $code = $resp['metadata']['code'] ?? 0;

            if ($code != 200) {
                $msg = $resp['metadata']['message'] ?? "code {$code}";
                $this->dispatch('toast', type: 'error', message: 'BPJS getRiwayatKunjungan: ' . $msg, title: 'BPJS Riwayat');
                return;
            }

            $list = $resp['response']['list'] ?? ($resp['response'] ?? []);
            $this->riwayatBpjsList = is_array($list) ? array_values($list) : [];
            $this->riwayatBpjsTitle = trim("Riwayat Kunjungan BPJS — {$nama} ({$noKartu})");
            $this->showRiwayatBpjs = true;
            $this->dispatch('open-modal', name: 'rj-riwayat-bpjs');
        } catch (\Exception $e) {
            \Log::error('PCare getRiwayatKunjungan exception', ['rjNo' => $rjNo, 'error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error', message: 'Error PCare: ' . $e->getMessage(), title: 'BPJS Error');
        }
    }

    public function closeRiwayatBpjs(): void
    {
        $this->showRiwayatBpjs = false;
        $this->riwayatBpjsList = [];
        $this->riwayatBpjsTitle = '';
        $this->dispatch('close-modal', name: 'rj-riwayat-bpjs');
    }

    /* ===============================
     | PCARE — Edit Kunjungan BPJS (revisi yg sudah dikirim)
     |
     | Trigger via event 'rj.pcare.edit-kunjungan' dgn rjNo.
     | Wajib: pcareKunjungan.code sudah 200/201 sebelumnya.
     =============================== */
    #[On('rj.pcare.edit-kunjungan')]
    public function editKunjunganByRjNo(string $rjNo): void
    {
        $this->rjNo = $rjNo;
        $rjData = $this->findDataRJ($rjNo);
        if (!$rjData) {
            return;
        }
        $this->dataDaftarPoliRJ = $rjData;
        $this->dataPasien = $this->getMasterPasien($rjData['regNo'] ?? '') ?? [];
        $this->editKunjunganBPJS();
    }

    private function editKunjunganBPJS(): void
    {
        if (($this->dataDaftarPoliRJ['klaimId'] ?? '') !== 'JM') {
            return;
        }

        $rjNo = $this->dataDaftarPoliRJ['rjNo'] ?? null;
        if (!$rjNo) {
            return;
        }

        $kunjunganCode = $this->dataDaftarPoliRJ['taskIdPelayanan']['pcareKunjungan']['code'] ?? '';
        if ($kunjunganCode != 200 && $kunjunganCode != 201) {
            $this->dispatch('toast', type: 'warning', message: 'Kunjungan belum pernah dikirim sukses. Pakai "Kirim Kunjungan BPJS" dulu.', title: 'BPJS Edit');
            return;
        }

        $payload = $this->buildKunjunganPayload($rjNo);
        if ($payload === null) {
            return;
        } // toast sudah di-dispatch

        try {
            \Log::info('PCare editKunjungan request', ['rjNo' => $rjNo, 'payload' => $payload]);
            $response = $this->editKunjungan($payload)->getOriginalContent();
            $code = $response['metadata']['code'] ?? 0;
            $msg = $response['metadata']['message'] ?? '';

            $rjData = $this->findDataRJ($rjNo) ?: [];
            $rjData['taskIdPelayanan'] ??= [];
            $rjData['taskIdPelayanan']['pcareKunjungan'] = [
                'code' => $code,
                'message' => $msg,
                'sentAt' => now()->format('Y-m-d H:i:s'),
                'response' => $response['response'] ?? null,
                'editedAt' => now()->format('Y-m-d H:i:s'),
            ];
            DB::transaction(function () use ($rjNo, $rjData) {
                $this->lockRJRow($rjNo);
                $this->updateJsonRJ($rjNo, $rjData);
            });

            $isOk = $code == 200 || $code == 201;
            $this->dispatch('toast', type: $isOk ? 'success' : 'warning', message: 'PCare Edit Kunjungan: ' . $msg, title: $isOk ? 'BPJS Berhasil' : 'BPJS Gagal', duration: 6000);
        } catch (\Exception $e) {
            \Log::error('PCare editKunjungan exception', ['rjNo' => $rjNo, 'error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error', message: 'Error PCare: ' . $e->getMessage(), title: 'BPJS Error');
        }
    }

    /* ===============================
     | PCARE — Hapus Kunjungan BPJS
     |
     | Trigger via event 'rj.pcare.delete-kunjungan' dgn rjNo.
     | Wajib: pcareKunjungan.code sudah 200/201 sebelumnya.
     =============================== */
    #[On('rj.pcare.delete-kunjungan')]
    public function deleteKunjunganByRjNo(string $rjNo): void
    {
        $this->rjNo = $rjNo;
        $rjData = $this->findDataRJ($rjNo);
        if (!$rjData) {
            return;
        }
        $this->dataDaftarPoliRJ = $rjData;

        $kunjunganCode = $this->dataDaftarPoliRJ['taskIdPelayanan']['pcareKunjungan']['code'] ?? '';
        if ($kunjunganCode != 200 && $kunjunganCode != 201) {
            $this->dispatch('toast', type: 'warning', message: 'Kunjungan belum pernah dikirim sukses, tidak bisa dihapus.', title: 'BPJS Hapus');
            return;
        }

        $noKunjungan = 'RJ-' . $rjNo;

        try {
            \Log::info('PCare deleteKunjungan request', ['rjNo' => $rjNo, 'noKunjungan' => $noKunjungan]);
            $response = $this->deleteKunjungan($noKunjungan)->getOriginalContent();
            $code = $response['metadata']['code'] ?? 0;
            $msg = $response['metadata']['message'] ?? '';

            $rjData = $this->findDataRJ($rjNo) ?: [];
            $rjData['taskIdPelayanan'] ??= [];
            $rjData['taskIdPelayanan']['pcareKunjunganDelete'] = [
                'code' => $code,
                'message' => $msg,
                'sentAt' => now()->format('Y-m-d H:i:s'),
                'response' => $response['response'] ?? null,
            ];
            // Reset pcareKunjungan code supaya bisa Kirim ulang
            if ($code == 200 || $code == 201) {
                $rjData['taskIdPelayanan']['pcareKunjungan']['code'] = 0;
                $rjData['taskIdPelayanan']['pcareKunjungan']['message'] = 'Deleted on ' . now()->format('Y-m-d H:i:s');
            }
            DB::transaction(function () use ($rjNo, $rjData) {
                $this->lockRJRow($rjNo);
                $this->updateJsonRJ($rjNo, $rjData);
            });

            $isOk = $code == 200 || $code == 201;
            $this->dispatch('toast', type: $isOk ? 'success' : 'warning', message: 'PCare Hapus Kunjungan: ' . $msg, title: $isOk ? 'BPJS Berhasil' : 'BPJS Gagal', duration: 6000);
        } catch (\Exception $e) {
            \Log::error('PCare deleteKunjungan exception', ['rjNo' => $rjNo, 'error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error', message: 'Error PCare: ' . $e->getMessage(), title: 'BPJS Error');
        }
    }

    /* -------------------------
     | Build payload kunjungan (dipakai add & edit)
     * ------------------------- */
    private function buildKunjunganPayload(string $rjNo): ?array
    {
        $diagnosa = $this->dataDaftarPoliRJ['diagnosis'] ?? [];
        $kdDiag1 = $diagnosa[0]['icdX'] ?? '';
        $kdDiag2 = $diagnosa[1]['icdX'] ?? null;
        $kdDiag3 = $diagnosa[2]['icdX'] ?? null;

        if (!$kdDiag1) {
            $this->dispatch('toast', type: 'warning', message: 'Diagnosa primer wajib diisi sebelum kirim Kunjungan.', title: 'Diagnosa Belum');
            return null;
        }

        $pf = $this->dataDaftarPoliRJ['pemeriksaanFisik'] ?? ($this->dataDaftarPoliRJ['tandaVital'] ?? []);

        $rjDate = Carbon::createFromFormat('d/m/Y H:i:s', $this->dataDaftarPoliRJ['rjDate']);
        $noKartu = preg_replace('/\D/', '', $this->dataPasien['pasien']['identitas']['nokartuBpjs'] ?? '');
        $perencanaan = $this->dataDaftarPoliRJ['perencanaan'] ?? [];
        $anamnesa = $this->dataDaftarPoliRJ['anamnesa'] ?? [];

        return [
            'noKunjungan' => 'RJ-' . $rjNo,
            'noKartu' => $noKartu,
            'tglDaftar' => $rjDate->format('d-m-Y'),
            'kdPoli' => $this->dataDaftarPoliRJ['kdpolibpjs'] ?? '',
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
            'kdDokter' => $this->dataDaftarPoliRJ['kddrbpjs'] ?? '',
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

    /* ===============================
     | DB ERROR HANDLER
     =============================== */
    private function handleDatabaseError(QueryException $e): void
    {
        $errorCode = $e->errorInfo[1] ?? 0;

        $message = match ($errorCode) {
            1 => 'Duplikasi data, record sudah ada.',
            1400 => 'Field wajib tidak boleh kosong.',
            2291 => 'Data referensi tidak valid.',
            2292 => 'Data sedang digunakan, tidak dapat diubah.',
            8177 => 'Kesalahan constraint, periksa kembali data.',
            default => 'Kesalahan database: ' . $e->getMessage(),
        };

        $this->dispatch('toast', type: 'error', message: $message);

        \Log::error('Database error in save: ' . $e->getMessage(), [
            'rjNo' => $this->dataDaftarPoliRJ['rjNo'] ?? null,
            'formMode' => $this->formMode,
        ]);
    }
    public function shiftMismatchMessage(): ?string
    {
        $rjDate = $this->dataDaftarPoliRJ['rjDate'] ?? '';
        $shift = (string) ($this->dataDaftarPoliRJ['shift'] ?? '');
        if (empty($rjDate) || empty($shift)) {
            return null;
        }

        try {
            $time = Carbon::createFromFormat('d/m/Y H:i:s', $rjDate)->format('H:i:s');
        } catch (\Throwable $e) {
            return null;
        }

        $expected = $this->resolveShiftByTime($time);
        if ($expected === $shift) {
            return null;
        }

        return "Jam {$time} seharusnya Shift {$expected}, bukan Shift {$shift}.";
    }

    private function resolveShiftByTime(string $time): string
    {
        // Oracle treats '' as NULL, jadi whereNotNull sudah cukup — jangan tambah where('!=',''),
        // karena `col != NULL` selalu unknown/false → semua row ter-filter habis.
        $row = DB::table('rstxn_shiftctls')
            ->select('shift')
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [$time])
            ->first();

        return (string) ($row?->shift ?? '1');
    }
    private function hitungNoAntrian(string $drId, Carbon $rjDateCarbon): int
    {
        $poliId = $this->dataDaftarPoliRJ['poliId'] ?? null;

        $maxAntrianRjhdrs = (int) DB::table('rstxn_rjhdrs')
            ->where('dr_id', $drId)
            ->when($poliId, fn($q) => $q->where('poli_id', $poliId))

            ->whereRaw("to_char(rj_date, 'ddmmyyyy') = ?", [$rjDateCarbon->format('dmY')])
            ->max('no_antrian');

        // angkaantrean bertipe VARCHAR2 — pakai to_number agar max numeric (bukan lex sort).
        $maxAntrianBooking = (int) DB::table('referensi_mobilejkn_bpjs as b')->join('rsmst_doctors as d', 'd.kd_dr_bpjs', '=', 'b.kodedokter')->where('d.dr_id', $drId)->where('b.tanggalperiksa', $rjDateCarbon->format('Y-m-d'))->selectRaw('nvl(max(to_number(b.angkaantrean)), 0) as maxq')->value('maxq');

        return max($maxAntrianRjhdrs, $maxAntrianBooking) + 1;
    }

    /* ===============================
     | LOV HANDLERS
     =============================== */
    #[On('lov.selected.rjFormPasien')]
    public function rjFormPasien(string $target, array $payload): void
    {
        $this->dataDaftarPoliRJ['regNo'] = $payload['reg_no'] ?? '';
        $this->dataDaftarPoliRJ['regName'] = $payload['reg_name'] ?? '';
        $this->dataPasien = $this->findDataMasterPasien($this->dataDaftarPoliRJ['regNo'] ?? '');
        $this->incrementVersion('pasien');
        $this->incrementVersion('modal');
        $this->dispatch('focus-cari-dokter');
    }

    #[On('lov.selected.rjFormDokter')]
    public function rjFormDokter(string $target, array $payload): void
    {
        $this->dataDaftarPoliRJ['drId'] = $payload['dr_id'] ?? '';
        $this->dataDaftarPoliRJ['drDesc'] = $payload['dr_name'] ?? '';
        $this->dataDaftarPoliRJ['poliId'] = $payload['poli_id'] ?? '';
        $this->dataDaftarPoliRJ['poliDesc'] = $payload['poli_desc'] ?? '';
        $this->dataDaftarPoliRJ['kddrbpjs'] = $payload['kd_dr_bpjs'] ?? '';
        $this->dataDaftarPoliRJ['kdpolibpjs'] = $payload['kd_poli_bpjs'] ?? '';
        $this->incrementVersion('dokter');
        $this->incrementVersion('modal');
        $this->dispatch('focus-klaim-options');
    }

    /* ===============================
     | SEP HANDLERS
     =============================== */
    /* ===============================
     | SATU SEHAT
     =============================== */
    // Modal Satu Sehat pindah ke komponen sendiri satu-sehat-rj-actions.
    // Trigger dispatch event 'daftar-rj.satu-sehat.open' yang ditangkap
    // oleh komponen tersebut (lihat embed di bawah).

    /* ===============================
     | iDRG (E-Klaim Kemenkes)
     =============================== */
    // Modal iDRG/INACBG full pindah ke SFC idrg-rj-actions (sibling component).
    // Trigger lewat dispatch event 'daftar-rj.idrg.open' yang ditangkap oleh
    // idrg-rj-actions di file ini paling bawah.

    /* ===============================
     | UPDATED HOOKS
     =============================== */
    #[On('sep-generated')]
    public function updated($name, $value): void
    {
        if ($name === 'dataDaftarPoliRJ.rjDate' && !empty($value)) {
            try {
                $time = Carbon::createFromFormat('d/m/Y H:i:s', $value)->format('H:i:s');
                $this->dataDaftarPoliRJ['shift'] = $this->resolveShiftByTime($time);
            } catch (\Throwable $e) {
                // Format belum valid (user masih mengetik) — biarkan shift apa adanya
            }
        }

        if (in_array($name, ['dataDaftarPoliRJ.regNo'])) {
            $this->incrementVersion('pasien');
            $this->incrementVersion('modal');
        }

        if ($name === 'dataDaftarPoliRJ.drId') {
            $this->incrementVersion('dokter');
            $this->incrementVersion('modal');
        }

        if (in_array($name, ['klaimId', 'kunjSakit', 'kdTkp'])) {
            $this->incrementVersion('modal');
        }

        if ($name === 'kunjSakit') {
            $this->kunjSakit = $value;
            $this->dataDaftarPoliRJ['kunjSakit'] = $value;
        }

        if ($name === 'kdTkp') {
            $this->kdTkp = $value;
            $this->dataDaftarPoliRJ['kdTkp'] = $value;
        }

        if ($name === 'klaimId') {
            $this->klaimId = $value;
            $this->dataDaftarPoliRJ['klaimId'] = $value;
            $this->dataDaftarPoliRJ['klaimStatus'] = DB::table('rsmst_klaimtypes')->where('klaim_id', $value)->value('klaim_status') ?? 'UMUM';
        }
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function syncFromDataDaftarPoliRJ(): void
    {
        $this->klaimId = $this->dataDaftarPoliRJ['klaimId'] ?? 'UM';
        $this->kunjSakit = (string) ($this->dataDaftarPoliRJ['kunjSakit'] ?? '1');
        $this->kdTkp = (string) ($this->dataDaftarPoliRJ['kdTkp'] ?? '10');
    }

    protected function resetForm(): void
    {
        $this->reset(['rjNo', 'dataDaftarPoliRJ']);
        $this->resetVersion();
        $this->klaimId = 'UM';
        $this->kunjSakit = '1';
        $this->kdTkp = '10';
        $this->formMode = 'create';

        $this->dataDaftarPoliRJ['rjDate'] = Carbon::now()->format('d/m/Y H:i:s');
        $this->dataDaftarPoliRJ['regNo'] = '';
        $this->dataDaftarPoliRJ['regName'] = '';
        $this->dataDaftarPoliRJ['drId'] = null;
        $this->dataDaftarPoliRJ['drDesc'] = '';
        $this->dataDaftarPoliRJ['poliId'] = null;
        $this->dataDaftarPoliRJ['poliDesc'] = '';
        $this->dataDaftarPoliRJ['passStatus'] = 'O';
    }
};
?>
{{-- Blade template tidak ada perubahan --}}
<div>
    <x-modal name="rj-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $rjNo ?? 'new']) }}">

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
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Data Rawat Jalan' : 'Tambah Data Rawat Jalan' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Kelola data pendaftaran dan
                                    pelayanan pasien rawat jalan.</p>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-3">
                            <x-badge
                                :variant="$formMode === 'edit' ? 'warning' : 'success'">{{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}</x-badge>
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <x-input-label value="Tanggal RJ" />
                            <x-text-input wire:model.live="dataDaftarPoliRJ.rjDate" class="block w-full"
                                :error="$errors->has('dataDaftarPoliRJ.rjDate')" :disabled="$isFormLocked" />
                            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.rjDate')" class="mt-1" />
                        </div>
                        <div class="w-36">
                            <x-input-label value="Shift" />
                            <x-select-input wire:model.live="dataDaftarPoliRJ.shift" class="w-full mt-1 sm:w-36"
                                :error="$errors->has('dataDaftarPoliRJ.shift')" :disabled="$isFormLocked">
                                <option value="">-- Pilih Shift --</option>
                                <option value="1">Shift 1</option>
                                <option value="2">Shift 2</option>
                                <option value="3">Shift 3</option>
                            </x-select-input>
                            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.shift')" class="mt-1" />
                            @if ($shiftMsg = $this->shiftMismatchMessage())
                                <p class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ $shiftMsg }}
                                </p>
                            @endif
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20" x-data
                x-on:focus-cari-pasien.window="$nextTick(() => setTimeout(() => $refs.lovPasien?.querySelector('input')?.focus(), 150))"
                x-on:focus-cari-dokter.window="$nextTick(() => setTimeout(() => $refs.lovDokter?.querySelector('input')?.focus(), 150))"
                x-on:focus-klaim-options.window="$nextTick(() => setTimeout(() => $refs.klaimOptions?.querySelector('input[type=radio]')?.focus(), 150))"
                x-on:focus-no-referensi.window="$nextTick(() => setTimeout(() => $refs.inputNoReferensi?.querySelector('input')?.focus(), 150))">
                <div class="max-w-full mx-auto">
                    <div class="p-1 space-y-1">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                            {{-- KOLOM KIRI --}}
                            <div
                                class="p-6 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                                <div>
                                    <div class="mt-2">
                                        <x-toggle wire:model.live="dataDaftarPoliRJ.passStatus" trueValue="N"
                                            falseValue="O" label="Pasien Baru" :disabled="$isFormLocked" />
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Jika tidak dicentang maka
                                        dianggap Pasien Lama.</p>
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.passStatus')" class="mt-1" />
                                </div>
                                <div class="mt-2" x-ref="lovPasien">
                                    <livewire:lov.pasien.lov-pasien target="rjFormPasien" :initialRegNo="$dataDaftarPoliRJ['regNo'] ?? ''"
                                        :disabled="$isFormLocked" />
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.regNo')" class="mt-1" />
                                </div>
                                <div class="mt-2" x-ref="lovDokter">
                                    <livewire:lov.dokter.lov-dokter label="Cari Dokter - Poli" target="rjFormDokter"
                                        :initialDrId="$dataDaftarPoliRJ['drId'] ?? null" :disabled="$isFormLocked" />
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.drId')" class="mt-1" />
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.drDesc')" class="mt-1" />
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.poliId')" class="mt-1" />
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.poliDesc')" class="mt-1" />
                                </div>
                                <div x-ref="klaimOptions">
                                    <x-input-label value="Jenis Klaim" />
                                    <div class="grid grid-cols-5 gap-2 mt-2">
                                        @foreach ($klaimOptions ?? [] as $klaim)
                                            <x-radio-button :label="$klaim['klaimDesc']" :value="(string) $klaim['klaimId']" name="klaimId"
                                                wire:model.live="klaimId" :disabled="$isFormLocked" />
                                        @endforeach
                                    </div>
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.klaimId')" class="mt-1" />
                                </div>
                            </div>

                            {{-- KOLOM KANAN --}}
                            <div
                                class="p-6 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                                @if (($dataDaftarPoliRJ['klaimStatus'] ?? '') === 'BPJS' || ($dataDaftarPoliRJ['klaimId'] ?? '') === 'JM')
                                    <div class="space-y-3">
                                        {{-- Kunjungan Sakit/Sehat — PCare klinik pratama --}}
                                        <div>
                                            <x-input-label value="Kunjungan Sakit / Sehat" :required="true" />
                                            <div class="grid grid-cols-2 gap-2">
                                                @foreach ($kunjSakitOptions as $opt)
                                                    <x-radio-button :label="$opt['kunjSakitDesc']" :value="$opt['kunjSakitId']"
                                                        name="kunjSakit" wire:model.live="kunjSakit"
                                                        :disabled="$isFormLocked" />
                                                @endforeach
                                            </div>
                                            <p
                                                class="mt-1 text-xs {{ $kunjSakit === '1' ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                                {{ $kunjSakit === '1' ? 'Pasien datang dgn keluhan/sakit.' : 'Imunisasi, KB, kontrol gizi, promotif (poli BPJS: 020, 021, 023–026).' }}
                                            </p>
                                        </div>

                                        {{-- Tkp (Tempat Kunjungan Pelayanan) PCare --}}
                                        <div>
                                            <x-input-label value="Tempat Pelayanan (Tkp)" :required="true" />
                                            <div class="grid grid-cols-2 gap-2">
                                                @foreach ($kdTkpOptions as $opt)
                                                    <x-radio-button :label="$opt['kdTkpDesc']" :value="$opt['kdTkpId']" name="kdTkp"
                                                        wire:model.live="kdTkp" :disabled="$isFormLocked" />
                                                @endforeach
                                            </div>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                10 = Rawat Jalan Tingkat Pertama (default klinik). 50 = Promotif
                                                (penyuluhan / kelas ibu hamil / dll).
                                            </p>
                                        </div>
                                    </div>
                                @endif
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-between gap-3">
                    <a href="{{ route('master.pasien') }}" wire:navigate>
                        <x-ghost-button type="button">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Master Pasien
                        </x-ghost-button>
                    </a>
                    <div class="flex justify-between gap-3">
                        <x-secondary-button wire:click="closeModal">
                            Batal
                        </x-secondary-button>
                        <x-primary-button x-ref="btnSimpan" wire:click.prevent="save()" class="min-w-[120px]"
                            wire:loading.attr="disabled" :disabled="$isFormLocked">
                            <span wire:loading.remove>
                                <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                                </svg>
                                {{ $isFormLocked ? 'Read Only' : 'Simpan' }}
                            </span>
                            <span wire:loading><x-loading /> Menyimpan...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>

    {{-- Satu Sehat modal embed pindah ke ⚡daftar-rj.blade.php (page level)
         supaya pola konsisten dengan openRekamMedis/openSatuSehat dispatcher
         yang sudah ada di daftar-rj. --}}

    {{-- ================================
         RIWAYAT KUNJUNGAN BPJS — modal
    ================================ --}}
    <x-modal name="rj-riwayat-bpjs" size="3xl" focusable>
        <div class="flex flex-col">
            {{-- HEADER --}}
            <div class="flex items-start justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Riwayat Kunjungan BPJS
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $riwayatBpjsTitle ?: 'Detail kunjungan dari BPJS PCare' }}
                    </p>
                </div>
                <x-icon-button color="gray" type="button" wire:click="closeRiwayatBpjs">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </x-icon-button>
            </div>

            {{-- BODY --}}
            <div class="px-6 py-4 max-h-[70vh] overflow-y-auto">
                @if (count($riwayatBpjsList) === 0)
                    <div class="px-4 py-10 text-sm text-center text-gray-500 dark:text-gray-400">
                        Belum ada riwayat kunjungan.
                    </div>
                @else
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($riwayatBpjsList as $idx => $row)
                            <li wire:key="riwayat-bpjs-{{ $idx }}" class="py-3">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $row['kdPoli']['nmPoli'] ?? ($row['kdPoli'] ?? '-') }}
                                        <span class="ml-2 text-xs font-normal text-gray-500">
                                            {{ $row['tglDaftar'] ?? '-' }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        @if (!empty($row['noKunjungan']))
                                            No: <span class="font-mono">{{ $row['noKunjungan'] }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="mt-1 text-sm text-gray-700 dark:text-gray-200">
                                    @if (!empty($row['keluhan']))
                                        <div><span class="text-gray-500">Keluhan:</span> {{ $row['keluhan'] }}</div>
                                    @endif
                                    @if (!empty($row['kdDiag1']) || !empty($row['nmDiag1']))
                                        <div>
                                            <span class="text-gray-500">Dx1:</span>
                                            <span class="font-mono">{{ $row['kdDiag1'] ?? '-' }}</span>
                                            {{ $row['nmDiag1'] ?? '' }}
                                        </div>
                                    @endif
                                    @if (!empty($row['kdDiag2']) || !empty($row['nmDiag2']))
                                        <div>
                                            <span class="text-gray-500">Dx2:</span>
                                            <span class="font-mono">{{ $row['kdDiag2'] ?? '-' }}</span>
                                            {{ $row['nmDiag2'] ?? '' }}
                                        </div>
                                    @endif
                                    @if (!empty($row['terapiObat']))
                                        <div><span class="text-gray-500">Terapi:</span> {{ $row['terapiObat'] }}</div>
                                    @endif
                                    @if (!empty($row['kdDokter']) || !empty($row['nmDokter']))
                                        <div class="text-xs text-gray-500">
                                            Dokter: {{ $row['nmDokter'] ?? ($row['kdDokter'] ?? '-') }}
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- FOOTER --}}
            <div class="flex justify-end px-6 py-3 border-t border-gray-200 dark:border-gray-700">
                <x-secondary-button type="button" wire:click="closeRiwayatBpjs">Tutup</x-secondary-button>
            </div>
        </div>
    </x-modal>

</div>
