<?php

namespace App\Http\Controllers;

use App\Http\Traits\BPJS\TraitJWTAntreanFktp;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Webservice INBOUND Antrean FKTP Klinik Pratama (Mobile JKN).
 *
 * Arah panggilan: BPJS Mobile JKN (client) → klinik siklik (server).
 * Konsep di-mirror dari AntrolBPJSController project rsimadinah,
 * adaptasi ke spec FKTP klinik pratama (GET path params + POST/PUT body).
 *
 * Auth flow:
 *   1. BPJS hit GET /auth dengan x-username + x-password (cocokkan ke
 *      env ANTREAN_USERNAME/PASSWORD) → return JWT.
 *   2. BPJS hit endpoint lain dengan x-username + x-token → authenticate()
 *      verifikasi JWT signature & exp.
 *
 * Tabel siklik yang disentuh:
 *   - REFERENSI_MOBILEJKN_BPJS  : staging booking Mobile JKN
 *   - RSMST_PASIENS             : validasi pasien existing
 *   - RSMST_DOCTORS             : lookup dokter via KD_DR_BPJS
 *   - RSMST_POLIS               : lookup poli via KD_POLI_BPJS
 *   - RSTXN_RJHDRS              : count antrean realtime (sudah checkin)
 *   - PASIEN                    : registrasi calon pasien baru dari Mobile JKN
 *   - WEB_LOG_STATUS            : audit log per request (via trait)
 */
class AntreanFktpController extends Controller
{
    use TraitJWTAntreanFktp;

    /* =========================================================
     | AUTHENTICATE (helper internal)
     * ========================================================= */

    /**
     * Cek x-username + x-token di setiap request endpoint (selain /auth).
     *
     * Return:
     *   - true                : credentials valid, lanjut ke handler
     *   - JsonResponse         : envelope error, controller langsung return
     */
    protected function authenticate(Request $request)
    {
        $username = $request->header('x-username');
        $token    = $request->header('x-token');

        if (!$username || !$token) {
            return $this->sendError($request, 'Unauthorized: Missing credentials (x-username / x-token)', 201);
        }

        if ($username !== env('ANTREAN_USERNAME')) {
            return $this->sendError($request, 'Unauthorized: Username tidak terdaftar', 201);
        }

        $check = $this->cektoken($token);
        if ($check !== true) {
            // cektoken return envelope error — bungkus jadi JsonResponse
            return $this->sendError($request, $check['metadata']['message'] ?? 'Token invalid', 201);
        }

        return true;
    }

    /* =========================================================
     | ENDPOINTS
     * ========================================================= */

    /**
     * GET /auth — generate JWT token.
     * Header: x-username, x-password
     */
    public function auth(Request $request)
    {
        $username = $request->header('x-username');
        $password = $request->header('x-password');

        if (!$username || !$password) {
            return $this->sendError($request, 'Username / password tidak boleh kosong', 201);
        }

        $token = $this->createToken($username, $password);
        if ($token === 'x') {
            return $this->sendError($request, 'Unauthorized (Username dan Password Salah)', 201);
        }

        return $this->sendResponse($request, ['token' => $token], 200);
    }

