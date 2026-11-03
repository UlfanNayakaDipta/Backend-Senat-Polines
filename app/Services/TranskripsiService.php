<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Audio/video → teks lewat Groq Whisper.
 *
 * ffmpeg mengekstrak audio 16 kHz mono (video tidak pernah dikirim apa adanya),
 * lalu dipotong per 20 menit agar tiap permintaan jauh di bawah batas 25 MB.
 */
class TranskripsiService
{
    private string $key;
    private string $model;
    private string $ffmpeg;
    private string $ffprobe;
    private int $potongDetik;
    private int $maksDetik;

    public function __construct()
    {
        $this->key = (string) (config('services.groq.key') ?: env('GROQ_API_KEY', ''));
        $this->model = (string) (config('services.groq.stt_model') ?: env('GROQ_STT_MODEL', 'whisper-large-v3-turbo'));
        $this->ffmpeg = is_file('/usr/bin/ffmpeg') ? '/usr/bin/ffmpeg' : 'ffmpeg';
        $this->ffprobe = is_file('/usr/bin/ffprobe') ? '/usr/bin/ffprobe' : 'ffprobe';
        $this->potongDetik = (int) (config('services.groq.chunk_seconds') ?: env('GROQ_STT_CHUNK_SECONDS', 1200));
        $this->maksDetik = (int) (config('services.groq.maks_detik') ?: env('REKAMAN_MAKS_DETIK', 14400)); // 4 jam
    }

    /**
     * @param  callable(int):void|null  $onProgress  dipanggil 0–100 selama transkripsi
     * @return array{transkrip:string,durasi:int,potongan:int}
     */
    public function proses(string $berkasAbsolut, ?callable $onProgress = null): array
    {
        if ($this->key === '') {
            throw new RuntimeException('GROQ_API_KEY belum diset.');
        }

        if (! is_file($berkasAbsolut)) {
            throw new RuntimeException('Berkas rekaman tidak ditemukan.');
        }

        // Periksa durasi pada berkas ASLI sebelum ekstraksi — menolak lebih awal
        // jauh lebih murah daripada memotong audio yang tidak akan diproses.
        $durasiAsli = $this->durasi($berkasAbsolut);
        if ($this->maksDetik > 0 && $durasiAsli > $this->maksDetik) {
            throw new RuntimeException(sprintf(
                'Durasi rekaman %s melebihi batas %s. Potong rekaman atau naikkan REKAMAN_MAKS_DETIK.',
                $this->formatDurasi($durasiAsli),
                $this->formatDurasi($this->maksDetik)
            ));
        }

        $kerja = storage_path('app/rekaman-' . uniqid());
        if (! is_dir($kerja) && ! mkdir($kerja, 0775, true) && ! is_dir($kerja)) {
            throw new RuntimeException('Gagal membuat direktori kerja.');
        }

        try {
            $audio = $kerja . '/audio.mp3';

            $this->jalankan([
                $this->ffmpeg, '-y', '-i', $berkasAbsolut,
                '-vn', '-ac', '1', '-ar', '16000',
                '-c:a', 'libmp3lame', '-b:a', '64k',
                $audio,
            ], 'Gagal mengekstrak audio dari berkas.');

            $durasi = $this->durasi($audio);
            $potongan = $this->potong($audio, $kerja, $durasi);

            $teks = [];
            $jumlah = count($potongan);

            foreach ($potongan as $i => $file) {
                $teks[] = $this->transkripSatu($file);
                if ($onProgress) {
                    $onProgress((int) round((($i + 1) / max(1, $jumlah)) * 100));
                }
            }

            return [
                'transkrip' => trim(preg_replace("/\n{3,}/", "\n\n", implode("\n\n", $teks))),
                'durasi' => $durasi,
                'potongan' => $jumlah,
            ];
        } finally {
            $this->bersihkan($kerja);
        }
    }

    private function formatDurasi(int $detik): string
    {
        $jam = intdiv($detik, 3600);
        $sisa = $detik % 3600;

        return $jam > 0
            ? sprintf('%d jam %d mnt', $jam, intdiv($sisa, 60))
            : sprintf('%d mnt %d dtk', intdiv($detik, 60), $sisa % 60);
    }

    private function durasi(string $berkas): int
    {
        $out = shell_exec(sprintf(
            '%s -v error -show_entries format=duration -of csv=p=0 %s 2>/dev/null',
            escapeshellarg($this->ffprobe),
            escapeshellarg($berkas)
        ));

        return (int) round((float) trim((string) $out));
    }

    /** @return list<string> */
    private function potong(string $audio, string $kerja, int $durasi): array
    {
        if ($durasi > 0 && $durasi <= $this->potongDetik) {
            return [$audio];
        }

        $this->jalankan([
            $this->ffmpeg, '-y', '-i', $audio,
            '-f', 'segment', '-segment_time', (string) $this->potongDetik,
            '-c', 'copy',
            $kerja . '/bagian-%03d.mp3',
        ], 'Gagal memotong audio.');

        $files = glob($kerja . '/bagian-*.mp3') ?: [];
        sort($files);

        if ($files === []) {
            return [$audio];
        }

        return $files;
    }

    private function transkripSatu(string $berkas): string
    {
        $percobaan = 0;

        while (true) {
            $percobaan++;

            try {
                $res = Http::timeout(300)
                    ->withToken($this->key)
                    ->attach('file', file_get_contents($berkas), basename($berkas))
                    ->post('https://api.groq.com/openai/v1/audio/transcriptions', [
                        'model' => $this->model,
                        'language' => 'id',
                        'response_format' => 'json',
                        'temperature' => 0,
                    ]);

                if ($res->successful()) {
                    return trim((string) ($res->json('text') ?? ''));
                }

                // 4xx selain rate-limit tidak akan berhasil kalau diulang
                if ($res->status() !== 429 && $res->status() < 500) {
                    throw new RuntimeException('Groq menolak transkripsi: ' . $res->status() . ' ' . mb_substr($res->body(), 0, 200));
                }

                if ($percobaan >= 3) {
                    throw new RuntimeException('Groq gagal setelah 3 percobaan: ' . $res->status());
                }
            } catch (RuntimeException $e) {
                throw $e;
            } catch (\Throwable $e) {
                if ($percobaan >= 3) {
                    throw new RuntimeException('Transkripsi gagal: ' . $e->getMessage());
                }
            }

            sleep(3 * $percobaan);
        }
    }

    /** @param list<string> $perintah */
    private function jalankan(array $perintah, string $pesanGagal): void
    {
        $cmd = implode(' ', array_map('escapeshellarg', $perintah)) . ' 2>&1';
        $out = [];
        $kode = 0;
        exec($cmd, $out, $kode);

        if ($kode !== 0) {
            Log::error('ffmpeg gagal: ' . mb_substr(implode("\n", $out), -800));
            throw new RuntimeException($pesanGagal);
        }
    }

    private function bersihkan(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
}
