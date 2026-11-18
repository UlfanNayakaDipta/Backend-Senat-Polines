<?php

use App\Support\HeroSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE TABLE hero_settings (id INTEGER PRIMARY KEY CHECK (id = 1), title TEXT NOT NULL, description TEXT NOT NULL, primary_label TEXT NOT NULL, secondary_label TEXT NOT NULL, created_at DATETIME, updated_at DATETIME)');
        } else {
            Schema::create('hero_settings', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->text('title');
            $table->text('description');
            $table->text('primary_label');
            $table->text('secondary_label');
            $table->timestamps();
            });
        }
        $defaults = array_intersect_key(HeroSettings::DEFAULTS, array_flip(['title', 'description', 'primary_label', 'secondary_label']));
        DB::table('hero_settings')->insert(['id' => 1] + $defaults + ['created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_settings');
    }
};
