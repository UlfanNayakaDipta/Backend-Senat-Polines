<?php

namespace App\Http\Controllers;

use App\Models\KategoriAspirasi;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class KategoriAspirasiController extends CrudController
{
    protected string $model = KategoriAspirasi::class;
    protected array $with = [];

    protected function rules(?int $id = null): array
    {
        return [
            'nama_kategori' => ['required','string','max:255'],
        ];
    }

    #[OA\Get(
        path: '/kategori-aspirasi',
        summary: 'Daftar Kategori Aspirasi Sivitas',
        tags: ['Aspirasi'],
        responses: [
            new OA\Response(response: 200, description: 'Daftar kategori aspirasi (Akademik, Kemahasiswaan, Sarana & Prasarana, dll)')
        ]
    )]
    public function index()
    {
        return parent::index();
    }

    #[OA\Post(
        path: '/kategori-aspirasi',
        summary: 'Tambah Kategori Aspirasi Baru',
        tags: ['Aspirasi'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nama_kategori'],
                properties: [
                    new OA\Property(property: 'nama_kategori', type: 'string', example: 'Sarana & Prasarana Kampus')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Kategori aspirasi berhasil dibuat'),
            new OA\Response(response: 422, description: 'Validasi gagal')
        ]
    )]
    public function store(Request $request)
    {
        return parent::store($request);
    }

    #[OA\Get(
        path: '/kategori-aspirasi/{id}',
        summary: 'Detail Kategori Aspirasi',
        tags: ['Aspirasi'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Detail kategori aspirasi'),
            new OA\Response(response: 404, description: 'Kategori tidak ditemukan')
        ]
    )]
    public function show($id)
    {
        return parent::show($id);
    }

    #[OA\Put(
        path: '/kategori-aspirasi/{id}',
        summary: 'Perbarui Kategori Aspirasi',
        tags: ['Aspirasi'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nama_kategori', type: 'string', example: 'Tata Kelola & Kelembagaan')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Kategori berhasil diperbarui')
        ]
    )]
    public function update(Request $request, $id)
    {
        return parent::update($request, $id);
    }

    #[OA\Delete(
        path: '/kategori-aspirasi/{id}',
        summary: 'Hapus Kategori Aspirasi',
        tags: ['Aspirasi'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Kategori berhasil dihapus')
        ]
    )]
    public function destroy($id)
    {
        return parent::destroy($id);
    }
}
