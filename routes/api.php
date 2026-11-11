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
use App\Http\Controllers\RapatCatatanController;
use App\Http\Controllers\RapatLampiranController;
use App\Http\Controllers\RapatBerbagiController;
use App\Http\Controllers\RapatRekamanController;
use App\Http\Controllers\ChatbotController;

/*
|--------------------------------------------------------------------------
| Public Routes (Guest / Unauthenticated)
|--------------------------------------------------------------------------
*/
// Endpoint publik untuk frontend (direktori komisi, hanya baca)
Route::get('committees', [KomisiController::class, 'publicCommittees']);

// Authentication
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Public directory & master data read-only
Route::get('periode-senat', [PeriodeSenatController::class, 'index']);
Route::get('periode-senat/{periode_senat}', [PeriodeSenatController::class, 'show']);
Route::get('komisi', [KomisiController::class, 'index']);
Route::get('komisi/{komisi}', [KomisiController::class, 'show']);
Route::get('struktur-organisasi', [StrukturOrganisasiController::class, 'index']);
Route::get('struktur-organisasi/{struktur_organisasi}', [StrukturOrganisasiController::class, 'show']);
Route::get('kategori-aspirasi', [KategoriAspirasiController::class, 'index']);
Route::get('kategori-aspirasi/{kategori_aspirasi}', [KategoriAspirasiController::class, 'show']);

// Public publication read-only
Route::get('berita', [BeritaController::class, 'index']);
Route::get('berita/{berita}', [BeritaController::class, 'show']);
Route::get('peraturan', [PeraturanController::class, 'index']);
Route::get('peraturan/{peraturan}', [PeraturanController::class, 'show']);

// Public aspirations (submit and tracking)
Route::post('aspirasi', [AspirasiController::class, 'store']);
Route::get('aspirasi', [AspirasiController::class, 'index']);
Route::get('aspirasi/{aspirasi}', [AspirasiController::class, 'show']);

// Public AI Chatbot
Route::post('chatbot/chat', [ChatbotController::class, 'chat'])->middleware('throttle:20,1');