    /**
     * GET /ref/dokter/kodepoli/{kodepoli}/tanggal/{tanggal}
     *   — list dokter aktif di poli pada tanggal tertentu.
     *
     * Beda dari status() — endpoint ini referensi master (jadwal + kapasitas),
     * bukan progress antrean realtime. BPJS Mobile JKN pakai untuk display
     * pilihan dokter di app sebelum pasien daftar.
     *
     * Query: SCVIEW_SCPOLIS join hari (Senin-Minggu derived dari $tanggal)
     *        + filter SC_POLI_STATUS_='1' (jadwal aktif).
     *
     * Response shape (mirror spec BPJS):
     *   { response: { list: [{namadokter, kodedokter, jampraktek, kapasitas}, ...] },
     *     metadata: { code, message } }
     */
    public function refDokter(Request $request, $kodepoli, $tanggal)
    {
        $auth = $this->authenticate($request);
        if ($auth instanceof JsonResponse) return $auth;

        $validator = Validator::make(
            ['kodepoli' => $kodepoli, 'tanggal' => $tanggal],
            [
                'kodepoli' => 'required',
                'tanggal'  => 'required|date|date_format:Y-m-d',
            ]
        );
        if ($validator->fails()) {
            return $this->sendError($request, $validator->errors()->first(), 201);
        }

        // Hari Indonesia untuk filter SCVIEW_SCPOLIS.day_desc
        $hariMap = [
            'Sunday' => 'MINGGU',
            'Monday' => 'SENIN',
            'Tuesday' => 'SELASA',
            'Wednesday' => 'RABU',
            'Thursday' => 'KAMIS',
            'Friday' => 'JUMAT',
            'Saturday' => 'SABTU',
        ];
        $hari = $hariMap[Carbon::parse($tanggal)->dayName] ?? null;
        if (!$hari) {
            return $this->sendError($request, 'Tanggal tidak valid', 201);
        }

        $rows = DB::table('scview_scpolis')
            ->select('dr_name', 'kd_dr_bpjs', 'sc_poli_ket', 'kuota')
            ->where('kd_poli_bpjs', $kodepoli)
            ->where('day_desc', $hari)
            ->where('sc_poli_status_', '1')
            ->whereNotNull('kd_dr_bpjs')
            ->orderBy('mulai_praktek')
            ->orderBy('no_urut')
            ->get();

        if ($rows->isEmpty()) {
            return $this->sendError($request, 'Tidak ada jadwal dokter di poli tersebut pada hari ini', 201);
        }

        $list = $rows->map(fn($r) => [
            'namadokter' => $r->dr_name,
            'kodedokter' => is_numeric($r->kd_dr_bpjs) ? (int) $r->kd_dr_bpjs : $r->kd_dr_bpjs,
            'jampraktek' => $r->sc_poli_ket,
            'kapasitas'  => (int) $r->kuota,
        ])->values()->all();

        return $this->sendResponse($request, ['list' => $list], 200);
    }

