<?php

namespace App\Http\Controllers;

use App\Models\MateriRapat;

class MateriRapatController extends CrudController
{
    protected string $model = MateriRapat::class;
    protected array $with = [];

    protected function rules(?int $id = null): array
    {
        return [
            'id_rapat' => ['required','exists:rapats,id_rapat'],
            'judul_materi' => ['required','string','max:255'],
            'file_materi' => ['required','string','max:255'],
        ];
    }
}
