<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notulensis', function (Blueprint $table) {
            $table->id('id_notulensi');
            $table->foreignId('id_rapat')->constrained('rapats', 'id_rapat')->cascadeOnDelete();
            $table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnDelete();
            $table->text('isi_notulensi');
            $table->string('file_risalah')->nullable();
            $table->dateTime('tanggal_dibuat')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notulensis');
    }
};
