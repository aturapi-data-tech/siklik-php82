<?php

namespace App\Http\Traits\SATUSEHAT;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use Exception;

trait SatuSehatTrait
{

    // OAuth2 Configuration
    protected $authUrl;
    protected $clientId;
    protected $clientSecret;
    protected $baseUrl;
    protected $organizationId;
    // Dideklarasikan eksplisit: dipakai OrganizationTrait dan PHP 8.2 memberi
    // peringatan deprecated untuk properti dinamis.
    protected $organizationName;


    public function initializeSatuSehat()
    {
        $this->authUrl         = config('satusehat.auth_url');
        $this->clientId        = config('satusehat.client_id');
        $this->clientSecret    = config('satusehat.secret_id');
        $this->baseUrl         = config('satusehat.base_url');
        $this->organizationId  = config('satusehat.organization_id');
        $this->organizationName = config('satusehat.organization_name');
    }

    /**
     * Get OAuth2 Token
     */
    protected function getAccessToken()
    {
        return Cache::remember('satusehat_access_token', 3500, function () {
            $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
            $url = config('satusehat.auth_url') . "accesstoken?grant_type=client_credentials";

            $response = Http::timeout((int) config('satusehat.timeout'))
                ->withHeaders($headers)
                ->asForm()
                ->post($url, [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret
                ]);

            if ($response->successful()) {
                return $response->json()['access_token'];
            }

            throw new \Exception('Failed to get access token: ' . $response->body());
        });
    }

    /**
     * Make API Request to SatuSehat
     */
    protected function makeRequest($method, $endpoint, $data = [])
    {

        $token = $this->getAccessToken();
        $url = $this->baseUrl . $endpoint;

        // Base client: timeout, bearer token, common headers
        $client = Http::timeout((int) config('satusehat.timeout'))
            ->withToken($token)
            ->withHeaders([
                'Organization-Id' => $this->organizationId,
            ]);

        // Eksekusi HTTP — dibungkus try/catch supaya koneksi gagal pun tetap ke-log.
        try {
            // Untuk GET: query string sudah menempel di $endpoint.
            if (strtolower($method) === 'get') {
                $response = $client->get($url);
            } else {
                // Untuk POST/PUT/PATCH/DELETE: kirim $data sebagai JSON‐body
                $response = $client
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->{$method}($url, $data);
            }
        } catch (\Throwable $e) {
            // Network/timeout — tidak ada $response sama sekali, tapi panggilannya
            // TETAP harus tercatat; tanpa ini kegagalan koneksi tak berjejak.
            $this->logSatuSehat($url, 0, null, $e->getMessage());
            throw $e;
        }

        // Log SELURUH panggilan (sukses maupun 4xx/5xx), bukan hanya yang gagal:
        // saat SATUSEHAT membantah isi kiriman kita, jejak inilah satu-satunya
        // cara membuktikan apa yang benar-benar dikirim dan apa balasannya.
        $this->logSatuSehat(
            $url,
            $response->status(),
            $response->transferStats?->getTransferTime(),
            $response->body()
        );

        if ($response->successful()) {
            return $response->json();
        }
        throw new \Exception('API request failed: ' . $response->body());
    }

    /**
     * Satu baris audit trail panggilan SATUSEHAT ke web_log_status — tabel dan
     * pola kolom yang sama dengan AntrianTrait::logWebStatus() (BPJS).
     *
     * CATATAN siklik: web_log_status di sini TIDAK punya kolom http_payload
     * (sirus punya), jadi yang tercatat baru URL + status + balasan + rtt.
     * Isi payload yang dikirim belum ikut tersimpan — lihat docs/satusehat-api.md.
     *
     * Gagal menulis log TIDAK BOLEH menggagalkan kiriman yang sudah berhasil,
     * jadi seluruhnya dibungkus try/catch.
     */
    private function logSatuSehat(string $url, ?int $code, ?float $rtt, ?string $responseBody): void
    {
        try {
            DB::table('web_log_status')->insert([
                'code'                => $code,
                'date_ref'            => Carbon::now(config('app.timezone')),
                'response'            => $responseBody,
                'http_req'            => $url,
                'requestTransferTime' => $rtt,
            ]);
        } catch (\Throwable) {
            // sengaja diam — audit trail bukan alasan menggagalkan transaksi klinis
        }
    }

    /**
     * Kalimat pendek dan terpakai dari Exception makeRequest(), untuk toast.
     *
     * makeRequest() melempar "API request failed: <body OperationOutcome mentah>".
     * Body itu utuh dan berguna di web_log_status, tapi kalau ditempel apa adanya
     * ke toast, petugas cuma melihat pagar JSON dan pesan aslinya justru terpotong
     * di tengah. Nama diberi akhiran SatuSehat supaya tidak bentrok dengan method
     * ringkasError milik komponen Livewire yang memakai trait ini.
     */
    protected function ringkasErrorSatuSehat(\Throwable $e): string
    {
        $pesan = $e->getMessage();

        if (preg_match('~\{.*\}~s', $pesan, $cocok)) {
            $body = json_decode($cocok[0], true);
            $teks = $body['issue'][0]['details']['text']
                ?? ($body['issue'][0]['diagnostics'] ?? null);
            if (is_string($teks) && $teks !== '') {
                return Str::limit($teks, 160);
            }
        }

        return Str::limit($pesan, 160);
    }
}
