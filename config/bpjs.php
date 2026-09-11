<?php

/*
|--------------------------------------------------------------------------
| BPJS Kesehatan — kredensial & jalur keluar (egress) SEMUA panggilan API BPJS
|--------------------------------------------------------------------------
| Siklik adalah klinik FKTP. Layanan BPJS yang dipakai:
|   - PCare        : pendaftaran & kunjungan FKTP (outbound, PcareTrait)
|   - Antrean FKTP : dua arah —
|                    * outbound (klinik → BPJS): AntrianTrait, grup `antrian`
|                    * inbound  (Mobile JKN → klinik): klinik jadi server JWT,
|                      grup `antrean_fktp` (AntreanFktpController + TraitJWTAntreanFktp)
|   - i-Care       : riwayat pelayanan peserta (outbound, iCareTrait)
|
| SEMUA nilai berasal dari .env supaya tiap lingkungan (dev/prod) mengatur sendiri.
| Dibaca lewat config() — JANGAN env() langsung di dalam app/, karena env()
| mengembalikan null setelah `php artisan config:cache`.
|
| Kebijakan BPJS (Formulir Pengajuan Akses Bridging SIM, berlaku 2 Sep 2026 dan
| diajukan lewat ITSM BPJS): API hanya melayani permintaan dari IP publik yang
| di-whitelist. Bila yang didaftarkan adalah IP VPS (bukan IP klinik), seluruh
| lalu lintas BPJS harus KELUAR lewat VPS itu — lewat forward proxy (Squid).
| Lihat grup `proxy` di bawah dan docs/bpjs-whitelist-ip-proxy.md.
|
| Grup proxy/timeout dipakai App\Support\Bpjs\BpjsHttp — satu-satunya pembuat
| PendingRequest untuk PCare, Antrean, dan i-Care. SATUSEHAT TIDAK lewat sini
| (lihat config/satusehat.php).
*/

return [

    /*
    |----------------------------------------------------------------------
    | PCare (BPJS FKTP) — outbound
    |----------------------------------------------------------------------
    */
    'pcare' => [
        'url'        => env('PCARE_URL'),
        'cons_id'    => env('PCARE_CONS_ID'),
        'secret_key' => env('PCARE_SECRET_KEY'),
        'user_key'   => env('PCARE_USER_KEY'),
        'username'   => env('PCARE_USERNAME'),
        'password'   => env('PCARE_PASSWORD'),
        // Keterangan faskes & kode provider (8 digit, mis. 0184B007) untuk payload PCare.
        'desc'       => env('PCARE_DESC'),
        'provider'   => env('PCARE_PROVIDER'),
    ],

    /*
    |----------------------------------------------------------------------
    | Antrean — outbound (klinik → server BPJS), dipakai AntrianTrait
    |----------------------------------------------------------------------
    */
    'antrian' => [
        'url'        => env('ANTRIAN_URL'),
        'cons_id'    => env('ANTRIAN_CONS_ID'),
        'secret_key' => env('ANTRIAN_SECRET_KEY'),
        'user_key'   => env('ANTRIAN_USER_KEY'),
        // Endpoint antrean BPJS lebih lambat dari PCare; batas waktu sendiri
        // supaya tidak ikut turun saat BPJS_HTTP_TIMEOUT dikecilkan.
        'timeout'    => (int) env('ANTRIAN_HTTP_TIMEOUT', 15),
    ],

    /*
    |----------------------------------------------------------------------
    | Antrean FKTP — inbound (Mobile JKN → klinik). Klinik = server JWT.
    |----------------------------------------------------------------------
    | Kredensial ini yang didaftarkan ke BPJS supaya mereka bisa hit
    | /api/auth → JWT → endpoint antrean milik klinik.
    */
    'antrean_fktp' => [
        'username'   => env('ANTREAN_USERNAME'),
        'password'   => env('ANTREAN_PASSWORD'),
        // Kunci penanda tangan JWT yang diterbitkan klinik untuk BPJS.
        'jwt_secret' => env('BPJS_JWT_SECRET', 'siklik-fktp-fallback-change-me'),
    ],

    /*
    |----------------------------------------------------------------------
    | i-Care JKN — outbound (riwayat pelayanan peserta)
    |----------------------------------------------------------------------
    */
    'icare' => [
        'url'        => env('ICARE_URL'),
        'cons_id'    => env('ICARE_CONS_ID'),
        'secret_key' => env('ICARE_SECRET_KEY'),
        'user_key'   => env('ICARE_USER_KEY'),
    ],

    /*
    |----------------------------------------------------------------------
    | Proxy keluar (whitelist IP BPJS)
    |----------------------------------------------------------------------
    */

    // SAKLAR: true = panggilan BPJS keluar lewat proxy_url; false (bawaan) = langsung
    // seperti sebelum ada kebijakan whitelist. Dibuat terpisah dari URL supaya produksi
    // bisa menyimpan URL proxy sejak awal tanpa memakainya, lalu dinyalakan dengan satu
    // baris saat BPJS mulai menegakkan whitelist.
    'proxy_aktif' => filter_var(env('BPJS_PROXY_AKTIF', false), FILTER_VALIDATE_BOOL),

    // URL forward proxy, mis. http://user:pass@203.0.113.10:3128 . Dipakai hanya bila proxy_aktif = true.
    'proxy_url' => env('BPJS_PROXY_URL'),

    // IP publik yang didaftarkan ke BPJS — dipakai `php artisan bpjs:cek-proxy`
    // untuk memastikan IP keluar benar-benar IP itu.
    'ip_whitelist' => env('BPJS_IP_WHITELIST'),

    /*
    |----------------------------------------------------------------------
    | Batas waktu HTTP (detik)
    |----------------------------------------------------------------------
    | Panggilan sinkron tanpa batas = layar membeku saat BPJS gangguan;
    | jangan dinaikkan tanpa alasan.
    */
    'timeout' => (int) env('BPJS_HTTP_TIMEOUT', 10),
    'connect_timeout' => (int) env('BPJS_HTTP_CONNECT_TIMEOUT', 3),

    // Layanan penunjuk IP publik untuk bpjs:cek-proxy.
    'ip_echo_url' => env('BPJS_IP_ECHO_URL', 'https://api.ipify.org?format=json'),
];
