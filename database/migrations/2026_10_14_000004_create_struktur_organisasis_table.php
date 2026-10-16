<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('struktur_organisasis', function (Blueprint $table) {
            $table->id('id_struktur');
            $table->foreignId('id_enrollment')->constrained('enrollments', 'id_enrollment')->cascadeOnDelete();
            $table->string('jabatan');
            $table->string('periode');
            $table->unsignedInteger('urutan_bagan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('struktur_organisasis');
    }
};
