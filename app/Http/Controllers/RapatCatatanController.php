<?php

namespace App\Http\Controllers;

use App\Models\RapatCatatan;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Catatan risalah anggota per rapat (satu catatan per anggota per rapat).
 */
class RapatCatatanController extends Controller
{
    #[OA\Get(
        path: '/rapat/{id_rapat}/catatan',
        summary: 'Daftar Catatan Risalah Anggota pada Rapat',
        description: 'Mengambil catatan pribadi peserta atau seluruh catatan peserta rapat (filter id_user opsional).',
        tags: ['Workspace Rapat'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id_rapat', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'id_user', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 6))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar catatan rapat')
        ]
    )]
    public function index(Request $request, $id_rapat)
    {
        $query = RapatCatatan::with('user:id_user,nama,role')
            ->where('id_rapat', (int) $id_rapat);

        if ($request->filled('id_user')) {
            $query->where('id_user', $request->integer('id_user'));
        }

        return response()->json($query->get());
    }

    /** Simpan / perbarui catatan milik satu anggota pada satu rapat. */
    #[OA\Post(
        path: '/rapat/{id_rapat}/catatan',
        summary: 'Simpan / Perbarui Catatan Risalah Anggota',
        description: 'Menyimpan atau memperbarui catatan pribadi anggota pada sesi rapat tertentu (upsert per id_user & id_rapat).',
        tags: ['Workspace Rapat'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id_rapat', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_user'],
                properties: [
                    new OA\Property(property: 'id_user', type: 'integer', example: 6),
                    new OA\Property(property: 'isi', type: 'string', example: 'Poin penting: penyesuaian bobot SKS magang industri.')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Catatan berhasil disimpan'),
            new OA\Response(response: 422, description: 'Validasi data catatan gagal')
        ]
    )]
    public function upsert(Request $request, $id_rapat)
    {
        $data = $request->validate([
            'id_user' => ['required', 'exists:users,id_user'],
            'isi' => ['nullable', 'string'],
        ]);

        $catatan = RapatCatatan::updateOrCreate(
            [
                'id_rapat' => (int) $id_rapat,
                'id_user' => (int) $data['id_user'],
            ],
            ['isi' => $data['isi'] ?? '']
        );

        return response()->json($catatan->load('user:id_user,nama,role'), 200);
    }
}
