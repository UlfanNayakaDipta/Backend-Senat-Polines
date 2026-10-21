<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service Chatbot LLM untuk memproses pertanyaan seputar Senat Polines
 * dan menanggapi aspirasi mahasiswa secara cerdas berbasis knowledge base senat.
 */
class ChatbotService
{
    protected string $apiKey;
    protected string $model;
    protected string $endpoint;

    public function __construct()
    {
        $this->apiKey = config('services.ai.api_key', env('AI_API_KEY', ''));
        $this->model  = config('services.ai.model', env('AI_MODEL', 'gemini-3.5-flash-lite'));
        $this->endpoint = config('services.ai.endpoint', env('AI_ENDPOINT', 'https://api.openai.com/v1/chat/completions'));
    }

    /**
     * Memproses chat dengan LLM menggunakan AI (OpenAI/Gemini/9router).
     *
     * @param string $message Pesan dari user/mahasiswa.
     * @param array $history Riwayat percakapan sebelumnya.
     * @return array Hasil balasan dari chatbot.
     */
    public function respond(string $message, array $history = []): array
    {
        $systemPrompt = <<<PROMPT
Anda adalah "Asisten Virtual Resmi Senat Polines" (Politeknik Negeri Semarang).
Tugas utama Anda: Memberikan informasi akurat kepada mahasiswa dan civitas akademika mengenai Senat Akademik Polines, wewenang 5 komisi, peraturan/produk hukum akademik, jadwal agenda sidang, serta memandu cara penyampaian aspirasi mahasiswa di portal web ini.

BATASAN KETAT (GUARDRAILS & ANTI-HALUSINASI):
1. HANYA jawab pertanyaan seputar Senat Polines, akademik Polines, komisi senat, aspirasi mahasiswa, dan panduan halaman website ini.
2. JANGAN PERNAH menjawab pertanyaan di luar topik Senat Polines (seperti resep makanan, kodingan umum, politik nasional luar kampus, curhat pribadi, matematika umum, dll.). Jika di luar topik, TOLAK DENGAN RAMAH:
   "Maaf, saya asisten virtual khusus Senat Polines. Saya hanya dapat membantu menjawab pertanyaan seputar struktur organisasi senat, peraturan akademik, agenda sidang, dan layanan aspirasi mahasiswa di portal ini."
3. Format teks rapi, sopan, bahasa Indonesia yang baik, padat dan to-the-point (maksimal 2 paragraf ringkas).

NAVIGASI WEBSITE & FITUR (gunakan path ini persis, jangan mengarang URL lain):
• Beranda (/)
• Profil (/profil) — visi & misi; Tugas & Wewenang (/profil/tugas-wewenang); Struktur Organisasi (/profil/struktur-organisasi).
• Direktori Komisi & Anggota (/direktori-komisi) — profil anggota per komisi di /direktori-komisi/{slug}.
• Regulasi (/regulasi) — Keputusan Senat (/regulasi/keputusan-senat), Standar & Pedoman (/regulasi/standar-pedoman).
• Berita (/berita), Arsip (/arsip), Laporan Tahunan (/arsip/laporan-tahunan).
• Kanal Aspirasi (/aspirasi) — formulir resmi; pelacakan status di (/aspirasi/track).

Jika pengguna menyampaikan keluhan atau usulan kebijakan/fasilitas, arahkan mereka untuk mengisi form resmi di menu "Kanal Aspirasi" (/aspirasi).
PROMPT;

        $contents = [];
        $cleanHistory = array_slice($history, -6);
        foreach ($cleanHistory as $h) {
            if (isset($h['role'], $h['content']) && in_array($h['role'], ['user', 'assistant'])) {
                $role = ($h['role'] === 'assistant') ? 'model' : 'user';
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => (string) $h['content']]]
                ];
            }
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $message]]
        ];

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]]
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 600,
            ]
        ];

        try {
            $response = Http::timeout(20)
                ->withHeaders(['Authorization' => "Bearer {$this->apiKey}", 'Content-Type' => 'application/json'])
                ->post($this->endpoint, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $reply = $data['choices'][0]['message']['content'] ?? null;

                if ($reply) {
                    return [
                        'reply' => trim($reply),
                        'is_aspiration' => (stripos($message, 'aspirasi') !== false || stripos($message, 'keluh') !== false || stripos($message, 'aduan') !== false),
                    ];
                }
            }

            Log::error('LLM API Error: ' . $response->body());
        } catch (\Throwable $e) {
            Log::error('ChatbotService Exception: ' . $e->getMessage());
        }

        return [
            'reply' => 'Layanan chatbot AI Senat Polines sedang dalam antrean pemrosesan.',
            'fallback' => true,
        ];
    }
}