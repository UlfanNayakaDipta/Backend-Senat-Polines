<?php

namespace App\Http\Controllers;

use App\Models\Rapat;

class RapatController extends CrudController
{
    protected string $model = Rapat::class;
    protected array $with = ['jenisRapat', 'kegiatan', 'notulensi'];

    protected function rules(?int $id = null): array
    {
        return [
            'id_kegiatan' => ['nullable','exists:kegiatans,id_kegiatan'],
            'id_user' => ['required','exists:users,id_user'],
            'id_jenis_rapat' => ['required','exists:jenis_rapats,id_jenis_rapat'],
            'nama_agenda' => ['required','string','max:255'],
            'tanggal' => ['required','date'],
            'waktu' => ['required','date_format:H:i'],
            'tempat' => ['required','string','max:255'],
            'status' => ['nullable','string','max:255'],
        ];
    }
}
