<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PeriodeSenatController;
use App\Http\Controllers\KomisiController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\StrukturOrganisasiController;
use App\Http\Controllers\KategoriAspirasiController;
use App\Http\Controllers\AspirasiController;
use App\Http\Controllers\TanggapanAspirasiController;
use App\Http\Controllers\KegiatanController;
use App\Http\Controllers\JenisRapatController;
use App\Http\Controllers\RapatController;
use App\Http\Controllers\AnggotaRapatController;
use App\Http\Controllers\AnggotaKegiatanController;
use App\Http\Controllers\MateriRapatController;
use App\Http\Controllers\NotulensiController;
use App\Http\Controllers\VotingController;
use App\Http\Controllers\OpsiVotingController;
use App\Http\Controllers\SuaraVotingController;
use App\Http\Controllers\PeraturanController;
use App\Http\Controllers\BeritaController;
use App\Http\Controllers\UserController;

// Tambahkan middleware auth (mis. 'auth:sanctum') sesuai kebutuhan
Route::group([], function () {
    Route::apiResource('periode-senat', PeriodeSenatController::class);
    Route::apiResource('komisi', KomisiController::class);
    Route::apiResource('enrollment', EnrollmentController::class);
    Route::apiResource('struktur-organisasi', StrukturOrganisasiController::class);
    Route::apiResource('kategori-aspirasi', KategoriAspirasiController::class);
    Route::apiResource('aspirasi', AspirasiController::class);
    Route::apiResource('tanggapan-aspirasi', TanggapanAspirasiController::class);
    Route::apiResource('kegiatan', KegiatanController::class);
    Route::apiResource('jenis-rapat', JenisRapatController::class);
    Route::apiResource('rapat', RapatController::class);
    Route::apiResource('anggota-rapat', AnggotaRapatController::class);
    Route::apiResource('anggota-kegiatan', AnggotaKegiatanController::class);
    Route::apiResource('materi-rapat', MateriRapatController::class);
    Route::apiResource('notulensi', NotulensiController::class);
    Route::apiResource('voting', VotingController::class);
    Route::apiResource('opsi-voting', OpsiVotingController::class);
    Route::apiResource('suara-voting', SuaraVotingController::class);
    Route::apiResource('peraturan', PeraturanController::class);
    Route::apiResource('berita', BeritaController::class);
    Route::apiResource('user', UserController::class);
});
