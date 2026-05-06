<?php

namespace App\Http\Traits\BPJS;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * JWT helper + response/log helper untuk webservice INBOUND Antrean FKTP.
 *
 * Konsep di-mirror dari App\Http\Traits\TraitJWTRsiMadinah (project rsimadinah)
 * — JWT custom HS256 (tanpa lib eksternal), envelope BPJS standard,
 * audit log per request.
 *
 * Beda dengan rsimadinah:
 *   - Private key: dari env BPJS_JWT_SECRET (bukan hardcoded)
 *   - Validasi user: cocokkan ke env ANTREAN_USERNAME/PASSWORD
 *     (klinik FKTP cuma 1 consumer = BPJS, gak butuh user table)
 *   - Audit log: ke WEB_LOG_STATUS (sudah ada di Oracle siklik)
 *     bukan api_log_status (gak ada di siklik)
 *
 * Flow auth (BPJS sebagai client → klinik sebagai server):
 *   1. BPJS hit GET /auth dengan header x-username + x-password
 *      → controller cek credentials, panggil createToken() → JWT
 *   2. BPJS hit endpoint lain dengan header x-username + x-token
 *      → controller authenticate(): cek username + cektoken() JWT
 */
trait TraitJWTAntreanFktp
{
    /* =========================================================
     | JWT ENCODE / DECODE  (HS256, custom — no external lib)
     * ========================================================= */

    private function urlsafeB64Encode($input)
    {
        return str_replace(['+/', '='], ['-_', ''], base64_encode($input));
    }

    private function urlsafeB64Decode($input)
    {
        return str_replace(['-_', ''], ['+/', '='], base64_decode($input));
    }

    private function signnature($msg, $key, $alg = 'HS256')
    {
        list($function, $algorithm) = $this->algoritm($alg);
        switch ($function) {
            case 'hash_hmac':
                return hash_hmac($algorithm, $msg, $key, true);
        }
    }

    private function encode_jwt($payload, $key, $alg = 'HS256')
    {
        $header   = json_encode(['typ' => 'JWT', 'alg' => $alg]);
        $payload  = json_encode($payload);
        $segments = [];
        $segments[] = $this->urlsafeB64Encode($header);
        $segments[] = $this->urlsafeB64Encode($payload);
        $sign_input = implode('.', $segments);
        $signature  = $this->signnature($sign_input, $key, $alg);
        $segments[] = $this->urlsafeB64Encode($signature);
        return implode('.', $segments);
    }

    private function decode_jwt($token, $key, array $allowed_algs = [])
    {
        if (empty($key)) {
            throw new \Exception('Key may not be empty');
        }

        $tks = explode('.', $token);
        if (\count($tks) != 3) {
            throw new \Exception('Wrong number of segments');
        }

        list($headb64, $bodyb64, $cryptob64) = $tks;
        $header  = json_decode($this->urlsafeB64Decode($headb64));
        $payload = json_decode($this->urlsafeB64Decode($bodyb64));

        if (null === $header) {
            throw new \Exception('Invalid header encoding');
        }
        if (null === $payload) {
            throw new \Exception('Invalid claims encoding');
        }
        if (false === ($sig = $this->urlsafeB64Decode($cryptob64))) {
            throw new \Exception('Invalid signature encoding');
        }
        if (empty($header->alg)) {
            throw new \Exception('Empty algorithm');
        }
        if (empty($this->algoritm($header->alg))) {
            throw new \Exception('Algorithm not supported');
        }
        if (!\in_array($header->alg, $allowed_algs)) {
            throw new \Exception('Algorithm not allowed');
        }
        if (!$this->verify("$headb64.$bodyb64", $sig, $key, $header->alg)) {
            throw new \Exception('Signature verification failed');
        }
        // exp = durasi (detik); valid kalau (now - iat) < exp
        if (isset($payload->exp) && (time() - $payload->iat) >= $payload->exp) {
            throw new \Exception('Expired token');
        }

        return $payload;
    }

