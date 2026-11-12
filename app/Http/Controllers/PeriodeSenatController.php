<?php

namespace App\Http\Controllers;

use App\Models\PeriodeSenat;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PeriodeSenatController extends CrudController
{
    protected string $model = PeriodeSenat::class;
    protected array $with = [];

    protected function rules(?int $id = null): array
    {
        return [
            'nama_periode' => ['required','string','max:255'],
            'tanggal_mulai' => ['required','date'],
            'tanggal_selesai' => ['required','date','after_or_equal:tanggal_mulai'],
        ];
    }

    #[OA\Get(
        path: '/periode-senat',
        summary: 'Daftar Periode Masa Bakti Senat',
        tags: ['Master Data'],
        responses: [
            new OA\Response(response: 200, description: 'Daftar periode masa jabatan senat')
        ]
    )]
    public function index()
    {
        return parent::index();
    }

    #[OA\Post(
        path: '/periode-senat',
        summary: 'Tambah Periode Masa Bakti Baru',
        tags: ['Master Data'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nama_periode', 'tanggal_mulai', 'tanggal_selesai'],
                properties: [
                    new OA\Property(property: 'nama_periode', type: 'string', example: '2026–2029'),
                    new OA\Property(property: 'tanggal_mulai', type: 'string', format: 'date', example: '2026-01-01'),
                    new OA\Property(property: 'tanggal_selesai', type: 'string', format: 'date', example: '2029-12-31')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Periode berhasil dibuat'),
            new OA\Response(response: 422, description: 'Validasi gagal')
        ]
    )]
    public function store(Request $request)
    {
        return parent::store($request);
    }

    #[OA\Get(
        path: '/periode-senat/{id}',
        summary: 'Detail Periode Senat',
        tags: ['Master Data'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Detail periode'),
            new OA\Response(response: 404, description: 'Periode tidak ditemukan')
        ]
    )]
    public function show($id)
    {
        return parent::show($id);
    }

    #[OA\Put(
        path: '/periode-senat/{id}',
        summary: 'Perbarui Periode Senat',
        tags: ['Master Data'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nama_periode', type: 'string', example: '2026–2029'),
                    new OA\Property(property: 'tanggal_mulai', type: 'string', format: 'date'),
                    new OA\Property(property: 'tanggal_selesai', type: 'string', format: 'date')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Periode berhasil diperbarui')
        ]
    )]
    public function update(Request $request, $id)
    {
        return parent::update($request, $id);
    }

    #[OA\Delete(
        path: '/periode-senat/{id}',
        summary: 'Hapus Periode Senat',
        tags: ['Master Data'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Periode berhasil dihapus')
        ]
    )]
    public function destroy($id)
    {
        return parent::destroy($id);
    }
}
