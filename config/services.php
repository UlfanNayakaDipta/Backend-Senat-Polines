<?php

return [
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'sso_secret' => env('GOOGLE_SSO_SECRET'),
    ],
    'ai' => [
        'api_key' => env('AI_API_KEY'),
        'model' => env('AI_MODEL', 'gemini-3.5-flash-lite'),
    ],
    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'stt_model' => env('GROQ_STT_MODEL', 'whisper-large-v3-turbo'),
        'chunk_seconds' => (int) env('GROQ_STT_CHUNK_SECONDS', 1200),
        'maks_detik' => (int) env('REKAMAN_MAKS_DETIK', 14400),
    ],
];

