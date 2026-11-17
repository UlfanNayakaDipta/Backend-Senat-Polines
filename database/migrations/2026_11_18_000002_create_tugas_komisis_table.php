<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tugas_komisis', function (Blueprint $table) {
            $table->id('id_tugas');
            $table->foreignId('id_komisi')->constrained('komisis', 'id_komisi')->cascadeOnDelete();
            $table->text('uraian_tugas');
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tugas_komisis');
    }
};
