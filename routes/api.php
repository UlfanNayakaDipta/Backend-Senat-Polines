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
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PresensiRapatController;

// Endpoint Publik untuk Frontend
Route::get('committees', [KomisiController::class, 'publicCommittees']);

// Tambahkan middleware auth (mis. 'auth:sanctum') sesuai kebutuhan
Route::group([], function () {
    // Autentikasi Pengguna
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    Route::apiResource('periode-senat', PeriodeSenatController::class);
    Route::apiResource('komisi', KomisiController::class);
    Route::apiResource('enrollment', EnrollmentController::class);
    Route::apiResource('struktur-organisasi', StrukturOrganisasiController::class);
    Route::apiResource('kategori-aspirasi', KategoriAspirasiController::class);
    Route::apiResource('aspirasi', AspirasiController::class);
    Route::apiResource('tanggapan-aspirasi', TanggapanAspirasiController::class);
    Route::apiResource('kegiatan', KegiatanController::class);
    Route::apiResource('jenis-rapat', JenisRapatController::class);

    // Presensi QR & Kuorum Rapat
    Route::get('rapat/active', [PresensiRapatController::class, 'getActive']);
    Route::post('rapat/presensi', [PresensiRapatController::class, 'submitPresensi']);
    Route::post('rapat/{id}/generate-qr', [PresensiRapatController::class, 'generateQr']);
    Route::post('rapat/{id}/freeze-presensi', [PresensiRapatController::class, 'freezePresensi']);
    Route::get('rapat/{id}/live-attendance', [PresensiRapatController::class, 'getLiveAttendance']);
    Route::put('rapat/{id}/anggota/{id_user}/validasi', [PresensiRapatController::class, 'validasiManual']);

    Route::apiResource('rapat', RapatController::class);
    Route::apiResource('anggota-rapat', AnggotaRapatController::class);
    Route::apiResource('anggota-kegiatan', AnggotaKegiatanController::class);
    Route::apiResource('materi-rapat', MateriRapatController::class);
    Route::apiResource('notulensi', NotulensiController::class);
    Route::post('voting/{id}/buka', [VotingController::class, 'buka']);
    Route::post('voting/{id}/tutup', [VotingController::class, 'tutup']);
    Route::get('voting/{id}/rekap', [VotingController::class, 'rekap']);
    Route::apiResource('voting', VotingController::class);
    Route::apiResource('opsi-voting', OpsiVotingController::class);
    Route::apiResource('suara-voting', SuaraVotingController::class);
    Route::apiResource('peraturan', PeraturanController::class);
    Route::apiResource('berita', BeritaController::class);
    Route::apiResource('user', UserController::class);

    // AI Chatbot Aspirasi & Layanan Informasi Senat
    Route::post('chatbot/chat', [\App\Http\Controllers\ChatbotController::class, 'chat']);
});