// Public meeting & notulensi read (for transparent senat governance)
Route::get('rapat', [RapatController::class, 'index']);
Route::get('rapat/{rapat}', [RapatController::class, 'show']);
Route::get('notulensi', [NotulensiController::class, 'index']);
Route::get('notulensi/{notulensi}', [NotulensiController::class, 'show']);
Route::get('kegiatan', [KegiatanController::class, 'index']);
Route::get('kegiatan/{kegiatan}', [KegiatanController::class, 'show']);
Route::get('jenis-rapat', [JenisRapatController::class, 'index']);
Route::get('jenis-rapat/{jenis_rapat}', [JenisRapatController::class, 'show']);
Route::get('voting', [VotingController::class, 'index']);
Route::get('voting/{voting}', [VotingController::class, 'show']);
Route::get('voting/{id}/rekap', [VotingController::class, 'rekap']);
Route::get('opsi-voting', [OpsiVotingController::class, 'index']);
Route::get('opsi-voting/{opsi_voting}', [OpsiVotingController::class, 'show']);
Route::get('enrollment', [EnrollmentController::class, 'index']);
Route::get('anggota-rapat', [AnggotaRapatController::class, 'index']);
Route::get('anggota-kegiatan', [AnggotaKegiatanController::class, 'index']);
Route::get('materi-rapat', [MateriRapatController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Authenticated Routes (Any valid user via Laravel Sanctum or ApiToken)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    // Presensi kehadiran rapat
    Route::get('rapat/active', [PresensiRapatController::class, 'getActive']);
    Route::post('rapat/presensi', [PresensiRapatController::class, 'submitPresensi']);
    Route::post('rapat/{id}/generate-qr', [PresensiRapatController::class, 'generateQr']);
    Route::post('rapat/{id}/freeze-presensi', [PresensiRapatController::class, 'freezePresensi']);
    Route::get('rapat/{id}/live-attendance', [PresensiRapatController::class, 'getLiveAttendance']);
    Route::put('rapat/{id}/anggota/{id_user}/validasi', [PresensiRapatController::class, 'validasiManual']);

    // Workspace rapat (catatan pribadi, lampiran, berbagi materi)
    Route::get('rapat/{id_rapat}/catatan', [RapatCatatanController::class, 'index']);
    Route::put('rapat/{id_rapat}/catatan', [RapatCatatanController::class, 'upsert']);
    Route::get('rapat/{id_rapat}/lampiran', [RapatLampiranController::class, 'index']);
    Route::post('rapat/{id_rapat}/lampiran', [RapatLampiranController::class, 'store']);
    Route::delete('rapat/{id_rapat}/lampiran/{id_lampiran}', [RapatLampiranController::class, 'destroy']);
    Route::get('rapat/{id_rapat}/berbagi', [RapatBerbagiController::class, 'index']);
    Route::post('rapat/{id_rapat}/berbagi', [RapatBerbagiController::class, 'store']);
    Route::delete('rapat/{id_rapat}/berbagi/{id_berbagi}', [RapatBerbagiController::class, 'destroy']);

    // Voting suara — ballot data is not public (secret ballot)
    Route::get('suara-voting', [SuaraVotingController::class, 'index'])
        ->middleware('role:admin,ketua_senat,sekretaris');
    Route::post('suara-voting', [SuaraVotingController::class, 'store']);

    /*
    |----------------------------------------------------------------------
    | Admin & Pimpinan Senat Protected Actions
    |----------------------------------------------------------------------
    */
    Route::middleware('role:admin,ketua_senat,sekretaris')->group(function () {
        // Voting Management
        Route::post('voting', [VotingController::class, 'store']);
        Route::put('voting/{voting}', [VotingController::class, 'update']);
        Route::delete('voting/{voting}', [VotingController::class, 'destroy']);
        Route::post('voting/{id}/buka', [VotingController::class, 'buka']);
        Route::post('voting/{id}/tutup', [VotingController::class, 'tutup']);
        Route::post('opsi-voting', [OpsiVotingController::class, 'store']);
        Route::put('opsi-voting/{opsi_voting}', [OpsiVotingController::class, 'update']);
        Route::delete('opsi-voting/{opsi_voting}', [OpsiVotingController::class, 'destroy']);
        Route::delete('suara-voting/{suara_voting}', [SuaraVotingController::class, 'destroy']);

        // Manajemen Sidang & Risalah
        Route::post('rapat', [RapatController::class, 'store']);
        Route::put('rapat/{rapat}', [RapatController::class, 'update']);
        Route::delete('rapat/{rapat}', [RapatController::class, 'destroy']);

        Route::post('notulensi', [NotulensiController::class, 'store']);
        Route::put('notulensi/{notulensi}', [NotulensiController::class, 'update']);
        Route::delete('notulensi/{notulensi}', [NotulensiController::class, 'destroy']);

        Route::post('materi-rapat', [MateriRapatController::class, 'store']);
        Route::put('materi-rapat/{materi_rapat}', [MateriRapatController::class, 'update']);
        Route::delete('materi-rapat/{materi_rapat}', [MateriRapatController::class, 'destroy']);

        Route::post('anggota-rapat', [AnggotaRapatController::class, 'store']);
        Route::put('anggota-rapat/{anggota_rapat}', [AnggotaRapatController::class, 'update']);
        Route::delete('anggota-rapat/{anggota_rapat}', [AnggotaRapatController::class, 'destroy']);

        Route::post('jenis-rapat', [JenisRapatController::class, 'store']);
        Route::put('jenis-rapat/{jenis_rapat}', [JenisRapatController::class, 'update']);
        Route::delete('jenis-rapat/{jenis_rapat}', [JenisRapatController::class, 'destroy']);

        // Kegiatan
        Route::post('kegiatan', [KegiatanController::class, 'store']);
        Route::put('kegiatan/{kegiatan}', [KegiatanController::class, 'update']);
        Route::delete('kegiatan/{kegiatan}', [KegiatanController::class, 'destroy']);
        Route::post('anggota-kegiatan', [AnggotaKegiatanController::class, 'store']);
        Route::put('anggota-kegiatan/{anggota_kegiatan}', [AnggotaKegiatanController::class, 'update']);
        Route::delete('anggota-kegiatan/{anggota_kegiatan}', [AnggotaKegiatanController::class, 'destroy']);

        // Master Data Senat
        Route::post('periode-senat', [PeriodeSenatController::class, 'store']);
        Route::put('periode-senat/{periode_senat}', [PeriodeSenatController::class, 'update']);
        Route::delete('periode-senat/{periode_senat}', [PeriodeSenatController::class, 'destroy']);

        Route::post('komisi', [KomisiController::class, 'store']);
        Route::put('komisi/{komisi}', [KomisiController::class, 'update']);
        Route::delete('komisi/{komisi}', [KomisiController::class, 'destroy']);

        // Manajemen anggota komisi (endpoint kompatibilitas frontend)
        Route::post('committees/members', [KomisiController::class, 'storeMember']);
        Route::put('committees/members/{id}', [KomisiController::class, 'updateMember']);
        Route::delete('committees/members/{id}', [KomisiController::class, 'destroyMember']);

        Route::post('enrollment', [EnrollmentController::class, 'store']);
        Route::put('enrollment/{enrollment}', [EnrollmentController::class, 'update']);
        Route::delete('enrollment/{enrollment}', [EnrollmentController::class, 'destroy']);

        Route::post('struktur-organisasi', [StrukturOrganisasiController::class, 'store']);
        Route::put('struktur-organisasi/{struktur_organisasi}', [StrukturOrganisasiController::class, 'update']);
        Route::delete('struktur-organisasi/{struktur_organisasi}', [StrukturOrganisasiController::class, 'destroy']);

        Route::post('kategori-aspirasi', [KategoriAspirasiController::class, 'store']);
        Route::put('kategori-aspirasi/{kategori_aspirasi}', [KategoriAspirasiController::class, 'update']);
        Route::delete('kategori-aspirasi/{kategori_aspirasi}', [KategoriAspirasiController::class, 'destroy']);

        // Rekaman Rapat Admin
        Route::get('rapat/{id_rapat}/rekaman', [RapatRekamanController::class, 'index']);
        Route::post('rapat/{id_rapat}/rekaman', [RapatRekamanController::class, 'store']);
        Route::get('rapat/{id_rapat}/rekaman/{id_rekaman}', [RapatRekamanController::class, 'show']);
        Route::delete('rapat/{id_rapat}/rekaman/{id_rekaman}', [RapatRekamanController::class, 'destroy']);

        // Tanggapan Aspirasi & Update Aspirasi
        Route::put('aspirasi/{aspirasi}', [AspirasiController::class, 'update']);
        Route::delete('aspirasi/{aspirasi}', [AspirasiController::class, 'destroy']);
        Route::apiResource('tanggapan-aspirasi', TanggapanAspirasiController::class);

        // Publikasi (Berita & Peraturan CRUD)
        Route::post('berita', [BeritaController::class, 'store']);
        Route::put('berita/{berita}', [BeritaController::class, 'update']);
        Route::delete('berita/{berita}', [BeritaController::class, 'destroy']);

        Route::post('peraturan', [PeraturanController::class, 'store']);
        Route::put('peraturan/{peraturan}', [PeraturanController::class, 'update']);
        Route::delete('peraturan/{peraturan}', [PeraturanController::class, 'destroy']);

        // User Management & Impersonation
        Route::post('admin/impersonate/{id}', [AuthController::class, 'impersonate']);
        Route::apiResource('user', UserController::class);
    });
});
