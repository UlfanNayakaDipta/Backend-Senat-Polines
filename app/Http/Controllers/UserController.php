<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Validation\Rule;

class UserController extends CrudController
{
    protected string $model = User::class;
    protected array $with = [];

    protected function rules(?int $id = null): array
    {
        return [
            'nim_nip' => ['required','string','max:50',Rule::unique('users','nim_nip')->ignore($id, 'id_user')],
            'nama' => ['required','string','max:255'],
            'email' => ['required','email',Rule::unique('users','email')->ignore($id, 'id_user')],
            'password' => ['required','string','min:8'],
            'role' => ['required','string','max:50'],
            'foto_profil' => ['nullable','string','max:255'],
        ];
    }
}
