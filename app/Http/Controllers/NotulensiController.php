<?php

namespace App\Http\Controllers;

use App\Models\Notulensi;

class NotulensiController extends CrudController
{
    protected string $model = Notulensi::class;
    protected array $with = ['user'];

    protected function rules(?int $id = null): array
    {
        return [
            'id_rapat' => ['required','exists:rapats,id_rapat'],
            'id_user' => ['required','exists:users,id_user'],
            'isi_notulensi' => ['required','string'],
            'rekomendasi_keputusan' => ['nullable','string','in:Pertimbangan,Penetapan,Keputusan'],
            'file_risalah' => ['nullable','string','max:255'],
        ];
    }
}
