<?php

namespace App\Http\Controllers;

use App\Models\Aspirasi;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AspirasiController extends CrudController
{
    protected string $model = Aspirasi::class;
    protected array $with = ['user', 'kategori', 'tanggapans.user'];

    protected function rules(?int $id = null): array
    {
        return [
            'id_user' => ['required','exists:users,id_user'],
            'id_kategori' => ['required','exists:kategori_aspirasis,id_kategori'],
            'isi_aspirasi' => ['required','string'],
            'lampiran' => ['nullable','string','max:255'],
            'status' => ['nullable','string','max:255'],
            'anonim' => ['boolean'],
        ];
    }

    #[OA\Get(
        path: '/aspirasi',
        summary: 'Daftar Aspirasi Sivitas Akademika',
        description: 'Mengambil daftar aspirasi publik beserta relasi user, kategori, dan tanggapans.user.',
        tags: ['Aspirasi'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Jumlah item per halaman (default 15)', schema: new OA\Schema(type: 'integer', example: 15)),
            new OA\Parameter(name: 'all', in: 'query', required: false, description: 'Set true untuk mengambil semua data tanpa paginasi', schema: new OA\Schema(type: 'boolean', example: false)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar aspirasi terpaginasi')
        ]
    )]
    public function index()
    {
        return parent::index();
    }

    #[OA\Post(
        path: '/aspirasi',
        summary: 'Kirim Aspirasi Baru',
        tags: ['Aspirasi'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_user', 'id_kategori', 'isi_aspirasi'],
                properties: [
                    new OA\Property(property: 'id_user', type: 'integer', example: 1),
                    new OA\Property(property: 'id_kategori', type: 'integer', example: 1),
                    new OA\Property(property: 'isi_aspirasi', type: 'string', example: 'Pengusulan penambahan fasilitas hotspot wifi di laboratorium gedung Elektro.'),
                    new OA\Property(property: 'lampiran', type: 'string', nullable: true),
                    new OA\Property(property: 'status', type: 'string', example: 'Menunggu'),
                    new OA\Property(property: 'anonim', type: 'boolean', example: false)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Aspirasi berhasil dikirimkan'),
            new OA\Response(response: 422, description: 'Validasi data gagal')
        ]
    )]
    public function store(Request $request)
    {
        return parent::store($request);
    }

    #[OA\Get(
        path: '/aspirasi/{id}',
        summary: 'Detail Aspirasi',
        tags: ['Aspirasi'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Detail aspirasi beserta tanggapan'),
            new OA\Response(response: 404, description: 'Aspirasi tidak ditemukan')
        ]
    )]
    public function show($id)
    {
        return parent::show($id);
    }

    #[OA\Put(
        path: '/aspirasi/{id}',
        summary: 'Perbarui Status/Isi Aspirasi',
        description: 'Memperbarui status tindak lanjut atau data aspirasi. Khusus role admin, ketua_senat, atau sekretaris.',
        tags: ['Aspirasi'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'id_user', type: 'integer', example: 1),
                    new OA\Property(property: 'id_kategori', type: 'integer', example: 1),
                    new OA\Property(property: 'status', type: 'string', example: 'Diproses'),
                    new OA\Property(property: 'isi_aspirasi', type: 'string'),
                    new OA\Property(property: 'lampiran', type: 'string', nullable: true),
                    new OA\Property(property: 'anonim', type: 'boolean', example: false),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Aspirasi berhasil diperbarui'),
            new OA\Response(response: 401, description: 'Belum terautentikasi'),
            new OA\Response(response: 403, description: 'Akses ditolak (khusus admin/ketua_senat/sekretaris)'),
            new OA\Response(response: 404, description: 'Aspirasi tidak ditemukan'),
            new OA\Response(response: 422, description: 'Validasi data gagal'),
        ]
    )]
    public function update(Request $request, $id)
    {
        return parent::update($request, $id);
    }

    #[OA\Delete(
        path: '/aspirasi/{id}',
        summary: 'Hapus Aspirasi',
        description: 'Menghapus data aspirasi. Khusus role admin, ketua_senat, atau sekretaris.',
        tags: ['Aspirasi'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Aspirasi berhasil dihapus'),
            new OA\Response(response: 401, description: 'Belum terautentikasi'),
            new OA\Response(response: 403, description: 'Akses ditolak (khusus admin/ketua_senat/sekretaris)'),
            new OA\Response(response: 404, description: 'Aspirasi tidak ditemukan'),
        ]
    )]
    public function destroy($id)
    {
        return parent::destroy($id);
    }
}
