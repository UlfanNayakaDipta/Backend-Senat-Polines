<?php

namespace App\Http\Controllers;

use App\Models\AnggotaRapat;

class AnggotaRapatController extends CrudController
{
    protected string $model = AnggotaRapat::class;
    protected array $with = ['user'];

    protected function rules(?int $id = null): array
    {
        return [
            'id_rapat' => ['required','exists:rapats,id_rapat'],
            'id_user' => ['required','exists:users,id_user'],
            'status_kehadiran' => ['nullable','string','max:255'],
            'waktu_presensi' => ['nullable','date'],
        ];
    }
}
