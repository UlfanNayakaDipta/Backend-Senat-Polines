<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aspirasis', function (Blueprint $table) {
            $table->id('id_aspirasi');
            $table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnDelete();
            $table->foreignId('id_kategori')->constrained('kategori_aspirasis', 'id_kategori')->cascadeOnDelete();
            $table->text('isi_aspirasi');
            $table->string('lampiran')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('anonim')->default(false);
            $table->dateTime('tanggal_dibuat')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aspirasis');
    }
};
