<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notulensis', function (Blueprint $table) {
            // Path relatif berkas PDF notulen resmi (hasil render dari isi_notulensi).
            $table->string('berkas_pdf')->nullable()->after('file_risalah');
            $table->dateTime('pdf_dibuat')->nullable()->after('berkas_pdf');
        });
    }

    public function down(): void
    {
        Schema::table('notulensis', function (Blueprint $table) {
            $table->dropColumn(['berkas_pdf', 'pdf_dibuat']);
        });
    }
};
