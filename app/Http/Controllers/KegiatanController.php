<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class KegiatanController extends CrudController
{
    protected string $model = Kegiatan::class;
    protected array $with = ['user'];

    protected function rules(?int $id = null): array
    {
        return [
            'id_user' => ['required','exists:users,id_user'],
            'nama_kegiatan' => ['required','string','max:255'],
            'deskripsi' => ['nullable','string'],
            'tanggal_kegiatan' => ['required','date'],
            'dokumentasi' => ['nullable','string','max:255'],
        ];
    }

    #[OA\Get(
        path: '/kegiatan',
        summary: 'Daftar Agenda Kegiatan Senat',
        tags: ['Kegiatan'],
        responses: [
            new OA\Response(response: 200, description: 'Daftar agenda kegiatan senat terpaginasi')
        ]
    )]
    public function index()
    {
        return parent::index();
    }

    #[OA\Post(
        path: '/kegiatan',
        summary: 'Tambah Agenda Kegiatan Baru',
        tags: ['Kegiatan'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_user', 'nama_kegiatan', 'tanggal_kegiatan'],
                properties: [
                    new OA\Property(property: 'id_user', type: 'integer', example: 1),
                    new OA\Property(property: 'nama_kegiatan', type: 'string', example: 'Kunjungan Kerja Forum Senat Politeknik Nasional'),
                    new OA\Property(property: 'deskripsi', type: 'string', example: 'Koordinasi tata kelola kurikulum vokasi'),
                    new OA\Property(property: 'tanggal_kegiatan', type: 'string', format: 'date', example: '2026-11-20'),
                    new OA\Property(property: 'dokumentasi', type: 'string', nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Kegiatan berhasil dibuat'),
            new OA\Response(response: 422, description: 'Validasi gagal')
        ]
    )]
    public function store(Request $request)
    {
        return parent::store($request);
    }

    #[OA\Get(
        path: '/kegiatan/{id}',
        summary: 'Detail Agenda Kegiatan',
        tags: ['Kegiatan'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Detail kegiatan'),
            new OA\Response(response: 404, description: 'Kegiatan tidak ditemukan')
        ]
    )]
    public function show($id)
    {
        return parent::show($id);
    }

    #[OA\Put(
        path: '/kegiatan/{id}',
        summary: 'Perbarui Agenda Kegiatan',
        tags: ['Kegiatan'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nama_kegiatan', type: 'string', example: 'Workshop Pengembangan Standar Akademik'),
                    new OA\Property(property: 'deskripsi', type: 'string'),
                    new OA\Property(property: 'tanggal_kegiatan', type: 'string', format: 'date')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Kegiatan berhasil diperbarui')
        ]
    )]
    public function update(Request $request, $id)
    {
        return parent::update($request, $id);
    }

    #[OA\Delete(
        path: '/kegiatan/{id}',
        summary: 'Hapus Agenda Kegiatan',
        tags: ['Kegiatan'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Kegiatan berhasil dihapus')
        ]
    )]
    public function destroy($id)
    {
        return parent::destroy($id);
    }
}
