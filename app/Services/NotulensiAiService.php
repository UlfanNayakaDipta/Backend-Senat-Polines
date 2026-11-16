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
Tugas: ubah transkrip rekaman rapat menjadi HASIL rapat resmi berbahasa Indonesia
yang langsung naik cetak sebagai PDF notulen (format FPM 5.5.01/L2).

ATURAN:
1. Tulis hanya berdasarkan transkrip. JANGAN mengarang keputusan, angka, pasal, atau nama yang tidak ada.
2. Perbaiki salah dengar nama orang memakai daftar peserta yang diberikan.
3. Fokus pada HASIL: apa yang dibahas, siapa menyampaikan apa, koreksi/usulan, dan apa yang disepakati.
4. JANGAN menulis daftar hadir, jumlah peserta, lampiran, atau action item. Bagian itu diurus sekretariat.
5. Jika suatu bagian tidak jelas atau tidak dibahas, tulis "Tidak dibahas dalam rekaman" — jangan diisi karangan.
6. Bahasa Indonesia formal, ringkas, tanpa basa-basi. Sebut nama penyampai bila disebut di transkrip.
7. Sertakan penanda waktu (mis. 00:12:30) bila terlihat dari konteks; jika tidak ada, lewati.

FORMAT KELUARAN (markdown, pakai heading persis ini):
## Identitas
Pimpinan Rapat: <nama dan jabatan pimpinan rapat>
Peserta Rapat: <ringkas, mis. "Para anggota Senat, Direktur, dan para Wakil Direktur">

## Agenda Rapat
1. <agenda>
2. <agenda>

## Hasil Pembahasan
### A. <judul bab / butir yang dibahas>
- <hasil pembahasan: usulan, koreksi, tanggapan beserta nama penyampai>
#### Kesepakatan
- <kesepakatan yang benar-benar disepakati pada bagian ini>

### B. <judul berikutnya>
- <hasil pembahasan>
#### Kesepakatan
- <kesepakatan>

## Kesimpulan Rapat
1. <kesimpulan inti>

## Penutup Rapat
- <penutup: apresiasi dan/atau waktu rapat ditutup>
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
