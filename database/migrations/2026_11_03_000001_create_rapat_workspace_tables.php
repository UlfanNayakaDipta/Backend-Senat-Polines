<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catatan risalah per anggota per rapat (HTML dari teks editor)
        Schema::create('rapat_catatans', function (Blueprint $table) {
            $table->id('id_catatan');
            $table->foreignId('id_rapat')->constrained('rapats', 'id_rapat')->cascadeOnDelete();
            $table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnDelete();
            $table->text('isi')->nullable();
            $table->timestamps();

            $table->unique(['id_rapat', 'id_user']);
        });

        // Lampiran notulensi per rapat
        Schema::create('rapat_lampirans', function (Blueprint $table) {
            $table->id('id_lampiran');
            $table->foreignId('id_rapat')->constrained('rapats', 'id_rapat')->cascadeOnDelete();
            $table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnDelete();
            $table->string('nama');
            $table->string('url', 2048);
            $table->unsignedBigInteger('ukuran')->default(0);
            $table->string('mime')->nullable();
            $table->timestamps();
        });

        // Dokumen & tautan yang dibagikan antar anggota dalam rapat
        Schema::create('rapat_berbagis', function (Blueprint $table) {
            $table->id('id_berbagi');
            $table->foreignId('id_rapat')->constrained('rapats', 'id_rapat')->cascadeOnDelete();
            $table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnDelete();
            $table->string('jenis', 10)->default('file'); // file | link
            $table->string('nama');
            $table->string('url', 2048)->nullable();
            $table->unsignedBigInteger('ukuran')->default(0);
            $table->string('mime')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapat_berbagis');
        Schema::dropIfExists('rapat_lampirans');
        Schema::dropIfExists('rapat_catatans');
    }
};
