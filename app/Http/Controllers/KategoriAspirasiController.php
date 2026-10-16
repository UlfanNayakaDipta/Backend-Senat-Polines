<?php

namespace App\Http\Controllers;

use App\Models\KategoriAspirasi;

class KategoriAspirasiController extends CrudController
{
    protected string $model = KategoriAspirasi::class;
    protected array $with = [];

    protected function rules(?int $id = null): array
    {
        return [
            'nama_kategori' => ['required','string','max:255'],
        ];
    }
}
