<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service AI Chatbot Senat Polines.
 * Menjawab pertanyaan spesifik seputar navigasi website, struktur komisi,
 * agenda sidang, dan panduan aspirasi mahasiswa via Google Generative AI (Gemini Flash).
 */
class ChatbotService
{
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.ai.api_key', env('AI_API_KEY', ''));
        $this->model  = config('services.ai.model', env('AI_MODEL', 'gemini-3.5-flash-lite'));
<<<<<<< HEAD
        $this->endpoint = config('services.ai.endpoint', env('AI_ENDPOINT', 'https://api.openai.com/v1/chat/completions'));
    }

    /**
     * Memproses chat dengan LLM menggunakan AI (OpenAI/Gemini/9router).
     *
     * @param string $message Pesan dari user/mahasiswa.
     * @param array $history Riwayat percakapan sebelumnya.
     * @return array Hasil balasan dari chatbot.
=======
    }

    /**
     * Memproses chat dengan guardrails ketat (hanya seputar Senat Polines & Website).
>>>>>>> 99ac6ef (feat(ai): integrate Gemini Flash Lite with Senat knowledge base and guardrails)
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

<<<<<<< HEAD
=======
KNOWLEDGE BASE RESMI SENAT POLINES:
- Pimpinan Senat (Periode 2026–2029):
  • Ketua Senat: Dr. Sukamto, S.Kom., M.T. (NIP 197101172003121001)
  • Sekretaris Senat: Andi Setiawan, S.E., M.M. (NIP 197607202008011011)
- Badan Pekerja Harian (BPH):
  • Ketua: Andi Setiawan, S.E., M.M.
  • Sekretaris: Vinda Setya Kartika, S.T., M.T.
- 5 Komisi Tetap Senat Polines:
  1. Komisi I (Pendidikan dan Pengajaran) - Ketua: Tri Yudantoro, S.T., M.T. / Sekr: Anis Roihatin, S.T., M.T. (Fokus: kurikulum, kalender akademik, beban SKS, pembelajaran vokasi).
  2. Komisi II (Penelitian dan Pengabdian kepada Masyarakat) - Ketua: Dr. Jumi, S.Kom., M.Kom. / Sekr: Fajar Surya Herlambang, S.T., M.T. (Fokus: riset terapan, hilirisasi pengmas, etika penelitian).
  3. Komisi III (Kelembagaan dan Sumber Daya Manusia) - Ketua: Riles Melvy Wattimena, S.T., M.T. / Sekr: Iwan Budiyono, S.T., M.T. (Fokus: tata kelola jurusan/prodi, jabatan fungsional dosen, formasi SDM).
  4. Komisi IV (Pengembangan dan Kerja Sama) - Ketua: FX Arif Wahyudianto, S.T., M.T. / Sekr: Alvianita Gunawan Putri, S.T., M.T. (Fokus: kemitraan industri, rencana induk pengembangan, program strategis).
  5. Komisi V (Komisi Etik) - Ketua: Drs. Suroso, M.Pd. / Sekr: Achmad Zaenuddin, S.T., M.T. (Fokus: kode etik dosen, integritas sivitas, penegakan disiplin akademik).

>>>>>>> 99ac6ef (feat(ai): integrate Gemini Flash Lite with Senat knowledge base and guardrails)
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
<<<<<<< HEAD
=======

        // Masukkan history percakapan sebelumnya jika ada
>>>>>>> 99ac6ef (feat(ai): integrate Gemini Flash Lite with Senat knowledge base and guardrails)
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

<<<<<<< HEAD
=======
        // Pesan user saat ini
>>>>>>> 99ac6ef (feat(ai): integrate Gemini Flash Lite with Senat knowledge base and guardrails)
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
<<<<<<< HEAD
                'temperature' => 0.2,
=======
                'temperature' => 0.1, // Sangat deterministik
>>>>>>> 99ac6ef (feat(ai): integrate Gemini Flash Lite with Senat knowledge base and guardrails)
                'maxOutputTokens' => 600,
            ]
        ];

<<<<<<< HEAD
        try {
            $response = Http::timeout(20)
                ->withHeaders(['Authorization' => "Bearer {$this->apiKey}", 'Content-Type' => 'application/json'])
                ->post($this->endpoint, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $reply = $data['choices'][0]['message']['content'] ?? null;
=======
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        try {
            // 2 percobaan, timeout 20s; flash-lite menjawab ~1.5s sehingga aman untuk widget
            $response = Http::timeout(20)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->retry(2, 250, function ($exception) {
                    return $exception instanceof \Illuminate\Http\Client\ConnectionException;
                }, throw: false)
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
>>>>>>> 99ac6ef (feat(ai): integrate Gemini Flash Lite with Senat knowledge base and guardrails)

                if ($reply) {
                    return [
                        'reply' => trim($reply),
                        'is_aspiration' => (stripos($message, 'aspirasi') !== false || stripos($message, 'keluh') !== false || stripos($message, 'aduan') !== false),
                    ];
                }
            }

<<<<<<< HEAD
            Log::error('LLM API Error: ' . $response->body());
=======
            Log::error('Gemini API Error: ' . $response->body());
>>>>>>> 99ac6ef (feat(ai): integrate Gemini Flash Lite with Senat knowledge base and guardrails)
        } catch (\Throwable $e) {
            Log::error('ChatbotService Exception: ' . $e->getMessage());
        }

        return [
<<<<<<< HEAD
            'reply' => 'Layanan chatbot AI Senat Polines sedang dalam antrean pemrosesan.',
=======
            'reply' => 'Halo! Saya asisten Senat Polines. Saat ini layanan AI sedang dalam antrean pemrosesan. Anda dapat membaca informasi profil Senat di menu Profil, menelaah aturan di menu Produk Hukum, atau mengirimkan masukan via Kanal Aspirasi.',
>>>>>>> 99ac6ef (feat(ai): integrate Gemini Flash Lite with Senat knowledge base and guardrails)
            'fallback' => true,
        ];
    }
}