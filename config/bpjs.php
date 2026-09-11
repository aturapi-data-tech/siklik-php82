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
    | SISRUTE FKTP — Rujukan Berbasis Kompetensi Layanan (SRBK), outbound
    |----------------------------------------------------------------------
    | Dipakai App\Http\Traits\BPJS\PcareSisruteTrait (Sisrute/GetKriteriaRujukan,
    | Sisrute/GetFaskesRujukan, Sisrute/postKunjungan, Sisrute/deleteKunjungan).
    |
    | PRASYARAT ADMINISTRATIF (jangan diisi asal — lihat docs/rujukan-kompetensi.md):
    |  1. Cons ID DEV untuk service `pcare-sisrute-rest` DIAJUKAN TERPISAH dari cons ID
    |     PCare biasa. Pengajuan dari faskes ke KC BPJS setempat — untuk klinik ini
    |     KC Tulungagung (wilayah piloting SRBK). Cons ID PCare produksi yang sudah
    |     ada TIDAK otomatis berlaku: balasannya
    |     "Unauthorized! You are not registered for this service!".
    |  2. IP publik pemanggil harus di-whitelist lewat ITSM BPJS (Formulir Pengajuan
    |     Akses Bridging SIM). Belum di-whitelist = "Connection timed out/refused",
    |     bukan pesan error yang menjelaskan.
    |  3. Cons ID dev punya masa berlaku: "Unauthorized! Consumer ID is expired!"
    |     → perpanjangan lewat IT Wilayah BPJS.
    |
    | Bila SISRUTE_* dibiarkan kosong, nilainya JATUH KE `bpjs.pcare.*`. Itu memang
    | disengaja supaya lingkungan yang belum punya kredensial khusus tetap bisa
    | menjalankan halaman (dan gagal dengan pesan BPJS yang jelas), BUKAN tanda
    | bahwa kredensial PCare boleh dipakai untuk SRBK.
    */
    'sisrute' => [
        // Base URL TANPA garis miring penutup; trait menyambung "/Sisrute/<endpoint>".
        // Dev BPJS: https://dvlp.bpjs-kesehatan.go.id/pcare-sisrute-rest/api/v1.0
        'url'        => env('SISRUTE_URL', 'https://dvlp.bpjs-kesehatan.go.id/pcare-sisrute-rest/api/v1.0'),
        'cons_id'    => env('SISRUTE_CONS_ID', env('PCARE_CONS_ID')),
        'secret_key' => env('SISRUTE_SECRET_KEY', env('PCARE_SECRET_KEY')),
        'user_key'   => env('SISRUTE_USER_KEY', env('PCARE_USER_KEY')),
        'username'   => env('SISRUTE_USERNAME', env('PCARE_USERNAME')),
        'password'   => env('SISRUTE_PASSWORD', env('PCARE_PASSWORD')),

        // Mode latihan/simulasi: true = jawaban diambil dari database/fixtures/sisrute/*.json
        // TANPA memanggil jaringan. Tetap dicatat ke web_log_status dengan penanda [SIMULASI].
        'simulasi'   => filter_var(env('SISRUTE_SIMULASI', false), FILTER_VALIDATE_BOOL),

        // Header Content-Type. Server DEV (dvlp) MENOLAK permintaan bila Content-Type
        // dikirim (info BPJS 11 Jun 2026); produksi tetap "application/json".
        // Kosong = header tidak dikirim sama sekali.
        'content_type' => env('SISRUTE_CONTENT_TYPE', ''),

        // Kode aplikasi pada X-authorization "Basic base64(user:pass:kdAplikasi)".
        // PCare FKTP memakai 095 — pcare-sisrute mewarisi aturan header PCare.
        'kd_aplikasi' => env('SISRUTE_KD_APLIKASI', '095'),

        // SATUSEHAT/Sisrute kerap lambat (ada pengalaman 408 "timeout akses ke API
        // Sisrute/Satu Sehat"). Batas waktu sendiri supaya tidak menyeret timeout PCare.
        'timeout'    => (int) env('SISRUTE_HTTP_TIMEOUT', 20),
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
