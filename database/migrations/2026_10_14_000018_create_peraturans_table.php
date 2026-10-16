<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peraturans', function (Blueprint $table) {
            $table->id('id_peraturan');
            $table->foreignId('id_periode_senat')->constrained('periode_senats', 'id_periode_senat')->cascadeOnDelete();
            $table->string('nomor_sk');
            $table->string('judul');
            $table->string('kategori');
            $table->string('tahap_pembentukan');
            $table->string('file_dokumen')->nullable();
            $table->date('tanggal_terbit');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peraturans');
    }
};
