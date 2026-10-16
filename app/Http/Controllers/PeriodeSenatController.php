<?php

namespace App\Http\Controllers;

use App\Models\PeriodeSenat;

class PeriodeSenatController extends CrudController
{
    protected string $model = PeriodeSenat::class;
    protected array $with = [];

    protected function rules(?int $id = null): array
    {
        return [
            'nama_periode' => ['required','string','max:255'],
            'tanggal_mulai' => ['required','date'],
            'tanggal_selesai' => ['required','date','after_or_equal:tanggal_mulai'],
        ];
    }
}
