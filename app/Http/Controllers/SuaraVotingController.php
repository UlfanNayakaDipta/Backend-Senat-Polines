<?php

namespace App\Http\Controllers;

use App\Models\OpsiVoting;
use App\Models\SuaraVoting;
use App\Models\Voting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class SuaraVotingController extends CrudController
{
    protected string $model = SuaraVoting::class;
    protected array $with = ['opsiVoting', 'user'];

    protected function rules(?int $id = null): array
    {
        return [
            'id_voting' => ['required', 'exists:votings,id_voting'],
            'id_opsi' => ['required', 'exists:opsi_votings,id_opsi'],
            'id_user' => ['required', 'exists:users,id_user', Rule::unique('suara_votings', 'id_user')->where('id_voting', request('id_voting'))->ignore($id, 'id_suara')],
        ];
    }

    #[OA\Get(
        path: '/suara-voting',
        summary: 'Daftar Suara Masuk (E-Voting)',
        tags: ['Voting'],
        responses: [
            new OA\Response(response: 200, description: 'Daftar perolehan suara terpaginasi')
        ]
    )]
    public function index()
    {
        return parent::index();
    }

    #[OA\Post(
        path: '/suara-voting',
        summary: 'Submit Suara E-Voting Sidang Senat',
        description: 'Mencatat pilihan suara anggota senat pada sesi voting yang aktif (satu anggota satu suara).',
        tags: ['Voting'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_voting', 'id_opsi'],
                properties: [
                    new OA\Property(property: 'id_voting', type: 'integer', example: 1),
                    new OA\Property(property: 'id_opsi', type: 'integer', example: 1),
                    new OA\Property(property: 'id_user', type: 'integer', nullable: true, example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Suara berhasil disimpan dan rekapitulasi diperbarui'
            ),
            new OA\Response(
                response: 422,
                description: 'Voting belum dibuka, sudah memberikan suara, atau opsi tidak valid'
            )
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $idVoting = $request->input('id_voting');
        $voting = Voting::find($idVoting);

        if (! $voting || $voting->status !== 'dibuka') {
            return response()->json([
                'message' => 'Voting sedang ditutup atau belum dibuka.',
            ], 422);
        }

        $idUser = $request->input('id_user') ?? auth()->id();
        if (! $idUser) {
            return response()->json([
                'message' => 'User ID diperlukan.',
            ], 422);
        }

        $alreadyVoted = SuaraVoting::where('id_voting', $voting->id_voting)
            ->where('id_user', $idUser)
            ->exists();

        if ($alreadyVoted) {
            return response()->json([
                'message' => 'Anda sudah memberikan suara pada sesi voting ini.',
            ], 422);
        }

        $idOpsi = $request->input('id_opsi');
        $opsi = OpsiVoting::where('id_voting', $voting->id_voting)
            ->where('id_opsi', $idOpsi)
            ->first();

        if (! $opsi) {
            return response()->json([
                'message' => 'Opsi voting tidak valid untuk sesi voting ini.',
            ], 422);
        }

        $suara = SuaraVoting::create([
            'id_voting' => $voting->id_voting,
            'id_opsi' => $opsi->id_opsi,
            'id_user' => $idUser,
            'waktu_vote' => now(),
        ]);

        $voting->refresh();

        return response()->json([
            'message' => 'Suara berhasil disimpan.',
            'suara' => $suara->load(['opsiVoting', 'user']),
            'total_suara' => $voting->total_suara,
            'hasil_rekap' => $voting->hasil_rekap,
            'ringkasan' => [
                'total_suara' => $voting->total_suara,
                'hasil_rekap' => $voting->hasil_rekap,
            ],
        ], 201);
    }
}
