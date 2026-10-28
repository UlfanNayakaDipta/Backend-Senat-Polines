<?php

namespace App\Http\Controllers;

use App\Models\Peraturan;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PeraturanController extends CrudController
{
    protected string $model = Peraturan::class;
    protected array $with = [];

    protected function rules(?int $id = null): array
    {
        return [
            'id_periode_senat' => ['required','exists:periode_senats,id_periode_senat'],
            'nomor_sk' => ['required','string','max:255'],
            'judul' => ['required','string','max:255'],
            'kategori' => ['required','string','max:255'],
            'tahap_pembentukan' => ['required','string','max:255'],
            'file_dokumen' => ['nullable','string','max:255'],
            'tanggal_terbit' => ['required','date'],
        ];
    }

    #[OA\Get(
        path: '/peraturan',
        summary: 'Daftar Peraturan & Dokumen Senat',
        tags: ['Peraturan & Dokumen'],
        responses: [
            new OA\Response(response: 200, description: 'Daftar peraturan terpaginasi')
        ]
    )]
    public function index()
    {
        return parent::index();
    }

    #[OA\Post(
        path: '/peraturan',
        summary: 'Tambah Dokumen Peraturan Baru',
        tags: ['Peraturan & Dokumen'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_periode_senat', 'nomor_sk', 'judul', 'kategori', 'tahap_pembentukan', 'tanggal_terbit'],
                properties: [
                    new OA\Property(property: 'id_periode_senat', type: 'integer', example: 1),
                    new OA\Property(property: 'nomor_sk', type: 'string', example: 'SK/SENAT-POLINES/2026/012'),
                    new OA\Property(property: 'judul', type: 'string', example: 'Peraturan Tata Tertib Sidang dan Pengambilan Keputusan Senat'),
                    new OA\Property(property: 'kategori', type: 'string', example: 'Tata Tertib'),
                    new OA\Property(property: 'tahap_pembentukan', type: 'string', example: 'Disahkan'),
                    new OA\Property(property: 'file_dokumen', type: 'string', nullable: true),
                    new OA\Property(property: 'tanggal_terbit', type: 'string', format: 'date', example: '2026-05-10')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Peraturan berhasil disimpan'),
            new OA\Response(response: 422, description: 'Validasi gagal')
        ]
    )]
    public function store(Request $request)
    {
        return parent::store($request);
    }

    #[OA\Get(
        path: '/peraturan/{id}',
        summary: 'Detail Dokumen Peraturan',
        tags: ['Peraturan & Dokumen'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Detail peraturan'),
            new OA\Response(response: 404, description: 'Peraturan tidak ditemukan')
        ]
    )]
    public function show($id)
    {
        return parent::show($id);
    }

    #[OA\Put(
        path: '/peraturan/{id}',
        summary: 'Perbarui Dokumen Peraturan',
        tags: ['Peraturan & Dokumen'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'judul', type: 'string'),
                    new OA\Property(property: 'nomor_sk', type: 'string'),
                    new OA\Property(property: 'tahap_pembentukan', type: 'string')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Peraturan berhasil diperbarui')
        ]
    )]
    public function update(Request $request, $id)
    {
        return parent::update($request, $id);
    }

    #[OA\Delete(
        path: '/peraturan/{id}',
        summary: 'Hapus Peraturan',
        tags: ['Peraturan & Dokumen'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Peraturan berhasil dihapus')
        ]
    )]
    public function destroy($id)
    {
        return parent::destroy($id);
    }
}
