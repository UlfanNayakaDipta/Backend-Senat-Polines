<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suara_votings', function (Blueprint $table) {
            $table->id('id_suara');
            $table->foreignId('id_voting')->constrained('votings', 'id_voting')->cascadeOnDelete();
            $table->foreignId('id_opsi')->constrained('opsi_votings', 'id_opsi')->cascadeOnDelete();
            $table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnDelete();
            $table->dateTime('waktu_vote')->useCurrent();
            $table->unique(['id_voting', 'id_user']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suara_votings');
    }
};
