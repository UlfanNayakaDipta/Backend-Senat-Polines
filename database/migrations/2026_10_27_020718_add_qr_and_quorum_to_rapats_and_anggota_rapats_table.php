<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rapats', function (Blueprint $table) {
            $table->string('token_qr')->nullable()->after('status');
            $table->string('kode_sesi', 10)->nullable()->after('token_qr');
            $table->boolean('is_presensi_open')->default(false)->after('kode_sesi');
            $table->timestamp('qr_expires_at')->nullable()->after('is_presensi_open');
            $table->unsignedTinyInteger('kuorum_min_persen')->default(67)->after('qr_expires_at'); // default 2/3 = ~67%
            $table->boolean('is_kuorum_locked')->default(false)->after('kuorum_min_persen');
        });

        Schema::table('anggota_rapats', function (Blueprint $table) {
            $table->string('metode_presensi', 50)->nullable()->after('waktu_presensi'); // qr_scan, token_input, manual_admin
            $table->boolean('is_edited_by_admin')->default(false)->after('metode_presensi');
            $table->string('catatan_admin')->nullable()->after('is_edited_by_admin');
        });
    }

    public function down(): void
    {
        Schema::table('anggota_rapats', function (Blueprint $table) {
            $table->dropColumn(['metode_presensi', 'is_edited_by_admin', 'catatan_admin']);
        });

        Schema::table('rapats', function (Blueprint $table) {
            $table->dropColumn(['token_qr', 'kode_sesi', 'is_presensi_open', 'qr_expires_at', 'kuorum_min_persen', 'is_kuorum_locked']);
        });
    }
};
