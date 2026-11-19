<?php

namespace App\Http\Controllers;

use App\Models\OpsiVoting;
use App\Models\Voting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class OpsiVotingController extends CrudController
{
    protected string $model = OpsiVoting::class;
    protected array $with = [];

    protected function rules(?int $id = null): array
    {
        return [
            'id_voting' => ['required','exists:votings,id_voting'],
            'nama_opsi' => ['required','string','max:255'],
        ];
    }

    private function locked(callable $callback): mixed
    {
        return Cache::store('file')->lock('evoting-write', 30)->block(10, fn () => DB::transaction($callback));
    }

    private function editable(int $idVoting): Voting
    {
        $voting = Voting::whereKey($idVoting)->lockForUpdate()->firstOrFail();
        $voting->assertEditable();

        return $voting;
    }

    #[OA\Post(
        path: '/opsi-voting',
        summary: 'Tambah Opsi Voting',
        description: 'Hanya boleh menambah opsi pada sesi draft yang belum pernah dibuka dan belum bersuara; sesi lain ditolak 422. Ditulis dalam kunci bersama evoting-write.',
        tags: ['Voting'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['id_voting', 'nama_opsi'],
            properties: [
                new OA\Property(property: 'id_voting', type: 'integer', example: 1),
                new OA\Property(property: 'nama_opsi', type: 'string', example: 'Setuju'),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'Opsi dibuat'),
            new OA\Response(response: 401, description: 'Belum terautentikasi'),
            new OA\Response(response: 403, description: 'Akses ditolak (hanya admin/ketua senat/sekretaris)'),
            new OA\Response(response: 422, description: 'Sesi bukan draft kosong atau payload tidak valid'),
        ]
    )]
    public function store(Request $request)
    {
        return $this->locked(function () use ($request) {
            $data = $request->validate([
                'id_voting' => ['required', 'integer', Rule::exists('votings', 'id_voting')],
                'nama_opsi' => ['required', 'string', 'max:255'],
            ]);
            $this->editable($data['id_voting']);

            return response()->json($this->model::create($data), 201);
        });
    }

    #[OA\Put(
        path: '/opsi-voting/{opsi_voting}',
        summary: 'Ubah Opsi Voting',
        description: 'Hanya boleh mengubah nama/parent opsi bila parent lama DAN parent baru sama-sama draft kosong yang belum pernah dibuka; selain itu 422. Ditulis dalam kunci bersama evoting-write.',
        tags: ['Voting'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'opsi_voting', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'id_voting', type: 'integer'),
            new OA\Property(property: 'nama_opsi', type: 'string'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Opsi diperbarui'),
            new OA\Response(response: 401, description: 'Belum terautentikasi'),
            new OA\Response(response: 403, description: 'Akses ditolak (hanya admin/ketua senat/sekretaris)'),
            new OA\Response(response: 404, description: 'Opsi tidak ditemukan'),
            new OA\Response(response: 422, description: 'Parent lama atau baru bukan draft kosong'),
        ]
    )]
    public function update(Request $request, $id)
    {
        return $this->locked(function () use ($request, $id) {
            $opsi = OpsiVoting::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->editable($opsi->id_voting);
            $data = $request->validate([
                'id_voting' => ['sometimes', 'required', 'integer', 'exists:votings,id_voting'],
                'nama_opsi' => ['sometimes', 'required', 'string', 'max:255'],
            ]);
            if (isset($data['id_voting']) && $data['id_voting'] != $opsi->id_voting) {
                $this->editable($data['id_voting']);
            }
            $opsi->update($data);

            return $opsi;
        });
    }

    #[OA\Delete(
        path: '/opsi-voting/{opsi_voting}',
        summary: 'Hapus Opsi Voting',
        description: 'Hanya boleh menghapus opsi pada sesi draft kosong yang belum pernah dibuka dan belum bersuara; ballot yang sudah ada tidak boleh kehilangan opsinya (422). Ditulis dalam kunci bersama evoting-write.',
        tags: ['Voting'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'opsi_voting', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Opsi dihapus'),
            new OA\Response(response: 401, description: 'Belum terautentikasi'),
            new OA\Response(response: 403, description: 'Akses ditolak (hanya admin/ketua senat/sekretaris)'),
            new OA\Response(response: 404, description: 'Opsi tidak ditemukan'),
            new OA\Response(response: 422, description: 'Sesi bukan draft kosong atau sudah memiliki suara'),
        ]
    )]
    public function destroy($id)
    {
        return $this->locked(function () use ($id) {
            $opsi = OpsiVoting::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->editable($opsi->id_voting);
            $opsi->delete();

            return response()->noContent();
        });
    }
}
