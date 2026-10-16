<?php

namespace App\Http\Controllers;

use App\Models\Komisi;

class KomisiController extends CrudController
{
    protected string $model = Komisi::class;
    protected array $with = [];

    protected function rules(?int $id = null): array
    {
        return [
            'nama_komisi' => ['required','string','max:255'],
            'deskripsi' => ['nullable','string'],
        ];
    }
}
