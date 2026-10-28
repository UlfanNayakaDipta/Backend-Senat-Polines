<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('notulensis', 'rekomendasi_keputusan')) {
            Schema::table('notulensis', function (Blueprint $table) {
                $table->string('rekomendasi_keputusan')->nullable()->after('isi_notulensi');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('notulensis', 'rekomendasi_keputusan')) {
            Schema::table('notulensis', function (Blueprint $table) {
                $table->dropColumn('rekomendasi_keputusan');
            });
        }
    }
};