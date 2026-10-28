<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class UserController extends CrudController
{
    protected string $model = User::class;
    protected array $with = [];

    protected function rules(?int $id = null): array
    {
        return [
            'nim_nip' => ['required','string','max:50',Rule::unique('users','nim_nip')->ignore($id, 'id_user')],
            'nama' => ['required','string','max:255'],
            'email' => ['required','email',Rule::unique('users','email')->ignore($id, 'id_user')],
            'password' => ['required','string','min:8'],
            'role' => ['required','string','max:50'],
            'foto_profil' => ['nullable','string','max:255'],
        ];
    }

    #[OA\Get(
        path: '/user',
        summary: 'Daftar Pengguna / Anggota Senat & Sivitas',
        tags: ['User'],
        responses: [
            new OA\Response(response: 200, description: 'Daftar user terpaginasi')
        ]
    )]
    public function index()
    {
        return parent::index();
    }

    #[OA\Post(
        path: '/user',
        summary: 'Tambah Pengguna Baru',
        tags: ['User'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nim_nip', 'nama', 'email', 'password', 'role'],
                properties: [
                    new OA\Property(property: 'nim_nip', type: 'string', example: '197508121999031002'),
                    new OA\Property(property: 'nama', type: 'string', example: 'Prof. Dr. Ir. Budi Santoso, M.T.'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'budi.santoso@polines.ac.id'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'role', type: 'string', enum: ['admin', 'ketua_senat', 'sekretaris', 'anggota', 'mahasiswa', 'dosen'], example: 'anggota'),
                    new OA\Property(property: 'foto_profil', type: 'string', nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Pengguna berhasil dibuat'),
            new OA\Response(response: 422, description: 'Validasi data pengguna gagal')
        ]
    )]
    public function store(Request $request)
    {
        return parent::store($request);
    }

    #[OA\Get(
        path: '/user/{id}',
        summary: 'Detail Pengguna',
        tags: ['User'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Detail pengguna'),
            new OA\Response(response: 404, description: 'Pengguna tidak ditemukan')
        ]
    )]
    public function show($id)
    {
        return parent::show($id);
    }

    #[OA\Put(
        path: '/user/{id}',
        summary: 'Perbarui Data Pengguna',
        tags: ['User'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nama', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'role', type: 'string'),
                    new OA\Property(property: 'foto_profil', type: 'string')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Pengguna berhasil diperbarui')
        ]
    )]
    public function update(Request $request, $id)
    {
        return parent::update($request, $id);
    }

    #[OA\Delete(
        path: '/user/{id}',
        summary: 'Hapus Pengguna',
        tags: ['User'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Pengguna berhasil dihapus')
        ]
    )]
    public function destroy($id)
    {
        return parent::destroy($id);
    }
}
