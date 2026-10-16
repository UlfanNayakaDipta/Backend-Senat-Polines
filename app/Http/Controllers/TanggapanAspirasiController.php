<?php

namespace App\Http\Controllers;

use App\Models\TanggapanAspirasi;

class TanggapanAspirasiController extends CrudController
{
    protected string $model = TanggapanAspirasi::class;
    protected array $with = ['user'];

    protected function rules(?int $id = null): array
    {
        return [
            'id_aspirasi' => ['required','exists:aspirasis,id_aspirasi'],
            'id_user' => ['required','exists:users,id_user'],
            'pesan' => ['required','string'],
        ];
    }
}
