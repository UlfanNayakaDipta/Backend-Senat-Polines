<?php

namespace App\Http\Controllers;

use App\Models\Peraturan;

class PeraturanController extends CrudController
{
    protected string $model = Peraturan::class;
    protected array $with = [];

    protected function rules(?int $id = null): array
    {
        return [
            'id_periode_senat' => ['required','exists:periode_senats,id_periode_senat'],
            'nomor_sk' => ['required','string','max:255'],
            'judul' => ['required','string','max:255'],
            'kategori' => ['required','string','max:255'],
            'tahap_pembentukan' => ['required','string','max:255'],
            'file_dokumen' => ['nullable','string','max:255'],
            'tanggal_terbit' => ['required','date'],
        ];
    }
}
