<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materi_rapats', function (Blueprint $table) {
            $table->id('id_materi');
            $table->foreignId('id_rapat')->constrained('rapats', 'id_rapat')->cascadeOnDelete();
            $table->string('judul_materi');
            $table->string('file_materi');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materi_rapats');
    }
};
