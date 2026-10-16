<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anggota_rapats', function (Blueprint $table) {
            $table->id('id_undangan');
            $table->foreignId('id_rapat')->constrained('rapats', 'id_rapat')->cascadeOnDelete();
            $table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnDelete();
            $table->string('status_kehadiran')->nullable();
            $table->dateTime('waktu_presensi')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anggota_rapats');
    }
};
