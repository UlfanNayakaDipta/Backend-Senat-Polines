<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKegiatan;

class AnggotaKegiatanController extends CrudController
{
    protected string $model = AnggotaKegiatan::class;
    protected array $with = ['user'];

    protected function rules(?int $id = null): array
    {
        return [
            'id_kegiatan' => ['required','exists:kegiatans,id_kegiatan'],
            'id_user' => ['required','exists:users,id_user'],
            'status' => ['nullable','string','max:255'],
            'waktu_hadir' => ['nullable','date'],
            'keterangan_ijin' => ['nullable','string','max:255'],
        ];
    }
}
