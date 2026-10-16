<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;

class EnrollmentController extends CrudController
{
    protected string $model = Enrollment::class;
    protected array $with = ['user', 'komisi', 'periodeSenat'];

    protected function rules(?int $id = null): array
    {
        return [
            'id_user' => ['required','exists:users,id_user'],
            'id_komisi' => ['required','exists:komisis,id_komisi'],
            'id_periode_senat' => ['required','exists:periode_senats,id_periode_senat'],
            'jabatan_komisi' => ['required','string','max:255'],
        ];
    }
}
