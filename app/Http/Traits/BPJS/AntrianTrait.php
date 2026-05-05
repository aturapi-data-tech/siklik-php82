<?php

namespace App\Http\Traits\BPJS;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

/**
 * BPJS Antrean RS — outbound (klinik/RS → server BPJS).
 *
 * SLIM PORT dari sirus-php82/AntrianTrait.php — hanya method yang dibutuhkan
 * komponen jadwal-mingguan: antrianSignature(), antrianStringDecrypt(), ref_jadwal_dokter().
 *
 * NOTE: signature/stringDecrypt di-prefix `antrian` supaya tidak bentrok dgn
 * PcareTrait yang punya method nama sama (dipakai bersamaan di daftar-rj-actions).
 *
 * Auth pattern (beda dari trait FKTP inbound):
 *   - HMAC SHA256 signature: hash_hmac('sha256', cons_id.'&'.timestamp, secret_key)
 *   - Header: x-cons-id, x-timestamp, x-signature, user_key
 *   - Response BPJS: AES-256-CBC encrypted + LZString compressed
 *     decrypt_key = cons_id . secret_key . timestamp
 *
 * Env yang dibutuhkan (didapat dari BPJS saat onboarding faskes):
 *   - ANTRIAN_URL          (mis. https://apijkn.bpjs-kesehatan.go.id/antreanrs_dev/)
 *   - ANTRIAN_CONS_ID
 *   - ANTRIAN_SECRET_KEY
 *   - ANTRIAN_USER_KEY
 *
 * Method lain (tambah_antrean, batal_antrean, dst.) sengaja TIDAK di-port
 * karena siklik klinik pratama murni inbound — gak push ke BPJS.
 */
trait AntrianTrait
{
    /* =========================================================
     | AUTH SIGNATURE
     * ========================================================= */

    private static function antrianSignature(): array
    {
        $cons_id   = env('ANTRIAN_CONS_ID');
        $secretKey = env('ANTRIAN_SECRET_KEY');
        $userkey   = env('ANTRIAN_USER_KEY');

        date_default_timezone_set('UTC');
        $tStamp    = strval(time() - strtotime('1970-01-01 00:00:00'));
        $signature = hash_hmac('sha256', $cons_id . '&' . $tStamp, $secretKey, true);
        $encoded   = base64_encode($signature);

        return [
            'user_key'    => $userkey,
            'x-cons-id'   => $cons_id,
            'x-timestamp' => $tStamp,
            'x-signature' => $encoded,
            'decrypt_key' => $cons_id . $secretKey . $tStamp,
        ];
    }

    /**
     * Decrypt response BPJS (AES-256-CBC + LZString decompress).
     * Key derivation: hex2bin(sha256(decrypt_key)) untuk key & iv (first 16 bytes).
     */
    private static function antrianStringDecrypt(string $key, string $string): string
    {
        $key_hash = hex2bin(hash('sha256', $key));
        $iv       = substr($key_hash, 0, 16);
        $decoded  = openssl_decrypt(base64_decode($string), 'AES-256-CBC', $key_hash, OPENSSL_RAW_DATA, $iv);
        return $decoded === false
            ? ''
            : (\LZCompressor\LZString::decompressFromEncodedURIComponent($decoded) ?? '');
    }

    /* =========================================================
     | LOG (audit trail outbound — beda dari log inbound JWT trait)
     * ========================================================= */

    private static function logWebStatus(int $code, string $url, $payload, ?float $transferTime): void
    {
        DB::table('web_log_status')->insert([
            'code'                => $code,
            'date_ref'            => Carbon::now(env('APP_TIMEZONE')),
            'response'            => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'http_req'            => $url,
            'requestTransferTime' => $transferTime,
        ]);
    }

    /* =========================================================
     | ENDPOINT: ref_jadwal_dokter
     | GET {ANTRIAN_URL}/jadwaldokter/kodepoli/{kodePoli}/tanggal/{tgl}
     * ========================================================= */

    /**
     * Ambil list dokter + jadwal di poli BPJS pada tanggal tertentu.
     *
     * Return array (bukan JsonResponse — supaya gampang di-loop di Livewire):
     *   ['ok' => bool, 'code' => int, 'msg' => string, 'list' => array]
     *
     * Item di 'list': namadokter, kodedokter, jampraktek, kapasitas.
     */
    public static function ref_jadwal_dokter(string $kodePoli, string $tgl): array
    {
        $validator = Validator::make(
            ['kodePoli' => $kodePoli, 'tanggal' => $tgl],
            [
                'kodePoli' => 'required',
                'tanggal'  => 'required|date|date_format:Y-m-d',
            ]
        );
        if ($validator->fails()) {
            return ['ok' => false, 'code' => 201, 'msg' => $validator->errors()->first(), 'list' => []];
        }

        $url = rtrim(env('ANTRIAN_URL'), '/') . '/jadwaldokter/kodepoli/' . $kodePoli . '/tanggal/' . $tgl;

        try {
            $signature = self::antrianSignature();
            $response  = Http::timeout(15)->withHeaders($signature)->get($url);
            $transfer  = $response->transferStats?->getTransferTime();

            if ($response->failed()) {
                self::logWebStatus($response->status(), $url, $response->json(), $transfer);
                return ['ok' => false, 'code' => $response->status(), 'msg' => $response->reason() ?: 'HTTP error', 'list' => []];
            }

            $code = (int) $response->json('metadata.code');
            $msg  = (string) ($response->json('metadata.message') ?? '');

            // BPJS sukses pakai code 200 atau 1 tergantung endpoint
            if ($code !== 200 && $code !== 1) {
                self::logWebStatus($code, $url, $response->json(), $transfer);
                return ['ok' => false, 'code' => $code, 'msg' => $msg, 'list' => []];
            }

            $rawResp = $response->json('response');
            // Beberapa endpoint BPJS return string encrypted, beberapa return plain object
            if (is_string($rawResp)) {
                $decrypt = self::antrianStringDecrypt($signature['decrypt_key'], $rawResp);
                $data    = json_decode($decrypt, true);
            } else {
                $data = $rawResp;
            }

            self::logWebStatus($code, $url, ['msg' => $msg, 'count' => count($data['list'] ?? [])], $transfer);

            return ['ok' => true, 'code' => $code, 'msg' => $msg, 'list' => $data['list'] ?? []];
        } catch (Exception $e) {
            self::logWebStatus(408, $url, ['error' => $e->getMessage()], null);
            return ['ok' => false, 'code' => 408, 'msg' => $e->getMessage(), 'list' => []];
        }
    }