    private function algoritm($alg)
    {
        $supported_algs = [
            'HS256' => ['hash_hmac', 'SHA256'],
            'HS384' => ['hash_hmac', 'SHA384'],
            'HS512' => ['hash_hmac', 'SHA512'],
        ];
        return $supported_algs[$alg] ?? null;
    }

    private function verify($msg, $signature, $key, $alg)
    {
        if (empty($this->algoritm($alg))) {
            throw new \Exception('Algorithm not supported');
        }
        [, $algorithm] = $this->algoritm($alg);
        $hash = hash_hmac($algorithm, $msg, $key, true);
        return hash_equals($signature, $hash);
    }

    /* =========================================================
     | TOKEN PUBLIC API
     * ========================================================= */

    private function privateKey(): string
    {
        return env('BPJS_JWT_SECRET', 'siklik-fktp-fallback-change-me');
    }

    private function payloadtoken($username): array
    {
        return [
            'iss'  => 'Siklik FKTP API',
            'aud'  => 'BPJS Mobile JKN',
            'iat'  => time(),
            'exp'  => 43200, // 12 jam (detik)
            'data' => ['username' => $username],
        ];
    }

    /**
     * Validasi user/pass yang dikirim BPJS terhadap kredensial yang
     * kita daftarkan ke BPJS (disimpan di .env).
     */
    public function checkUser($username, $password): bool
    {
        return $username === env('ANTREAN_USERNAME')
            && $password === env('ANTREAN_PASSWORD');
    }

    /**
     * Generate JWT signed dengan private key.
     * Return 'x' (sentinel) kalau credentials gagal — match pola rsimadinah.
     */
    public function createToken($username, $password)
    {
        if ($this->checkUser($username, $password)) {
            return $this->encode_jwt($this->payloadtoken($username), $this->privateKey());
        }
        return 'x';
    }

    /**
     * Validasi JWT.
     *
     * Return:
     *   - true  : signature & exp valid
     *   - array : envelope error siap di-return ke client
     *
     * Match shape dengan rsimadinah supaya controller bisa pakai pola
     * if (cektoken() !== true) return cektoken().
     */
    public function cektoken($token)
    {
        try {
            if ($this->decode_jwt($token, $this->privateKey(), ['HS256'])) {
                return true;
            }
        } catch (\Exception $e) {
            return [
                'metadata' => [
                    'message' => $e->getMessage(),
                    'code'    => 201,
                ],
            ];
        }
    }

    /* =========================================================
     | RESPONSE HELPERS  (BPJS envelope + audit log WEB_LOG_STATUS)
     * ========================================================= */

    public function sendResponse($request, $data, int $code = 200)
    {
        $response = [
            'response' => $data,
            'metadata' => [
                'message' => 'Ok',
                'code'    => $code,
            ],
        ];

        DB::table('web_log_status')->insert([
            'code'                => $code,
            'date_ref'            => Carbon::now(env('APP_TIMEZONE')),
            'response'            => json_encode($response, JSON_UNESCAPED_UNICODE),
            'http_req'            => $request->fullUrl(),
            'requestTransferTime' => null,
        ]);

        return response()->json($response, $code);
    }

    public function sendError($request, $error, $code = 404)
    {
        $code = $code ?? 404;
        $response = [
            'metadata' => [
                'message' => $error,
                'code'    => $code,
            ],
        ];

        DB::table('web_log_status')->insert([
            'code'                => $code,
            'date_ref'            => Carbon::now(env('APP_TIMEZONE')),
            'response'            => json_encode($response, JSON_UNESCAPED_UNICODE),
            'http_req'            => $request->fullUrl(),
            'requestTransferTime' => null,
        ]);

        return response()->json($response, $code);
    }

    /* =========================================================
     | UTIL
     * ========================================================= */

    public function hariIndo($hariInggris)
    {
        return match ($hariInggris) {
            'Sunday'    => 'Minggu',
            'Monday'    => 'Senin',
            'Tuesday'   => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday'  => 'Kamis',
            'Friday'    => 'Jumat',
            'Saturday'  => 'Sabtu',
            default     => 'hari tidak valid',
        };
    }
}
