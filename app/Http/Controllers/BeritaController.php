<?php

namespace App\Http\Controllers;

use App\Models\Berita;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class BeritaController extends CrudController
{
    protected string $model = Berita::class;
    protected array $with = ['user'];

    protected function rules(?int $id = null): array
    {
        return [
            'id_user' => ['required','exists:users,id_user'],
            'judul' => ['required','string','max:255'],
            'isi' => ['required','string'],
            'gambar' => ['nullable','string','max:255'],
            'tanggal_publish' => ['nullable','date'],
        ];
    }

    #[OA\Get(
        path: '/berita',
        summary: 'Daftar Berita Senat Akademik',
        tags: ['Berita'],
        responses: [
            new OA\Response(response: 200, description: 'Daftar berita terpaginasi')
        ]
    )]
    public function index()
    {
        return parent::index();
    }

    #[OA\Post(
        path: '/berita',
        summary: 'Publikasi Berita Baru',
        tags: ['Berita'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_user', 'judul', 'isi'],
                properties: [
                    new OA\Property(property: 'id_user', type: 'integer', example: 1),
                    new OA\Property(property: 'judul', type: 'string', example: 'Sosialisasi Kebijakan Baru Kurikulum Terapan Polines'),
                    new OA\Property(property: 'isi', type: 'string', example: 'Senat Akademik menyelenggarakan sosialisasi kebijakan penyesuaian kurikulum...'),
                    new OA\Property(property: 'gambar', type: 'string', nullable: true),
                    new OA\Property(property: 'tanggal_publish', type: 'string', format: 'date', example: '2026-10-28')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Berita berhasil dipublikasikan'),
            new OA\Response(response: 422, description: 'Validasi gagal')
        ]
    )]
    public function store(Request $request)
    {
        return parent::store($request);
    }

    #[OA\Get(
        path: '/berita/{id}',
        summary: 'Detail Berita',
        tags: ['Berita'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Detail berita'),
            new OA\Response(response: 404, description: 'Berita tidak ditemukan')
        ]
    )]
    public function show($id)
    {
        return parent::show($id);
    }

    #[OA\Put(
        path: '/berita/{id}',
        summary: 'Perbarui Berita',
        tags: ['Berita'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'judul', type: 'string'),
                    new OA\Property(property: 'isi', type: 'string'),
                    new OA\Property(property: 'gambar', type: 'string')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Berita berhasil diperbarui')
        ]
    )]
    public function update(Request $request, $id)
    {
        return parent::update($request, $id);
    }

    #[OA\Delete(
        path: '/berita/{id}',
        summary: 'Hapus Berita',
        tags: ['Berita'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Berita berhasil dihapus')
        ]
    )]
    public function destroy($id)
    {
        return parent::destroy($id);
    }
}
