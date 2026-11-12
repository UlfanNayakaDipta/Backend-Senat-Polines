<?php

namespace App\Http\Controllers;

use App\Models\RapatBerbagi;
use App\Support\RapatFileStore;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Berbagi dokumen & tautan antar anggota pada satu rapat.
 */
class RapatBerbagiController extends Controller
{
    #[OA\Get(
        path: '/rapat/{id_rapat}/berbagi',
        summary: 'Daftar Berkas & Tautan Berbagi dalam Rapat',
        description: 'Mengambil daftar dokumen dan link eksternal yang dibagikan antar anggota pada ruang kerja rapat.',
        tags: ['Workspace Rapat'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id_rapat', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar item berbagi berhasil dimuat')
        ]
    )]
    public function index(Request $request, $id_rapat)
    {
        return response()->json(
            RapatBerbagi::with('user:id_user,nama,role')
                ->where('id_rapat', (int) $id_rapat)
                ->latest('id_berbagi')
                ->get()
        );
    }

    #[OA\Post(
        path: '/rapat/{id_rapat}/berbagi',
        summary: 'Bagikan Berkas atau Tautan ke Peserta Rapat',
        description: 'Membagikan materi tambahan (berkas unggahan hingga 1GB atau tautan web) kepada seluruh peserta rapat.',
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
                        new OA\Property(property: 'id_user', type: 'integer', example: 6),
                        new OA\Property(property: 'jenis', type: 'string', enum: ['file', 'link'], example: 'link'),
                        new OA\Property(property: 'nama', type: 'string', example: 'Materi Paparan Rektorat.pdf'),
                        new OA\Property(property: 'url', type: 'string', format: 'uri', example: 'https://polines.ac.id/materi-sidang'),
                        new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'Berkas materi (opsional jika url diisi)')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Item berhasil dibagikan'),
            new OA\Response(response: 422, description: 'Validasi gagal')
        ]
    )]
    public function store(Request $request, $id_rapat)
    {
        $data = $request->validate([
            'id_user' => ['required', 'exists:users,id_user'],
            'jenis' => ['nullable', 'in:file,link'],
            'nama' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:2048'],
            'file' => ['nullable', 'file', 'max:1024000'],
        ]);

        if ($request->hasFile('file')) {
            $meta = RapatFileStore::store($request->file('file'), (int) $id_rapat);
            $jenis = 'file';
        } else {
            $url = $data['url'] ?? null;

            if (! $url || ! preg_match('#^https?://#i', $url)) {
                return response()->json([
                    'message' => 'Tautan harus diawali http:// atau https://',
                    'errors' => ['url' => ['Tautan harus diawali http:// atau https://']],
                ], 422);
            }

            $jenis = $data['jenis'] ?? 'link';
            $meta = [
                'nama' => $data['nama'] ?? (parse_url($url, PHP_URL_HOST) ?: $url),
                'url' => $url,
                'ukuran' => 0,
                'mime' => null,
            ];
        }

        $item = RapatBerbagi::create([
            'id_rapat' => (int) $id_rapat,
            'id_user' => (int) $data['id_user'],
            'jenis' => $jenis,
            'nama' => $meta['nama'],
            'url' => $meta['url'],
            'ukuran' => $meta['ukuran'],
            'mime' => $meta['mime'] ?? null,
        ]);

        return response()->json($item->load('user:id_user,nama,role'), 201);
    }

    #[OA\Delete(
        path: '/rapat/{id_rapat}/berbagi/{id_berbagi}',
        summary: 'Hapus Item Berbagi Rapat',
        tags: ['Workspace Rapat'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id_rapat', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'id_berbagi', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Item berhasil dihapus'),
            new OA\Response(response: 404, description: 'Item tidak ditemukan')
        ]
    )]
    public function destroy($id_rapat, $id_berbagi)
    {
        RapatBerbagi::where('id_rapat', (int) $id_rapat)
            ->findOrFail((int) $id_berbagi)
            ->delete();

        return response()->noContent();
    }
}
