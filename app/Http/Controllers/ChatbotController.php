<?php

namespace App\Http\Controllers;

use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ChatbotController extends Controller
{
    protected ChatbotService $chatbot;

    public function __construct(ChatbotService $chatbot)
    {
        $this->chatbot = $chatbot;
    }

    /**
     * Endpoint API untuk memproses percakapan dengan AI Senat.
     * Route: POST /api/chatbot/chat
     */
    #[OA\Post(
        path: '/chatbot/chat',
        summary: 'Kirim pesan percakapan ke AI Chatbot Senat',
        description: 'Memproses pesan dari sivitas akademika dan menghasilkan jawaban cerdas terkait regulasi, agenda rapat, dan informasi Senat Polines.',
        tags: ['AI Chatbot'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Pesan pertanyaan dan riwayat percakapan sebelumnya',
            content: new OA\JsonContent(
                required: ['message'],
                properties: [
                    new OA\Property(
                        property: 'message',
                        type: 'string',
                        example: 'Bagaimana prosedur pengajuan aspirasi ke Senat Polines?'
                    ),
                    new OA\Property(
                        property: 'history',
                        type: 'array',
                        items: new OA\Items(type: 'object'),
                        example: [
                            ['role' => 'user', 'content' => 'Halo AI Senat'],
                            ['role' => 'assistant', 'content' => 'Halo! Ada yang bisa saya bantu terkait Senat Akademik Polines?']
                        ]
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Respon dari AI Chatbot berhasil dihasilkan',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'reply', type: 'string', example: 'Aspirasi dapat diajukan melalui portal web resmi pada menu Aspirasi sivitas.'),
                                new OA\Property(property: 'source', type: 'string', example: 'Tata Tertib Senat Akademik')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validasi input gagal'
            )
        ]
    )]
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'history' => 'nullable|array',
        ]);

        $result = $this->chatbot->respond(
            $validated['message'],
            $validated['history'] ?? []
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