    /* =========================================================
     | ENDPOINT: tambah_antrean (push antrean ke BPJS)
     | POST {ANTRIAN_URL}/antrean/add
     |
     | Spec FKTP klinik pratama lebih ringkas dari RS:
     |   nomorkartu, nik, nohp, kodepoli, namapoli, norm, tanggalperiksa,
     |   kodedokter, namadokter, jampraktek, nomorantrean, angkaantrean, keterangan
     * ========================================================= */
    public static function tambah_antrean(array $data): array
    {
        $payload = [
            'nomorkartu'     => $data['nomorkartu']     ?? '',
            'nik'            => $data['nik']            ?? '',
            'nohp'           => $data['nohp']           ?? '',
            'kodepoli'       => $data['kodepoli']       ?? '',
            'namapoli'       => $data['namapoli']       ?? '',
            'norm'           => $data['norm']           ?? '',
            'tanggalperiksa' => $data['tanggalperiksa'] ?? '',
            'kodedokter'     => is_numeric($data['kodedokter'] ?? null) ? (int) $data['kodedokter'] : ($data['kodedokter'] ?? ''),
            'namadokter'     => $data['namadokter']     ?? '',
            'jampraktek'     => $data['jampraktek']     ?? '',
            'nomorantrean'   => $data['nomorantrean']   ?? '',
            'angkaantrean'   => (int) ($data['angkaantrean'] ?? 0),
            'keterangan'     => $data['keterangan']     ?? '',
        ];

        $url = rtrim(env('ANTRIAN_URL'), '/') . '/antrean/add';

        try {
            $signature = self::antrianSignature();
            $response  = Http::timeout(15)->withHeaders($signature)->post($url, $payload);
            $transfer  = $response->transferStats?->getTransferTime();

            if ($response->failed()) {
                self::logWebStatus($response->status(), $url, ['payload' => $payload, 'response' => $response->json()], $transfer);
                return ['ok' => false, 'code' => $response->status(), 'msg' => $response->reason() ?: 'HTTP error'];
            }

            $code = (int) $response->json('metadata.code');
            $msg  = (string) ($response->json('metadata.message') ?? '');
            $okCodes = [200, 1];

            self::logWebStatus($code, $url, ['payload' => $payload, 'metadata' => ['code' => $code, 'message' => $msg]], $transfer);

            return [
                'ok'   => in_array($code, $okCodes, true),
                'code' => $code,
                'msg'  => $msg,
            ];
        } catch (Exception $e) {
            self::logWebStatus(408, $url, ['payload' => $payload, 'error' => $e->getMessage()], null);
            return ['ok' => false, 'code' => 408, 'msg' => $e->getMessage()];
        }
    }

    /* =========================================================
     | ENDPOINT: panggil_antrean (update status hadir/tidak)
     | POST {ANTRIAN_URL}/antrean/panggil
     |
     | Body:
     |   tanggalperiksa, kodepoli, nomorkartu, status (1=Hadir, 2=Tidak), waktu (ms)
     * ========================================================= */
    public static function panggil_antrean(string $tanggalperiksa, string $kodepoli, string $nomorkartu, int $status, int $waktuMs): array
    {
        $payload = [
            'tanggalperiksa' => $tanggalperiksa,
            'kodepoli'       => $kodepoli,
            'nomorkartu'     => $nomorkartu,
            'status'         => $status,
            'waktu'          => $waktuMs,
        ];

        $url = rtrim(env('ANTRIAN_URL'), '/') . '/antrean/panggil';

        try {
            $signature = self::antrianSignature();
            $response  = Http::timeout(15)->withHeaders($signature)->post($url, $payload);
            $transfer  = $response->transferStats?->getTransferTime();

            if ($response->failed()) {
                self::logWebStatus($response->status(), $url, ['payload' => $payload, 'response' => $response->json()], $transfer);
                return ['ok' => false, 'code' => $response->status(), 'msg' => $response->reason() ?: 'HTTP error'];
            }

            $code = (int) $response->json('metadata.code');
            $msg  = (string) ($response->json('metadata.message') ?? '');
            $okCodes = [200, 1];

            self::logWebStatus($code, $url, ['payload' => $payload, 'metadata' => ['code' => $code, 'message' => $msg]], $transfer);

            return [
                'ok'   => in_array($code, $okCodes, true),
                'code' => $code,
                'msg'  => $msg,
            ];
        } catch (Exception $e) {
            self::logWebStatus(408, $url, ['payload' => $payload, 'error' => $e->getMessage()], null);
            return ['ok' => false, 'code' => 408, 'msg' => $e->getMessage()];
        }
    }
}
