<?php

namespace App\Http\Controllers;

use App\Models\Komisi;

class KomisiController extends CrudController
{
    protected string $model = Komisi::class;
    protected array $with = [];

    protected function rules(?int $id = null): array
    {
        return [
            'nama_komisi' => ['required','string','max:255'],
            'deskripsi' => ['nullable','string'],
        ];
    }

    public function publicCommittees()
    {
        $komisis = Komisi::with(['enrollments.user'])->get();

        $data = $komisis->map(function ($komisi) {
            $ketua = $komisi->enrollments->first(function ($enrollment) {
                return str_contains(strtolower($enrollment->jabatan_komisi ?? ''), 'ketua');
            });

            $code = strtoupper(str_replace('Komisi ', 'KOM-', $komisi->nama_komisi));

            return [
                'id' => $komisi->id_komisi,
                'code' => $code ?: 'KOM',
                'name' => $komisi->nama_komisi,
                'lead' => $ketua?->user?->nama ?? 'Belum ditentukan',
                'tupoksi' => $komisi->deskripsi ?? 'Belum ada deskripsi tugas pokok dan fungsi.',
                'members_count' => $komisi->enrollments->count(),
                'members' => $komisi->enrollments->map(function ($enrollment) {
                    return [
                        'id_user' => $enrollment->user?->id_user,
                        'nama' => $enrollment->user?->nama,
                        'nim_nip' => $enrollment->user?->nim_nip,
                        'jabatan' => $enrollment->jabatan_komisi,
                        'foto_profil' => $enrollment->user?->foto_profil,
                    ];
                })->values(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }
}

