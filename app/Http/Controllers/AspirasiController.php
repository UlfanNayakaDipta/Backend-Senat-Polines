<?php

namespace App\Http\Controllers;

use App\Models\Aspirasi;

class AspirasiController extends CrudController
{
    protected string $model = Aspirasi::class;
    protected array $with = ['user', 'kategori', 'tanggapans.user'];

    protected function rules(?int $id = null): array
    {
        return [
            'id_user' => ['required','exists:users,id_user'],
            'id_kategori' => ['required','exists:kategori_aspirasis,id_kategori'],
            'isi_aspirasi' => ['required','string'],
            'lampiran' => ['nullable','string','max:255'],
            'status' => ['nullable','string','max:255'],
            'anonim' => ['boolean'],
        ];
    }
}
