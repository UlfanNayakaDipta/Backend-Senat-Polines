<?php

namespace App\Console\Commands;

use App\Models\RapatRekaman;
use Illuminate\Console\Command;

/**
 * Kebijakan arsip: transkrip & notulensi disimpan permanen, berkas rekaman
 * asli dihapus setelah N hari agar disk tidak penuh.
 */
class BersihkanRekaman extends Command
{
    protected $signature = 'rekaman:bersihkan
                            {--hari= : Umur maksimum berkas rekaman dalam hari (default dari REKAMAN_RETENSI_HARI)}
                            {--dry-run : Tampilkan saja, jangan hapus}';

    protected $description = 'Hapus berkas rekaman rapat yang melewati masa retensi (transkrip & notulensi tetap disimpan)';

    public function handle(): int
    {
        $hari = (int) ($this->option('hari') ?: config('senat.rekaman_retensi_hari') ?: env('REKAMAN_RETENSI_HARI', 90));
        $dry = (bool) $this->option('dry-run');
        $batas = now()->subDays($hari);

        $rows = RapatRekaman::where('created_at', '<', $batas)
            ->whereNotNull('berkas')
            ->get();

        $dihapus = 0;
        $hilang = 0;

        foreach ($rows as $r) {
            $path = public_path($r->berkas);

            if (! is_file($path)) {
                $hilang++;
            } elseif ($dry) {
                $this->line('[dry] ' . $r->berkas . ' (' . round(filesize($path) / 1048576, 1) . ' MB)');
                $dihapus++;

                continue;
            } else {
                @unlink($path);
                $dihapus++;
            }

            // Transkrip & notulen sengaja TIDAK dihapus: itu arsip resmi.
            $r->update(['berkas' => 'dihapus/retensi-' . $hari . 'hari/' . basename($r->berkas)]);
        }

        $this->info(($dry ? '[dry-run] ' : '') . "Berkas rekaman dibersihkan: {$dihapus}, sudah tidak ada: {$hilang}, batas umur {$hari} hari.");

        return self::SUCCESS;
    }
}
