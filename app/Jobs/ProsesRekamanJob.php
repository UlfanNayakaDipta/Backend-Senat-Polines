<?php

namespace App\Jobs;

use App\Models\AnggotaRapat;
use App\Models\Notulensi;
use App\Models\RapatRekaman;
use App\Services\NotulensiAiService;
use App\Services\TranskripsiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Satu rekaman → transkrip → draf notulensi, dijalankan di queue.
 * Progres ditulis ke kolom supaya UI bisa polling tanpa menahan request.
 */
class ProsesRekamanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;
    public int $timeout = 3600;

    public function __construct(public int $idRekaman) {}

    public function handle(TranskripsiService $stt, NotulensiAiService $ai): void
    {
        $rekaman = RapatRekaman::with('rapat.jenisRapat')->find($this->idRekaman);

        if (! $rekaman) {
            return;
        }

        try {
            $rekaman->update(['status' => 'transkripsi', 'progres' => 5, 'error' => null]);
            $this->lapor();

            $hasil = $stt->proses(
                public_path($rekaman->berkas),
                function (int $persen) use ($rekaman) {
                    $rekaman->update(['progres' => 10 + (int) round($persen * 0.6)]); // 10–70
                }
            );

            $rekaman->update([
                'transkrip' => $hasil['transkrip'],
                'durasi' => $hasil['durasi'] ?: $rekaman->durasi,
                'progres' => 72,
            ]);
            $this->lapor();

            if (trim((string) $hasil['transkrip']) === '') {
                throw new \RuntimeException('Transkrip kosong — rekaman mungkin tanpa suara.');
            }

            $rekaman->update(['status' => 'menyusun', 'progres' => 78]);
            $this->lapor();

            $rapat = $rekaman->rapat;
            $notulen = $ai->susun(
                $hasil['transkrip'],
                [
                    'nama' => $rapat->nama_agenda ?? 'Rapat Senat',
                    'jenis' => $rapat->jenisRapat->nama_jenis ?? 'Rapat',
                    'tanggal' => (string) ($rapat->tanggal ?? ''),
                    'tempat' => (string) ($rapat->tempat ?? '-'),
                ],
                $this->peserta((int) $rekaman->id_rapat)
            );

            $row = Notulensi::create([
                'id_rapat' => $rekaman->id_rapat,
                'id_user' => $rekaman->id_user,
                'isi_notulensi' => $notulen,
                'status' => 'draf',
            ]);

            $rekaman->update([
                'notulen' => $notulen,
                'id_notulensi' => $row->id_notulensi,
                'status' => 'selesai',
                'progres' => 100,
            ]);
            $this->lapor();
        } catch (Throwable $e) {
            Log::error('ProsesRekamanJob gagal: ' . $e->getMessage(), ['id_rekaman' => $this->idRekaman]);

            $rekaman->update([
                'status' => 'gagal',
                'error' => mb_substr($e->getMessage(), 0, 1000),
            ]);
            $this->lapor();

            throw $e;
        }
    }

    /** @return list<string> */
    private function peserta(int $idRapat): array
    {
        return AnggotaRapat::query()
            ->where('id_rapat', $idRapat)
            ->with('user:id_user,nama')
            ->get()
            ->pluck('user.nama')
            ->filter()
            ->values()
            ->all();
    }

    /** Queue database tidak memberi sinyal; cukup sentuh updated_at agar polling tahu job hidup. */
    private function lapor(): void
    {
        // no-op: kolom progres sudah di-update di atas
    }
}