    /**
     * GET /antrean/status/{kdPoli}/{tgl} — status antrean per dokter di poli pada tgl.
     *
     * Cara kerja:
     *   1. Lookup poli via RSMST_POLIS.kd_poli_bpjs
     *   2. Source jadwal dari SCVIEW_SCPOLIS (poli + hari = derived dari tgl)
     *      → cuma dokter yang punya jadwal hari itu yang muncul, plus jam
     *      praktek + kuota dari jadwal.
     *   3. Untuk tiap row jadwal: count yang sudah daftar (RSTXN_RJHDRS)
     *      dan yang sedang dilayani (waktu_masuk_poli ada, selesai kosong)
     *   4. Return list per dokter dengan jampraktek, totalantrean, sisaantrean
     */
    public function status(Request $request, $kdPoli, $tgl)
    {
        $auth = $this->authenticate($request);
        if ($auth instanceof JsonResponse) return $auth;

        $validator = Validator::make(
            ['kdPoli' => $kdPoli, 'tgl' => $tgl],
            [
                'kdPoli' => 'required',
                'tgl'    => 'required|date|date_format:Y-m-d',
            ]
        );
        if ($validator->fails()) {
            return $this->sendError($request, $validator->errors()->first(), 201);
        }

        $poli = DB::table('rsmst_polis')->where('kd_poli_bpjs', $kdPoli)->first();
        if (!$poli) {
            return $this->sendError($request, 'Poli tidak ditemukan', 201);
        }

        $hariMap = [
            'Sunday' => 'MINGGU',
            'Monday' => 'SENIN',
            'Tuesday' => 'SELASA',
            'Wednesday' => 'RABU',
            'Thursday' => 'KAMIS',
            'Friday' => 'JUMAT',
            'Saturday' => 'SABTU',
        ];
        $hari = $hariMap[Carbon::parse($tgl)->dayName] ?? null;

        $jadwals = DB::table('scview_scpolis')
            ->select('kuota', 'sc_poli_ket', 'kd_dr_bpjs', 'dr_id', 'dr_name', 'mulai_praktek', 'selesai_praktek')
            ->where('kd_poli_bpjs', $kdPoli)
            ->where('day_desc', $hari)
            ->where('sc_poli_status_', '1')
            ->whereNotNull('kd_dr_bpjs')
            ->orderBy('mulai_praktek')
            ->get();

        if ($jadwals->isEmpty()) {
            return $this->sendError($request, "Tidak ada jadwal dokter aktif di poli ini pada hari {$hari}", 201);
        }

        $list = [];
        foreach ($jadwals as $jd) {
            // Sudah daftar di RJ pada tgl tsb
            $totalDaftar = (int) DB::table('rstxn_rjhdrs')
                ->where('poli_id', $poli->poli_id)
                ->where('dr_id', $jd->dr_id)
                ->whereRaw("to_char(rj_date,'yyyy-mm-dd') = ?", [$tgl])

                ->count();

            // Antrean yang sedang dilayani (waktu_masuk_poli ada, waktu_selesai_pelayanan kosong)
            $sedangDilayani = DB::table('rstxn_rjhdrs')
                ->where('poli_id', $poli->poli_id)
                ->where('dr_id', $jd->dr_id)
                ->whereRaw("to_char(rj_date,'yyyy-mm-dd') = ?", [$tgl])
                ->whereNotNull('waktu_masuk_poli')
                ->whereNull('waktu_selesai_pelayanan')
                ->orderBy('no_antrian', 'desc')
                ->first();

            $list[] = [
                'namapoli'       => $poli->poli_desc,
                'totalantrean'   => (string) $totalDaftar,
                'sisaantrean'    => max(0, (int) $jd->kuota - $totalDaftar),
                'antreanpanggil' => $sedangDilayani->no_antrian ?? '',
                'keterangan'     => '',
                'kodedokter'     => is_numeric($jd->kd_dr_bpjs) ? (int) $jd->kd_dr_bpjs : $jd->kd_dr_bpjs,
                'namadokter'     => $jd->dr_name,
                'jampraktek'     => $jd->sc_poli_ket,
            ];
        }

        return $this->sendResponse($request, $list, 200);
    }

