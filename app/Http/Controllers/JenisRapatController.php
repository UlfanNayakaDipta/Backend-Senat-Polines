<?php

namespace App\Http\Controllers;

use App\Models\JenisRapat;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class JenisRapatController extends CrudController
{
    protected string $model = JenisRapat::class;
    protected array $with = [];

    protected function rules(?int $id = null): array
    {
        return [
            'nama_jenis' => ['required','string','max:255'],
        ];
    }

    #[OA\Get(
        path: '/jenis-rapat',
        summary: 'Daftar Kategori / Jenis Rapat',
        tags: ['Master Data'],
        responses: [
            new OA\Response(response: 200, description: 'Daftar jenis rapat (Sidang Pleno, Rapat Komisi, Rapat Pimpinan, dll)')
        ]
    )]
    public function index()
    {
        return parent::index();
    }

    #[OA\Post(
        path: '/jenis-rapat',
        summary: 'Tambah Jenis Rapat Baru',
        tags: ['Master Data'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nama_jenis'],
                properties: [
                    new OA\Property(property: 'nama_jenis', type: 'string', example: 'Sidang Pleno')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Jenis rapat berhasil dibuat'),
            new OA\Response(response: 422, description: 'Validasi gagal')
        ]
    )]
    public function store(Request $request)
    {
        return parent::store($request);
    }

    #[OA\Get(
        path: '/jenis-rapat/{id}',
        summary: 'Detail Jenis Rapat',
        tags: ['Master Data'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Detail jenis rapat'),
            new OA\Response(response: 404, description: 'Jenis rapat tidak ditemukan')
        ]
    )]
    public function show($id)
    {
        return parent::show($id);
    }

    #[OA\Put(
        path: '/jenis-rapat/{id}',
        summary: 'Perbarui Jenis Rapat',
        tags: ['Master Data'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nama_jenis', type: 'string', example: 'Rapat Dengar Pendapat')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Jenis rapat berhasil diperbarui')
        ]
    )]
    public function update(Request $request, $id)
    {
        return parent::update($request, $id);
    }

    #[OA\Delete(
        path: '/jenis-rapat/{id}',
        summary: 'Hapus Jenis Rapat',
        tags: ['Master Data'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Jenis rapat berhasil dihapus')
        ]
    )]
    public function destroy($id)
    {
        return parent::destroy($id);
    }
}
