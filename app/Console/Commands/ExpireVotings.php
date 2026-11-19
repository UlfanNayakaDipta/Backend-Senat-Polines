<?php

namespace App\Console\Commands;

use App\Models\Voting;
use Illuminate\Console\Command;

class ExpireVotings extends Command
{
    protected $signature = 'voting:expire';

    protected $description = 'Tutup sesi voting yang melewati waktu selesai tanpa mengubah jadwal selesai';

    public function handle(): int
    {
        $count = Voting::writeLocked(function (): int {
            $expired = Voting::whereIn('status', ['dibuka', 'aktif', 'active', 'open', 'berlangsung'])
                ->whereNotNull('waktu_selesai')
                ->where('waktu_selesai', '<=', now())
                ->lockForUpdate()
                ->get();

            foreach ($expired as $voting) {
                $voting->update(['status' => 'ditutup']);
            }

            return $expired->count();
        });

        $this->info("Voting kedaluwarsa ditutup: {$count}");

        return self::SUCCESS;
    }
}
