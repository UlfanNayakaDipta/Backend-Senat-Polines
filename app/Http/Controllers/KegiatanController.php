<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;

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
}
