<?php

namespace App\Http\Controllers;

use App\Models\Rapat;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class RapatController extends CrudController
{
    protected string $model = Rapat::class;
    protected array $with = ['jenisRapat', 'kegiatan', 'notulensi'];

    protected function rules(?int $id = null): array
    {
        return [
            'id_kegiatan' => ['nullable','exists:kegiatans,id_kegiatan'],
            'id_user' => ['required','exists:users,id_user'],
            'id_jenis_rapat' => ['required','exists:jenis_rapats,id_jenis_rapat'],
            'nama_agenda' => ['required','string','max:255'],
            'tanggal' => ['required','date'],
            'waktu' => ['required','date_format:H:i'],
            'tempat' => ['required','string','max:255'],
            'status' => ['nullable','string','max:255'],
        ];
    }

    #[OA\Get(
        path: '/rapat',
        summary: 'Daftar Agenda Rapat & Sidang Senat',
        tags: ['Rapat & Sidang'],
        responses: [
            new OA\Response(response: 200, description: 'Daftar data rapat terpaginasi')
        ]
    )]
    public function index()
    {
        return parent::index();
    }

    #[OA\Post(
        path: '/rapat',
        summary: 'Tambah Agenda Rapat Baru',
        tags: ['Rapat & Sidang'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_user', 'id_jenis_rapat', 'nama_agenda', 'tanggal', 'waktu', 'tempat'],
                properties: [
                    new OA\Property(property: 'id_kegiatan', type: 'integer', nullable: true),
                    new OA\Property(property: 'id_user', type: 'integer', example: 1),
                    new OA\Property(property: 'id_jenis_rapat', type: 'integer', example: 1),
                    new OA\Property(property: 'nama_agenda', type: 'string', example: 'Rapat Pleno Senat Penetapan Kalender Akademik'),
                    new OA\Property(property: 'tanggal', type: 'string', format: 'date', example: '2026-11-01'),
                    new OA\Property(property: 'waktu', type: 'string', example: '09:00'),
                    new OA\Property(property: 'tempat', type: 'string', example: 'Ruang Sidang Direksi Lt. 2'),
                    new OA\Property(property: 'status', type: 'string', example: 'Terjadwal')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Rapat berhasil dibuat'),
            new OA\Response(response: 422, description: 'Validasi gagal')
        ]
    )]
    public function store(Request $request)
    {
        return parent::store($request);
    }

    #[OA\Get(
        path: '/rapat/{id}',
        summary: 'Detail Agenda Rapat',
        tags: ['Rapat & Sidang'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Detail data rapat'),
            new OA\Response(response: 404, description: 'Data tidak ditemukan')
        ]
    )]
    public function show($id)
    {
        return parent::show($id);
    }

    #[OA\Put(
        path: '/rapat/{id}',
        summary: 'Perbarui Agenda Rapat',
        tags: ['Rapat & Sidang'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nama_agenda', type: 'string'),
                    new OA\Property(property: 'tanggal', type: 'string', format: 'date'),
                    new OA\Property(property: 'waktu', type: 'string'),
                    new OA\Property(property: 'tempat', type: 'string'),
                    new OA\Property(property: 'status', type: 'string')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Rapat berhasil diperbarui')
        ]
    )]
    public function update(Request $request, $id)
    {
        return parent::update($request, $id);
    }

    #[OA\Delete(
        path: '/rapat/{id}',
        summary: 'Hapus Agenda Rapat',
        tags: ['Rapat & Sidang'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Rapat berhasil dihapus')
        ]
    )]
    public function destroy($id)
    {
        return parent::destroy($id);
    }
}
