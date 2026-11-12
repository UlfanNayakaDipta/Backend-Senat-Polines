<?php

namespace App\Http\Controllers;

use App\Models\RapatLampiran;
use App\Support\RapatFileStore;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Lampiran notulensi: unggah berkas (multipart) atau daftarkan URL.
 */
class RapatLampiranController extends Controller
{
    #[OA\Get(
        path: '/rapat/{id_rapat}/lampiran',
        summary: 'Daftar Berkas Lampiran Notulensi Rapat',
        description: 'Mengambil seluruh lampiran dokumen pendukung rapat (PDF, Word, Excel, berkas unggahan atau URL tautan luar).',
        tags: ['Workspace Rapat'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id_rapat', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar lampiran rapat')
        ]
    )]
    public function index(Request $request, $id_rapat)
    {
        return response()->json(
            RapatLampiran::with('user:id_user,nama,role')
                ->where('id_rapat', (int) $id_rapat)
                ->latest('id_lampiran')
                ->get()
        );
    }

    #[OA\Post(
        path: '/rapat/{id_rapat}/lampiran',
        summary: 'Unggah Berkas atau Daftarkan URL Lampiran Rapat',
        description: 'Mengunggah berkas pendukung rapat hingga 1GB atau mendaftarkan URL dokumen eksternal.',
        tags: ['Workspace Rapat'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id_rapat', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['id_user'],
                    properties: [
                        new OA\Property(property: 'id_user', type: 'integer', example: 1),
                        new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'Berkas dokumen lampiran (PDF, Office, gambar, dll maks 1GB)'),
                        new OA\Property(property: 'nama', type: 'string', example: 'Draf Panduan Akademik 2026.pdf'),
                        new OA\Property(property: 'url', type: 'string', format: 'uri', example: 'https://docs.google.com/document/d/xyz')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Lampiran berhasil disimpan'),
            new OA\Response(response: 422, description: 'Validasi gagal (file atau url wajib diisi)')
        ]
    )]
    public function store(Request $request, $id_rapat)
    {
        $data = $request->validate([
            'id_user' => ['required', 'exists:users,id_user'],
            'file' => ['nullable', 'file', 'max:1024000'],
            'nama' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:2048'],
        ]);

        $adaFile = $request->hasFile('file');

        if (! $adaFile && empty($data['url'])) {
            return response()->json([
                'message' => 'Berkas atau URL lampiran wajib diisi.',
                'errors' => ['file' => ['Berkas atau URL lampiran wajib diisi.']],
            ], 422);
        }

        $meta = $adaFile
            ? RapatFileStore::store($request->file('file'), (int) $id_rapat)
            : [
                'nama' => $data['nama'] ?? basename(parse_url($data['url'], PHP_URL_PATH) ?: 'lampiran'),
                'url' => $data['url'],
                'ukuran' => 0,
                'mime' => null,
            ];

        if (! empty($data['nama'])) {
            $meta['nama'] = $data['nama'];
        }

        $lampiran = RapatLampiran::create([
            'id_rapat' => (int) $id_rapat,
            'id_user' => (int) $data['id_user'],
            'nama' => $meta['nama'],
            'url' => $meta['url'],
            'ukuran' => $meta['ukuran'],
            'mime' => $meta['mime'],
        ]);

        return response()->json($lampiran->load('user:id_user,nama,role'), 201);
    }

    #[OA\Delete(
        path: '/rapat/{id_rapat}/lampiran/{id_lampiran}',
        summary: 'Hapus Lampiran Rapat',
        tags: ['Workspace Rapat'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id_rapat', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'id_lampiran', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Lampiran berhasil dihapus'),
            new OA\Response(response: 404, description: 'Lampiran tidak ditemukan')
        ]
    )]
    public function destroy($id_rapat, $id_lampiran)
    {
        $lampiran = RapatLampiran::where('id_rapat', (int) $id_rapat)
            ->findOrFail((int) $id_lampiran);

        $lampiran->delete();

        return response()->noContent();
    }
}
