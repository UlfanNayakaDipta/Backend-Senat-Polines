<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Status notulensi memakai istilah keputusan senat:
 * ditetapkan / dipertimbangkan / dikembalikan.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private array $maju = [
        'draf' => 'dikembalikan',
        'draft' => 'dikembalikan',
        'review' => 'dipertimbangkan',
        'disahkan' => 'ditetapkan',
    ];

    /** @var array<string, string> */
    private array $mundur = [
        'dikembalikan' => 'draf',
        'dipertimbangkan' => 'review',
        'ditetapkan' => 'disahkan',
    ];

    public function up(): void
    {
        foreach ($this->maju as $lama => $baru) {
            DB::table('notulensis')->whereRaw('LOWER(status) = ?', [$lama])->update(['status' => $baru]);
        }

        Schema::table('notulensis', function (Blueprint $table) {
            $table->string('status')->default('dipertimbangkan')->change();
        });
    }

    public function down(): void
    {
        foreach ($this->mundur as $baru => $lama) {
            DB::table('notulensis')->whereRaw('LOWER(status) = ?', [$baru])->update(['status' => $lama]);
        }

        Schema::table('notulensis', function (Blueprint $table) {
            $table->string('status')->default('draf')->change();
        });
    }
};
