<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapats', function (Blueprint $table) {
            $table->id('id_rapat');
            $table->foreignId('id_kegiatan')->nullable()->constrained('kegiatans', 'id_kegiatan')->cascadeOnDelete();
            $table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnDelete();
            $table->foreignId('id_jenis_rapat')->constrained('jenis_rapats', 'id_jenis_rapat')->cascadeOnDelete();
            $table->string('nama_agenda');
            $table->date('tanggal');
            $table->time('waktu');
            $table->string('tempat');
            $table->string('status')->default('dijadwalkan');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapats');
    }
};
