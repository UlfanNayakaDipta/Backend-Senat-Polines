<?php

namespace App\Services;

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
        $this->model = config('services.ai.model', env('AI_MODEL', 'gpt-4o-mini'));
        $this->endpoint = config('services.ai.endpoint', env('AI_ENDPOINT', 'https://api.openai.com/v1/chat/completions'));
    }

    /**
     * Memproses pesan chat dari user/mahasiswa.
     *
     * @param string $message
     * @param array $history
     * @return array
     */
    public function respond(string $message, array $history = []): array
    {
        // TODO: Implementasi pemanggilan API LLM (OpenAI / Gemini / 9router)
        // dengan inject context knowledge base Senat Polines & aspirasi.
        return [
            'reply' => 'Layanan chatbot AI Senat Polines sedang dalam pengembangan.',
            'category_suggestion' => null,
            'is_aspiration' => false,
        ];
    }
}
