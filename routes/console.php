<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Retensi rekaman: berkas asli dihapus setelah 90 hari, transkrip & notulensi permanen.
Schedule::command('rekaman:bersihkan')->dailyAt('02:30')->withoutOverlapping();
