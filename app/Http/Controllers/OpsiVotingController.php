<?php

namespace App\Http\Controllers;

use App\Models\OpsiVoting;

class OpsiVotingController extends CrudController
{
    protected string $model = OpsiVoting::class;
    protected array $with = [];

    protected function rules(?int $id = null): array
    {
        return [
            'id_voting' => ['required','exists:votings,id_voting'],
            'nama_opsi' => ['required','string','max:255'],
        ];
    }
}