    /**
     * POST /antrean — pasien daftar antrean via Mobile JKN.
     *
     * Body: nomorkartu, nik, kodepoli, tanggalperiksa, keluhan,
     *       kodedokter, jampraktek, norm, nohp
     *
     * Cara kerja:
     *   1. Validasi pasien existing di RSMST_PASIENS (by nokartu_bpjs).
     *      Tidak ada → return 202 (pasien baru, BPJS akan retry pakai /peserta)
     *   2. Cek dokter & poli existence
     *   3. Cek jadwal dokter di SCVIEW_SCPOLIS — wajib match (poli, dokter,
     *      hari, jampraktek). Tanpa jadwal → tolak (cegah booking liar).
     *   4. Cek quota: SCVIEW_SCPOLIS.kuota − count booking belum batal − count RJHDRS
     *   5. Cek duplikasi booking (NIK + tgl)
     *   6. Lock per dokter+tgl (cegah race condition antrean ganda)
     *   7. Generate no_antrian (max + 1 dari RJHDRS & REFERENSI_MOBILEJKN_BPJS)
     *   8. Insert REFERENSI_MOBILEJKN_BPJS (status='Belum')
     *   9. Return nomorantrean, angkaantrean, kodebooking, dst.
     */
    public function ambil(Request $request)
    {
        $auth = $this->authenticate($request);
        if ($auth instanceof JsonResponse) return $auth;

        $validator = Validator::make($request->all(), [
            'nomorkartu'     => 'required|numeric|digits:13',
            'nik'            => 'required|numeric|digits:16',
            'kodepoli'       => 'required',
            'tanggalperiksa' => 'required|date|date_format:Y-m-d',
            'keluhan'        => 'nullable|string',
            'kodedokter'     => 'required',
            'jampraktek'     => 'required',
            'norm'           => 'required',
            'nohp'           => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return $this->sendError($request, $validator->errors()->first(), 201);
        }

        if (Carbon::parse($request->tanggalperiksa)->endOfDay()->isPast()) {
            return $this->sendError($request, 'Tanggal periksa sudah terlewat', 201);
        }

        // Cek pasien existing — kalau gak ada, return code 202 supaya
        // BPJS tau ini pasien baru & lanjut hit /peserta dulu
        $pasien = DB::table('rsmst_pasiens')
            ->select('reg_no', 'nokartu_bpjs', 'nik_bpjs')
            ->where('nokartu_bpjs', $request->nomorkartu)
            ->first();
        if (!$pasien) {
            return $this->sendError($request, 'Pasien belum terdaftar di klinik. Silahkan daftar pasien baru terlebih dahulu.', 202);
        }
        if ($pasien->nik_bpjs && $pasien->nik_bpjs != $request->nik) {
            return $this->sendError($request, 'NIK BPJS tidak cocok dengan data klinik. Silahkan perbaiki via pendaftaran offline.', 201);
        }

        $poli = DB::table('rsmst_polis')->where('kd_poli_bpjs', $request->kodepoli)->first();
        if (!$poli) return $this->sendError($request, 'Poli tidak ditemukan', 201);

        $doctor = DB::table('rsmst_doctors')->where('kd_dr_bpjs', $request->kodedokter)->first();
        if (!$doctor) return $this->sendError($request, 'Dokter tidak ditemukan', 201);

        // Validasi jadwal dokter di SCVIEW_SCPOLIS (jadwal lokal yg di-Apply
        // dari halaman /master/jadwal-mingguan). Cek 4 dimensi:
        //   poli + dokter + hari (derived dari tgl) + jam praktek match.
        $hariMap = [
            'Sunday' => 'MINGGU',
            'Monday' => 'SENIN',
            'Tuesday' => 'SELASA',
            'Wednesday' => 'RABU',
            'Thursday' => 'KAMIS',
            'Friday' => 'JUMAT',
            'Saturday' => 'SABTU',
        ];
        $hari = $hariMap[Carbon::parse($request->tanggalperiksa)->dayName] ?? null;
        $jampraktek = $request->jampraktek;
        $parts = explode('-', $jampraktek);
        if (count($parts) !== 2) {
            return $this->sendError($request, 'Format jampraktek invalid. Harus HH:MM-HH:MM.', 201);
        }
        $jamMulai   = trim($parts[0]) . ':00';
        $jamSelesai = trim($parts[1]) . ':00';

        $jadwal = DB::table('scview_scpolis')
            ->select('kuota', 'sc_poli_ket', 'shift', 'poli_desc', 'dr_name')
            ->where('kd_poli_bpjs', $request->kodepoli)
            ->where('kd_dr_bpjs', $request->kodedokter)
            ->where('day_desc', $hari)
            ->where('mulai_praktek', $jamMulai)
            ->where('selesai_praktek', $jamSelesai)
            ->where('sc_poli_status_', '1')
            ->first();

        if (!$jadwal) {
            return $this->sendError($request, "Jadwal dokter tidak tersedia di poli ini pada hari {$hari} jam {$jampraktek}.", 201);
        }

        // Cek quota — count antrean belum batal di REFERENSI + count RJHDRS yg sudah checkin
        $bookingActive = (int) DB::table('referensi_mobilejkn_bpjs')
            ->where('kodepoli', $request->kodepoli)
            ->where('kodedokter', $request->kodedokter)
            ->where('tanggalperiksa', $request->tanggalperiksa)
            ->where('status', '!=', 'Batal')
            ->count();

        $rjActive = (int) DB::table('rstxn_rjhdrs')
            ->where('poli_id', $poli->poli_id)
            ->where('dr_id', $doctor->dr_id)
            ->whereRaw("to_char(rj_date,'yyyy-mm-dd') = ?", [$request->tanggalperiksa])
            ->where('rj_status', '!=', 'F')

            ->count();

        $sisa = (int) $jadwal->kuota - max($bookingActive, $rjActive);
        if ($sisa <= 0) {
            return $this->sendError($request, "Quota Poli {$jadwal->poli_desc} Dokter {$jadwal->dr_name} pada {$request->tanggalperiksa} sudah penuh.", 201);
        }

        $noBooking = Carbon::now(config('app.timezone'))->format('YmdHis') . 'JKN';
        $lockKey   = "lock:fktp:antrian:{$doctor->dr_id}:" . Carbon::parse($request->tanggalperiksa)->format('Ymd');

        try {
            $response = Cache::lock($lockKey, 15)->block(5, function () use ($request, $poli, $doctor, $pasien, $noBooking, $jadwal) {
                return DB::transaction(function () use ($request, $poli, $doctor, $pasien, $noBooking, $jadwal) {

                    // Duplikasi: NIK sama, tgl sama, status bukan Batal
                    $dup = DB::table('referensi_mobilejkn_bpjs')
                        ->where('tanggalperiksa', $request->tanggalperiksa)
                        ->where('nik', $request->nik)
                        ->where('status', '!=', 'Batal')
                        ->first();
                    if ($dup) {
                        throw new Exception("Sudah ada antrean ({$dup->nobooking}) dengan NIK yang sama pada tanggal tersebut.");
                    }

                    // Hitung max antrean dari RJHDRS (admin/loket) dan booking JKN
                    $maxRJ = (int) DB::table('rstxn_rjhdrs')
                        ->where('dr_id', $doctor->dr_id)
                        ->where('poli_id', $poli->poli_id)
                        ->whereRaw("to_char(rj_date,'yyyy-mm-dd') = ?", [$request->tanggalperiksa])

                        ->max('no_antrian');

                    $maxBooking = (int) DB::table('referensi_mobilejkn_bpjs')
                        ->where('kodedokter', $request->kodedokter)
                        ->where('tanggalperiksa', $request->tanggalperiksa)
                        ->selectRaw('nvl(max(to_number(angkaantrean)), 0) as maxq')
                        ->value('maxq');

                    $noAntrian = max($maxRJ, $maxBooking) + 1;

                    // Estimasi: 10 menit per antrean dari awal jampraktek
                    $jammulai = substr($request->jampraktek, 0, 5);
                    $tglFull  = $request->tanggalperiksa . ' ' . $jammulai . ':00';
                    $estimasi = Carbon::createFromFormat('Y-m-d H:i:s', $tglFull, config('app.timezone'))
                        ->addMinutes(10 * ($noAntrian + 1))
                        ->timestamp * 1000;

                    $kuota    = (int) $jadwal->kuota;
                    $sisa     = max(0, $kuota - $noAntrian);

                    DB::table('referensi_mobilejkn_bpjs')->insert([
                        'nobooking'        => $noBooking,
                        'no_rawat'         => $noBooking,
                        'nomorkartu'       => $request->nomorkartu,
                        'nik'              => $request->nik,
                        'nohp'             => $request->nohp,
                        'kodepoli'         => $request->kodepoli,
                        'pasienbaru'       => 0,
                        'norm'             => strtoupper($pasien->reg_no),
                        'tanggalperiksa'   => $request->tanggalperiksa,
                        'kodedokter'       => $request->kodedokter,
                        'jampraktek'       => $request->jampraktek,
                        'jeniskunjungan'   => 1,
                        'nomorreferensi'   => null,
                        'nomorantrean'     => $request->kodepoli . '-' . $noAntrian,
                        'angkaantrean'     => $noAntrian,
                        'estimasidilayani' => $estimasi,
                        'sisakuotajkn'     => $sisa,
                        'kuotajkn'         => $kuota,
                        'sisakuotanonjkn'  => $sisa,
                        'kuotanonjkn'      => $kuota,
                        'status'           => 'Belum',
                        'validasi'         => '',
                        'statuskirim'      => 'Belum',
                        'keterangan_batal' => '',
                        'tanggalbooking'   => Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s'),
                        'daftardariapp'    => 'JKNMobileAPP',
                    ]);

                    return [
                        'nomorantrean'     => $request->kodepoli . '-' . $noAntrian,
                        'angkaantrean'     => $noAntrian,
                        'kodebooking'      => $noBooking,
                        'norm'             => $pasien->reg_no,
                        'namapoli'         => $poli->poli_desc,
                        'namadokter'       => $doctor->dr_name,
                        'estimasidilayani' => $estimasi,
                        'sisakuotajkn'     => $sisa,
                        'kuotajkn'         => $kuota,
                        'sisakuotanonjkn'  => $sisa,
                        'kuotanonjkn'      => $kuota,
                        'keterangan'       => 'Peserta harap 60 menit lebih awal guna pencatatan administrasi.',
                    ];
                });
            });

            return $this->sendResponse($request, $response, 200);
        } catch (Exception $e) {
            return $this->sendError($request, $e->getMessage(), 201);
        }
    }

    /**
     * GET /antrean/sisapeserta/{noKartu}/{kdPoli}/{tgl} — status antrean peserta.
     */
    public function sisa(Request $request, $noKartu, $kdPoli, $tgl)
    {
        $auth = $this->authenticate($request);
        if ($auth instanceof JsonResponse) return $auth;

        $validator = Validator::make(
            ['noKartu' => $noKartu, 'kdPoli' => $kdPoli, 'tgl' => $tgl],
            [
                'noKartu' => 'required|numeric|digits:13',
                'kdPoli'  => 'required',
                'tgl'     => 'required|date|date_format:Y-m-d',
            ]
        );
        if ($validator->fails()) {
            return $this->sendError($request, $validator->errors()->first(), 201);
        }

        $booking = DB::table('referensi_mobilejkn_bpjs')
            ->where('nomorkartu', $noKartu)
            ->where('kodepoli', $kdPoli)
            ->where('tanggalperiksa', $tgl)
            ->where('status', '!=', 'Batal')
            ->orderBy('tanggalbooking', 'desc')
            ->first();

        if (!$booking) {
            return $this->sendError($request, 'Data booking tidak ditemukan', 201);
        }

        $poli   = DB::table('rsmst_polis')->where('kd_poli_bpjs', $kdPoli)->first();
        $doctor = DB::table('rsmst_doctors')->where('kd_dr_bpjs', $booking->kodedokter)->first();

        // Yang sedang dilayani di poli+dokter+tgl tsb
        $sedangDilayani = DB::table('rstxn_rjhdrs')
            ->where('poli_id', $poli->poli_id ?? null)
            ->where('dr_id', $doctor->dr_id ?? null)
            ->whereRaw("to_char(rj_date,'yyyy-mm-dd') = ?", [$tgl])
            ->whereNotNull('waktu_masuk_poli')
            ->whereNull('waktu_selesai_pelayanan')
            ->orderBy('no_antrian', 'desc')
            ->first();

        return $this->sendResponse($request, [
            'nomorantrean'   => $booking->nomorantrean,
            'namapoli'       => $poli->poli_desc ?? '',
            'sisaantrean'    => max(0, (int) $booking->angkaantrean - (int) ($sedangDilayani->no_antrian ?? 0)),
            'antreanpanggil' => $sedangDilayani->no_antrian ?? '',
            'keterangan'     => '',
        ], 200);
    }

    /**
     * POST /peserta — push identitas pasien baru dari Mobile JKN.
     *
     * Body: nomorkartu, nik, nomorkk, nama, jeniskelamin, tanggallahir,
     *       alamat, kodeprop, namaprop, kodedati2, namadati2, kodekec,
     *       namakec, kodekel, namakel, rw, rt
     *
     * Insert ke tabel PASIEN (calon pasien Mobile JKN) — registrasi resmi
     * ke RSMST_PASIENS dilakukan saat pasien datang ke loket klinik.
     */
    public function pasienBaru(Request $request)
    {
        $auth = $this->authenticate($request);
        if ($auth instanceof JsonResponse) return $auth;

        $validator = Validator::make($request->all(), [
            'nomorkartu'   => 'required|numeric|digits:13',
            'nik'          => 'required|numeric|digits:16',
            'nomorkk'      => 'required|numeric|digits:16',
            'nama'         => 'required|string',
            'jeniskelamin' => 'required|in:L,P',
            'tanggallahir' => 'required|date|date_format:Y-m-d',
            'alamat'       => 'required|string',
            'kodeprop'     => 'required',
            'namaprop'     => 'required',
            'kodedati2'    => 'required',
            'namadati2'    => 'required',
            'kodekec'      => 'required',
            'namakec'      => 'required',
            'kodekel'      => 'required',
            'namakel'      => 'required',
            'rw'           => 'required',
            'rt'           => 'required',
        ]);
        if ($validator->fails()) {
            return $this->sendError($request, $validator->errors()->first(), 201);
        }

        // Idempoten — kalau NIK sudah ada, skip insert
        $exist = DB::table('pasien')->where('nik', $request->nik)->first();
        if ($exist) {
            return $this->sendResponse($request, ['message' => 'Pasien sudah terdaftar'], 200);
        }

        try {
            DB::table('pasien')->insert([
                'nik'            => $request->nik,
                'patient_uuid'   => (string) \Illuminate\Support\Str::uuid(),
                'nama_patient'   => $request->nama,
                'jenis_kelamin'  => $request->jeniskelamin,
                'tempat_lahir'   => null,
                'tgl_lahir'      => $request->tanggallahir,
                'alamat'         => $request->alamat,
                'desa'           => $request->namakel,
                'kecamatan'      => $request->namakec,
                'kota'           => $request->namadati2,
                'nokartu_bpjs'   => $request->nomorkartu,
            ]);

            return $this->sendResponse($request, ['message' => 'Pasien baru berhasil disimpan'], 200);
        } catch (Exception $e) {
            return $this->sendError($request, $e->getMessage(), 201);
        }
    }

    /**
     * PUT /antrean/batal — batalkan antrean.
     * Body: nomorkartu, kodepoli, tanggalperiksa, keterangan
     */
    public function batal(Request $request)
    {
        $auth = $this->authenticate($request);
        if ($auth instanceof JsonResponse) return $auth;

        $validator = Validator::make($request->all(), [
            'nomorkartu'     => 'required|numeric|digits:13',
            'kodepoli'       => 'required',
            'tanggalperiksa' => 'required|date|date_format:Y-m-d',
            'keterangan'     => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->sendError($request, $validator->errors()->first(), 201);
        }

        $booking = DB::table('referensi_mobilejkn_bpjs')
            ->where('nomorkartu', $request->nomorkartu)
            ->where('kodepoli', $request->kodepoli)
            ->where('tanggalperiksa', $request->tanggalperiksa)
            ->where('status', '!=', 'Batal')
            ->first();

        if (!$booking) {
            return $this->sendError($request, 'Data booking tidak ditemukan atau sudah dibatalkan', 201);
        }
        if ($booking->status === 'Checkin') {
            return $this->sendError($request, 'Pasien sudah checkin, tidak bisa dibatalkan', 201);
        }

        DB::table('referensi_mobilejkn_bpjs')
            ->where('nobooking', $booking->nobooking)
            ->update([
                'status'           => 'Batal',
                'keterangan_batal' => Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s') . ' — ' . $request->keterangan,
            ]);

        return $this->sendResponse($request, ['message' => 'Antrean berhasil dibatalkan'], 200);
    }
}
