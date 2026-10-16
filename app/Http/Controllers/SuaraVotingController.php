<?php

namespace App\Http\Controllers;

use App\Models\SuaraVoting;
use Illuminate\Validation\Rule;

class SuaraVotingController extends CrudController
{
    protected string $model = SuaraVoting::class;
    protected array $with = [];

    protected function rules(?int $id = null): array
    {
        return [
            'id_voting' => ['required','exists:votings,id_voting'],
            'id_opsi' => ['required','exists:opsi_votings,id_opsi'],
            'id_user' => ['required','exists:users,id_user',Rule::unique('suara_votings','id_user')->where('id_voting', request('id_voting'))->ignore($id, 'id_suara')],
        ];
    }
}
