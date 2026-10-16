<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opsi_votings', function (Blueprint $table) {
            $table->id('id_opsi');
            $table->foreignId('id_voting')->constrained('votings', 'id_voting')->cascadeOnDelete();
            $table->string('nama_opsi');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opsi_votings');
    }
};
