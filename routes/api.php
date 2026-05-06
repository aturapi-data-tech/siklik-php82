<?php

use App\Http\Controllers\AntreanFktpController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — BPJS Antrean FKTP Inbound (Mobile JKN)
|--------------------------------------------------------------------------
|
| Endpoint yang disediakan klinik untuk dipanggil BPJS Mobile JKN.
| Auth: GET /auth (x-username/x-password) → JWT, lalu endpoint lain pakai
| header x-username + x-token. Kredensial diset di .env via
| ANTREAN_USERNAME / ANTREAN_PASSWORD; secret JWT via BPJS_JWT_SECRET.
|
*/

Route::get('/auth', [AntreanFktpController::class, 'auth']);

Route::prefix('antrean')->group(function () {
    Route::get('/status/{kdPoli}/{tgl}',                 [AntreanFktpController::class, 'status']);
    Route::post('/',                                      [AntreanFktpController::class, 'ambil']);
    Route::get('/sisapeserta/{noKartu}/{kdPoli}/{tgl}',  [AntreanFktpController::class, 'sisa']);
    Route::put('/batal',                                  [AntreanFktpController::class, 'batal']);
});

Route::post('/peserta', [AntreanFktpController::class, 'pasienBaru']);

// Referensi master untuk BPJS Mobile JKN
Route::get('/ref/dokter/kodepoli/{kodepoli}/tanggal/{tanggal}', [AntreanFktpController::class, 'refDokter']);
