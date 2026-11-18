<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hero_settings', function (Blueprint $table) {
            $table->string('eyebrow', 160)->default('Organ Tertinggi Normatif Akademik');
            $table->string('background_image', 2048)->default('/images/gedung-polines.jpg');
        });
    }

    public function down(): void
    {
        Schema::table('hero_settings', function (Blueprint $table) {
            $table->dropColumn(['eyebrow', 'background_image']);
        });
    }
};
