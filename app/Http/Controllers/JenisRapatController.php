<?php

namespace App\Http\Controllers;

use App\Models\JenisRapat;

class JenisRapatController extends CrudController
{
    protected string $model = JenisRapat::class;
    protected array $with = [];

    protected function rules(?int $id = null): array
    {
        return [
            'nama_jenis' => ['required','string','max:255'],
        ];
    }
}
