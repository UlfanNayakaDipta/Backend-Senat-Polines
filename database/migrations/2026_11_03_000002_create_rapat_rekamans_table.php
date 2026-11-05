<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapat_rekamans', function (Blueprint $table) {
            $table->id('id_rekaman');
            $table->foreignId('id_rapat')->constrained('rapats', 'id_rapat')->cascadeOnDelete();
            $table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnDelete();

            $table->string('nama_asli');
            $table->string('berkas');            // path relatif di public/uploads/rekaman
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('ukuran')->default(0);
            $table->unsignedInteger('durasi')->default(0); // detik

            // unggah | transkripsi | menyusun | selesai | gagal
            $table->string('status', 20)->default('unggah');
            $table->unsignedTinyInteger('progres')->default(0);

            $table->longText('transkrip')->nullable();
            $table->longText('notulen')->nullable();
            $table->unsignedBigInteger('id_notulensi')->nullable();
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['id_rapat', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapat_rekamans');
    }
};
