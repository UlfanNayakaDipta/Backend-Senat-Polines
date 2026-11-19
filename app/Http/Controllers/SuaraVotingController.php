<?php

namespace App\Http\Controllers;

use App\Models\OpsiVoting;
use App\Models\SuaraVoting;
use App\Models\Voting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class SuaraVotingController extends CrudController
{
    protected string $model = SuaraVoting::class;
    protected array $with = ['opsiVoting', 'user'];

    #[OA\Get(
        path: '/voting/{id}/my-ballot',
        summary: 'Suara Milik Pemilih yang Sedang Login',
        description: 'Mengembalikan status dan isi suara milik pengguna yang terautentikasi pada satu sesi voting. Identitas HANYA diambil dari token; parameter id_user pada query/body diabaikan. Nilai eligible/is_invited/is_present menjelaskan alasan bila pengguna belum berhak memilih. Endpoint selalu 200 untuk sesi yang ada, termasuk bila pengguna tidak memenuhi syarat.',
        tags: ['Voting'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Status suara milik pengguna', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'has_voted', type: 'boolean'),
                new OA\Property(property: 'my_ballot', nullable: true, properties: [
                    new OA\Property(property: 'id_opsi', type: 'integer'),
                    new OA\Property(property: 'nama_opsi', type: 'string'),
                    new OA\Property(property: 'waktu_vote', type: 'string', nullable: true),
                ], type: 'object'),
                new OA\Property(property: 'eligible', type: 'boolean'),
                new OA\Property(property: 'is_invited', type: 'boolean'),
                new OA\Property(property: 'is_present', type: 'boolean'),
            ])),
            new OA\Response(response: 401, description: 'Belum terautentikasi'),
            new OA\Response(response: 404, description: 'Sesi voting tidak ditemukan'),
        ]
    )]
    public function myBallot(Request $request, $id): JsonResponse
    {
        $voting = Voting::findOrFail($id);
        $ballot = $voting->suaraVotings()->with('opsiVoting')
            ->where('id_user', $request->user()->id_user)->first();

        return response()->json([
            'has_voted' => $ballot !== null,
            'my_ballot' => $ballot ? [
                'id_opsi' => (int) $ballot->id_opsi,
                'nama_opsi' => (string) $ballot->opsiVoting->nama_opsi,
                'waktu_vote' => $ballot->waktu_vote?->toISOString(),
            ] : null,
            ...$voting->eligibility($request->user()),
        ]);
    }

    #[OA\Delete(
        path: '/suara-voting/{id}',
        summary: 'Hapus Suara (Tidak Diizinkan)',
        description: 'Penghapusan suara DILARANG sepenuhnya agar rekap tidak berubah tanpa jejak. Route mengembalikan 405 Method Not Allowed dan tidak menghapus data apa pun. Koreksi hanya melalui prosedur sidang, bukan penghapusan ballot.',
        tags: ['Voting'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 405, description: 'Metode tidak diizinkan; ballot bersifat permanen'),
            new OA\Response(response: 401, description: 'Belum terautentikasi'),
        ]
    )]
    public function destroyDenied($id): JsonResponse
    {
        return response()->json(['message' => 'Method Not Allowed'], 405);
    }

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
        description: 'Daftar rincian suara pemungutan suara sidang. Dibatasi khusus peran sekretariat dan administrator untuk melindungi kerahasiaan pilihan pemilih.',
        tags: ['Voting'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Daftar perolehan suara terpaginasi'),
            new OA\Response(response: 401, description: 'Belum terautentikasi'),
            new OA\Response(response: 403, description: 'Akses ditolak (hanya sekretariat/admin)')
        ]
    )]
    public function index()
    {
        return parent::index();
    }

    #[OA\Post(
        path: '/suara-voting',
        summary: 'Submit Suara E-Voting Sidang Senat',
        description: 'Mencatat pilihan suara anggota senat pada sesi voting yang aktif (satu anggota satu suara). Identitas pemilih HANYA diambil dari token autentikasi; field id_user pada body diabaikan. Hanya senator terundang (peran anggota/ketua_senat/sekretaris) dengan status_kehadiran tepat "hadir" yang boleh memilih.',
        tags: ['Voting'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_voting', 'id_opsi'],
                properties: [
                    new OA\Property(property: 'id_voting', type: 'integer', example: 1),
                    new OA\Property(property: 'id_opsi', type: 'integer', example: 1),
                    new OA\Property(property: 'id_user', type: 'integer', nullable: true, description: 'Diabaikan; identitas selalu dari token.', example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Suara berhasil disimpan dan rekapitulasi diperbarui'
            ),
            new OA\Response(response: 401, description: 'Belum terautentikasi'),
            new OA\Response(
                response: 403,
                description: 'Bukan senator, tidak diundang pada rapat, atau status kehadiran bukan hadir'
            ),
            new OA\Response(
                response: 422,
                description: 'Sesi belum dibuka/di luar jendela waktu, sudah memberikan suara, atau opsi tidak valid'
            )
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_voting' => ['required', 'integer', 'exists:votings,id_voting'],
            'id_opsi' => ['required', 'integer'],
        ]);

        try {
            return Cache::store('file')->lock('evoting-write', 30)->block(10, fn () => DB::transaction(
                fn () => $this->recordVote($request)
            ));
        } catch (\Illuminate\Database\UniqueConstraintViolationException $exception) {
            $detail = $exception->errorInfo[2] ?? '';
            if (! str_contains($detail, 'suara_votings_id_voting_id_user_unique')
                && ! str_contains($detail, 'UNIQUE constraint failed: suara_votings.id_voting, suara_votings.id_user')) {
                throw $exception;
            }

            return response()->json(['message' => 'Anda sudah memberikan suara pada sesi voting ini.'], 422);
        }
    }

    private function recordVote(Request $request): JsonResponse
    {
        $idVoting = $request->input('id_voting');
        $voting = Voting::whereKey($idVoting)->lockForUpdate()->first();

        if (! $voting || ! $voting->isUsable()) {
            return response()->json([
                'message' => 'Voting sedang ditutup atau belum dibuka.',
            ], 422);
        }

        if (! $voting->eligibility($request->user())['eligible']) {
            return response()->json(['message' => 'Anda tidak berhak memberikan suara.'], 403);
        }

        $idUser = $request->user()->id_user;
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
            ->lockForUpdate()->first();

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
