<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periode_senats', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->after('tanggal_selesai');
        });

        // Tandai periode terbaru yang sudah ada sebagai aktif
        $latestId = DB::table('periode_senats')->max('id_periode_senat');
        if ($latestId) {
            DB::table('periode_senats')
                ->where('id_periode_senat', $latestId)
                ->update(['is_active' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('periode_senats', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
