<?php

namespace App\Http\Controllers;

use App\Models\Voting;

class VotingController extends CrudController
{
    protected string $model = Voting::class;
    protected array $with = ['opsiVotings'];

    protected function rules(?int $id = null): array
    {
        return [
            'id_rapat' => ['required','exists:rapats,id_rapat'],
            'judul_voting' => ['required','string','max:255'],
            'deskripsi' => ['nullable','string'],
            'status' => ['nullable','string','max:255'],
            'waktu_mulai' => ['nullable','date'],
            'waktu_selesai' => ['nullable','date','after:waktu_mulai'],
        ];
    }
}
