<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('votings', function (Blueprint $table) {
            $table->id('id_voting');
            $table->foreignId('id_rapat')->constrained('rapats', 'id_rapat')->cascadeOnDelete();
            $table->string('judul_voting');
            $table->text('deskripsi')->nullable();
            $table->string('status')->default('draft');
            $table->dateTime('waktu_mulai')->nullable();
            $table->dateTime('waktu_selesai')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votings');
    }
};
