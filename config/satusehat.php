<?php

/*
|--------------------------------------------------------------------------
| SATUSEHAT (Kemenkes — platform FHIR)
|--------------------------------------------------------------------------
| Dipakai App\Http\Traits\SATUSEHAT\SatuSehatTrait beserta trait resource FHIR
| turunannya (Patient, Encounter, Condition, Observation, dst.).
|
| Semua nilai dari .env dan dibaca lewat config() — JANGAN env() langsung di
| dalam app/, karena env() mengembalikan null setelah `php artisan config:cache`.
|
| CATATAN: SATUSEHAT TIDAK melewati proxy BPJS (config/bpjs.php). Kebijakan
| whitelist IP itu milik BPJS Kesehatan; SATUSEHAT memakai OAuth2 client
| credentials dan keluar langsung dari server aplikasi.
*/

return [

    // Endpoint token OAuth2, diakhiri garis miring — kode menyambung
    // "accesstoken?grant_type=client_credentials" langsung di belakangnya.
    // mis. https://api-satusehat.kemkes.go.id/oauth2/v1/
    'auth_url' => env('SATUSEHAT_AUTH_URL'),

    // Base URL FHIR, diakhiri garis miring; endpoint resource disambung di belakangnya.
    // mis. https://api-satusehat.kemkes.go.id/fhir-r4/v1/
    'base_url' => env('SATUSEHAT_BASE_URL'),

    // Kredensial aplikasi dari Platform SATUSEHAT.
    'client_id' => env('SATUSEHAT_CLIENT_ID'),
    'secret_id' => env('SATUSEHAT_SECRET_ID'),

    // Identitas faskes: id organisasi SATUSEHAT + nama yang dipakai
    // saat membuat/memperbarui resource Organization.
    'organization_id' => env('SATUSEHAT_ORGANIZATION_ID'),
    'organization_name' => env('SATUSEHAT_ORGANIZATION_NAME'),

    // Batas waktu panggilan SATUSEHAT (detik).
    'timeout' => (int) env('SATUSEHAT_HTTP_TIMEOUT', 10),

    // Penanggung jawab unit penunjang — dipakai App\Support\PenanggungJawabPenunjang
    // sebagai ServiceRequest.performer (WAJIB, RuleNumber 10377).
    //
    // Isi dengan skmst_doctors.dr_id petugas yang MENGERJAKAN pemeriksaan. siklik
    // (klinik pratama) tidak punya poli Laboratorium/Radiologi tersendiri, jadi tidak
    // ada cara menyimpulkannya dari data. Dibiarkan kosong pun aman: kartu lab/radiologi
    // jatuh ke dokter pengirim — kiriman jalan, nilainya saja yang belum akurat.
    'pj_lab_dr_id' => env('SATUSEHAT_PJ_LAB_DR_ID'),
    'pj_radiologi_dr_id' => env('SATUSEHAT_PJ_RADIOLOGI_DR_ID'),
];
