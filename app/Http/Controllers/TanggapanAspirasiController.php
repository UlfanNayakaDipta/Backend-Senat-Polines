<?php

namespace App\Http\Controllers;

use App\Models\TanggapanAspirasi;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TanggapanAspirasiController extends CrudController
{
    protected string $model = TanggapanAspirasi::class;
    protected array $with = ['user'];

    protected function rules(?int $id = null): array
    {
        return [
            'id_aspirasi' => ['required', 'exists:aspirasis,id_aspirasi'],
            'id_user' => ['required', 'exists:users,id_user'],
            'pesan' => ['required', 'string'],
        ];
    }

    #[OA\Get(
        path: '/tanggapan-aspirasi',
        summary: 'Daftar Tanggapan Aspirasi Senat',
        description: 'Mengambil daftar tanggapan resmi Senat Akademik atas aspirasi sivitas akademika (publik untuk transparansi).',
        tags: ['Aspirasi'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Jumlah item per halaman (default 15)', schema: new OA\Schema(type: 'integer', example: 15)),
            new OA\Parameter(name: 'all', in: 'query', required: false, description: 'Set true untuk mengambil semua data tanpa paginasi', schema: new OA\Schema(type: 'boolean', example: false)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar tanggapan aspirasi beserta data penanggap'),
        ]
    )]
    public function index()
    {
        return parent::index();
    }

    #[OA\Post(
        path: '/tanggapan-aspirasi',
        summary: 'Kirim Tanggapan Resmi Aspirasi',
        description: 'Menambahkan tanggapan resmi atas aspirasi tertentu. Khusus role admin, ketua_senat, atau sekretaris.',
        tags: ['Aspirasi'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_aspirasi', 'id_user', 'pesan'],
                properties: [
                    new OA\Property(property: 'id_aspirasi', type: 'integer', example: 1),
                    new OA\Property(property: 'id_user', type: 'integer', example: 1),
                    new OA\Property(property: 'pesan', type: 'string', example: 'Aspirasi telah diteruskan ke Komisi terkait dan sedang ditindaklanjuti.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Tanggapan aspirasi berhasil disimpan'),
            new OA\Response(response: 401, description: 'Belum terautentikasi'),
            new OA\Response(response: 403, description: 'Akses ditolak (khusus admin/ketua_senat/sekretaris)'),
            new OA\Response(response: 422, description: 'Validasi data gagal'),
        ]
    )]
    public function store(Request $request)
    {
        return parent::store($request);
    }

    #[OA\Get(
        path: '/tanggapan-aspirasi/{id}',
        summary: 'Detail Tanggapan Aspirasi',
        tags: ['Aspirasi'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Detail tanggapan aspirasi'),
            new OA\Response(response: 404, description: 'Tanggapan tidak ditemukan'),
        ]
    )]
    public function show($id)
    {
        return parent::show($id);
    }

    #[OA\Put(
        path: '/tanggapan-aspirasi/{id}',
        summary: 'Perbarui Tanggapan Aspirasi',
        description: 'Memperbarui isi tanggapan resmi aspirasi. Khusus role admin, ketua_senat, atau sekretaris.',
        tags: ['Aspirasi'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'id_aspirasi', type: 'integer', example: 1),
                    new OA\Property(property: 'id_user', type: 'integer', example: 1),
                    new OA\Property(property: 'pesan', type: 'string', example: 'Tindak lanjut telah dijadwalkan pada rapat pleno berikutnya.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Tanggapan aspirasi berhasil diperbarui'),
            new OA\Response(response: 401, description: 'Belum terautentikasi'),
            new OA\Response(response: 403, description: 'Akses ditolak'),
            new OA\Response(response: 404, description: 'Tanggapan tidak ditemukan'),
            new OA\Response(response: 422, description: 'Validasi data gagal'),
        ]
    )]
    public function update(Request $request, $id)
    {
        return parent::update($request, $id);
    }

    #[OA\Delete(
        path: '/tanggapan-aspirasi/{id}',
        summary: 'Hapus Tanggapan Aspirasi',
        description: 'Menghapus tanggapan aspirasi. Khusus role admin, ketua_senat, atau sekretaris.',
        tags: ['Aspirasi'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Tanggapan aspirasi berhasil dihapus'),
            new OA\Response(response: 401, description: 'Belum terautentikasi'),
            new OA\Response(response: 403, description: 'Akses ditolak'),
            new OA\Response(response: 404, description: 'Tanggapan tidak ditemukan'),
        ]
    )]
    public function destroy($id)
    {
        return parent::destroy($id);
    }
}
