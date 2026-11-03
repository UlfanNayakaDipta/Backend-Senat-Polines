<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beritas', function (Blueprint $table): void {
            $table->text('gambar')->nullable()->change();
            $table->json('galeri')->nullable()->after('gambar');
            $table->json('lampiran')->nullable()->after('galeri');
        });
    }

    public function down(): void
    {
        Schema::table('beritas', function (Blueprint $table): void {
            $table->dropColumn(['galeri', 'lampiran']);
            $table->string('gambar')->nullable()->change();
        });
    }
};
