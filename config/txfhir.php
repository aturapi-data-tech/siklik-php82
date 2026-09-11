<?php

return [

    /*
    |--------------------------------------------------------------------------
    | FHIR Terminology Server (tx.fhir.org)
    |--------------------------------------------------------------------------
    |
    | Dipakai SnomedTrait/LoincTrait untuk pencarian & lookup kode terminologi
    | (LOV SNOMED keluhan utama, dll.). Cache hasil pencarian disimpan di
    | tabel skmst_snomed_codes.
    |
    */

    'base_url' => env('TXFHIR_BASE_URL', 'http://tx.fhir.org/r4'),

    /*
    |--------------------------------------------------------------------------
    | Pin edisi SNOMED CT (filter effectiveTime)
    |--------------------------------------------------------------------------
    |
    | Server terminologi SATUSEHAT memakai rilis SNOMED yang LEBIH TUA daripada
    | edisi terbaru tx.fhir.org. Konsep baru (mis. 1306548008, effective
    | 2024-04-01) valid di tx.fhir.org tetapi ditolak SATUSEHAT dengan
    | OperationOutcome "Code not found ... (RuleNumber: 10003)".
    |
    | Dengan pin versi di bawah, $expand (pencarian LOV) dan $lookup (validasi)
    | hanya mengenal konsep yang sudah ada pada rilis tersebut — konsep ber-
    | effectiveTime lebih baru otomatis tersaring. Pilih versi International
    | Edition yang di-host tx.fhir.org (cek /metadata?mode=terminology).
    |
    | Kosongkan (null) untuk memakai edisi terbaru tanpa filter.
    |
    | Kode yang terlanjur masuk cache SEBELUM pin dipasang dibersihkan dengan
    |   php artisan snomed:bersihkan-cache --dry-run
    |
    */

    'snomed_version' => env(
        'TXFHIR_SNOMED_VERSION',
        'http://snomed.info/sct/900000000000207008/version/20240201'
    ),

];
