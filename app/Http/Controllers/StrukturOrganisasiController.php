<?php

namespace App\Http\Controllers;

use App\Models\StrukturOrganisasi;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class StrukturOrganisasiController extends CrudController
{
    protected string $model = StrukturOrganisasi::class;
    protected array $with = ['enrollment.user', 'enrollment.komisi'];

    protected function rules(?int $id = null): array
    {
        return [
            'id_enrollment' => ['required','exists:enrollments,id_enrollment'],
            'jabatan' => ['required','string','max:255'],
            'periode' => ['required','string','max:255'],
            'urutan_bagan' => ['integer','min:0'],
        ];
    }

    #[OA\Get(
        path: '/struktur-organisasi',
        summary: 'Daftar Posisi Struktur Organisasi Senat',
        tags: ['Master Data'],
        responses: [
            new OA\Response(response: 200, description: 'Daftar struktur bagan organisasi senat')
        ]
    )]
    public function index()
    {
        return parent::index();
    }

    #[OA\Post(
        path: '/struktur-organisasi',
        summary: 'Tambah Posisi Struktur Organisasi',
        tags: ['Master Data'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_enrollment', 'jabatan', 'periode'],
                properties: [
                    new OA\Property(property: 'id_enrollment', type: 'integer', example: 1),
                    new OA\Property(property: 'jabatan', type: 'string', example: 'Ketua Senat'),
                    new OA\Property(property: 'periode', type: 'string', example: '2026–2029'),
                    new OA\Property(property: 'urutan_bagan', type: 'integer', example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Posisi struktur berhasil dibuat'),
            new OA\Response(response: 422, description: 'Validasi gagal')
        ]
    )]
    public function store(Request $request)
    {
        return parent::store($request);
    }

    #[OA\Get(
        path: '/struktur-organisasi/{id}',
        summary: 'Detail Posisi Struktur Organisasi',
        tags: ['Master Data'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Detail posisi struktur organisasi'),
            new OA\Response(response: 404, description: 'Posisi tidak ditemukan')
        ]
    )]
    public function show($id)
    {
        return parent::show($id);
    }

    #[OA\Put(
        path: '/struktur-organisasi/{id}',
        summary: 'Perbarui Posisi Struktur Organisasi',
        tags: ['Master Data'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'jabatan', type: 'string', example: 'Sekretaris Senat'),
                    new OA\Property(property: 'urutan_bagan', type: 'integer', example: 2)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Struktur organisasi berhasil diperbarui')
        ]
    )]
    public function update(Request $request, $id)
    {
        return parent::update($request, $id);
    }

    #[OA\Delete(
        path: '/struktur-organisasi/{id}',
        summary: 'Hapus Posisi Struktur Organisasi',
        tags: ['Master Data'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Posisi berhasil dihapus')
        ]
    )]
    public function destroy($id)
    {
        return parent::destroy($id);
    }
}
