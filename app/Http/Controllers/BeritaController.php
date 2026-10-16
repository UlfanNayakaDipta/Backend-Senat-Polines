<?php

namespace App\Http\Controllers;

use App\Models\Berita;

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
}
