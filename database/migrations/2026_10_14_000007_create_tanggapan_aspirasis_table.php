<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tanggapan_aspirasis', function (Blueprint $table) {
            $table->id('id_tanggapan');
            $table->foreignId('id_aspirasi')->constrained('aspirasis', 'id_aspirasi')->cascadeOnDelete();
            $table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnDelete();
            $table->text('pesan');
            $table->dateTime('tanggal_kirim')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tanggapan_aspirasis');
    }
};
