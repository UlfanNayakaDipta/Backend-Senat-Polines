<?php

namespace App\Http\Controllers;

use App\Models\StrukturOrganisasi;

class StrukturOrganisasiController extends CrudController
{
    protected string $model = StrukturOrganisasi::class;
    protected array $with = ['enrollment.user', 'enrollment.komisi'];

    protected function rules(?int $id = null): array
    {
        return [
            'id_enrollment' => ['required','exists:enrollments,id_enrollment'],
            'jabatan' => ['required','string','max:255'],
            'periode' => ['required','string','max:255'],
            'urutan_bagan' => ['integer','min:0'],
        ];
    }
}
