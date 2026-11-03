<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Transkrip rapat → draf notulensi terstruktur (markdown) lewat Gemini.
 */
class NotulensiAiService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = (string) (config('services.ai.api_key') ?: env('AI_API_KEY', ''));
        $this->model = (string) (config('services.ai.model') ?: env('AI_MODEL', 'gemini-3.5-flash-lite'));
    }

    /**
     * @param  array{nama:string,jenis:string,tanggal:string,tempat:string}  $rapat
     * @param  list<string>  $peserta  nama anggota yang hadir (memperbaiki salah dengar nama)
     */
    public function susun(string $transkrip, array $rapat, array $peserta = []): string
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('AI_API_KEY belum diset.');
        }

        $daftarPeserta = $peserta === [] ? '(tidak tersedia)' : implode(', ', array_slice($peserta, 0, 80));

        $system = <<<PROMPT
Anda adalah notulis resmi Senat Akademik Politeknik Negeri Semarang.
Tugas: ubah transkrip rekaman rapat menjadi notulensi resmi berbahasa Indonesia.

ATURAN:
1. Tulis hanya berdasarkan transkrip. JANGAN mengarang keputusan, angka, atau nama yang tidak ada.
2. Perbaiki salah dengar nama orang memakai daftar peserta yang diberikan.
3. Jika suatu bagian tidak jelas atau tidak dibahas, tulis "Tidak dibahas dalam rekaman" — jangan diisi karangan.
4. Bahasa Indonesia formal, ringkas, tanpa basa-basi.
5. Sertakan penanda waktu (mis. 00:12:30) bila terlihat dari konteks; jika tidak ada, lewati.

FORMAT KELUARAN (markdown, pakai heading persis ini):
## Ringkasan
2–4 kalimat inti rapat.

## Pokok Bahasan
- Poin pembahasan, satu baris per poin, kelompokkan bila perlu.

## Keputusan
- Keputusan yang benar-benar disepakati. Jika tidak ada, tulis "Tidak ada keputusan yang ditetapkan dalam rekaman."

## Action Items
| Tindakan | Penanggung Jawab | Tenggat |
|---|---|---|
| ... | ... | ... |
Jika tidak ada, tulis satu baris "Tidak ada action item yang disebutkan."

## Catatan
Hal yang masih menggantung, perlu klarifikasi, atau suara tidak jelas.
PROMPT;

        $user = "DATA RAPAT\n"
            . "Nama rapat: {$rapat['nama']}\n"
            . "Jenis: {$rapat['jenis']}\n"
            . "Tanggal: {$rapat['tanggal']}\n"
            . "Tempat: {$rapat['tempat']}\n\n"
            . "PESERTA (perbaiki nama yang salah dengar sesuai daftar ini):\n{$daftarPeserta}\n\n"
            . "TRANSKRIP:\n{$transkrip}";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $payload = [
            'system_instruction' => ['parts' => [['text' => $system]]],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $user]]],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 8192,
            ],
        ];

        $res = Http::timeout(180)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->retry(2, 500, fn ($e) => $e instanceof \Illuminate\Http\Client\ConnectionException, throw: false)
            ->post($url, $payload);

        if (! $res->successful()) {
            Log::error('Gemini notulensi error: ' . mb_substr($res->body(), 0, 500));
            throw new RuntimeException('Gagal menyusun notulensi (Gemini ' . $res->status() . ').');
        }

        $teks = $res->json('candidates.0.content.parts.0.text');

        if (! is_string($teks) || trim($teks) === '') {
            throw new RuntimeException('Gemini mengembalikan jawaban kosong.');
        }

        return trim($teks);
    }
}
