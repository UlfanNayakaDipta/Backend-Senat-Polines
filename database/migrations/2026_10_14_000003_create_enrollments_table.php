<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id('id_enrollment');
            $table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnDelete();
            $table->foreignId('id_komisi')->constrained('komisis', 'id_komisi')->cascadeOnDelete();
            $table->foreignId('id_periode_senat')->constrained('periode_senats', 'id_periode_senat')->cascadeOnDelete();
            $table->string('jabatan_komisi');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
