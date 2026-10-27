<?php

namespace App\Http\Controllers;

use App\Models\OpsiVoting;
use App\Models\SuaraVoting;
use App\Models\Voting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

