<?php

namespace App\Http\Controllers;

use App\Models\Komisi;
use App\Models\User;
use App\Models\Enrollment;
use App\Models\PeriodeSenat;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

            $secretary = $komisi->enrollments->first(function ($enrollment) {
                return str_contains(strtolower($enrollment->jabatan_komisi ?? ''), 'sekretaris');
            });

            $code = strtoupper(str_replace('Komisi ', 'KOM-', $komisi->nama_komisi));

            return [
                'id' => $komisi->id_komisi,
                'code' => $code ?: 'KOM',
                'name' => $komisi->nama_komisi,
                'lead' => $ketua?->user?->nama ?? 'Belum ditentukan',
                'secretary' => $secretary?->user?->nama ?? 'Belum ditentukan',
                'focus' => $komisi->deskripsi ?? 'Belum ada deskripsi komisi.',
                'tupoksi' => $komisi->deskripsi ?? 'Belum ada deskripsi tugas pokok dan fungsi.',
                'members_count' => $komisi->enrollments->count(),
                'members' => $komisi->enrollments->map(function ($enrollment) {
                    return [
                        'id_enrollment' => $enrollment->id_enrollment,
                        'id_user' => $enrollment->user?->id_user,
                        'nama' => $enrollment->user?->nama,
                        'nim_nip' => $enrollment->user?->nim_nip,
                        'email' => $enrollment->user?->email,
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

    public function storeMember(Request $request)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'nim_nip' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'id_komisi' => ['required'],
            'jabatan_komisi' => ['required', 'string', 'max:255'],
            'foto_profil' => ['nullable', 'string', 'max:255'],
        ]);

        $komisi = null;
        if (is_numeric($validated['id_komisi'])) {
            $komisi = Komisi::find($validated['id_komisi']);
        }
        if (! $komisi) {
            $codeQuery = str_replace('KOM-', 'Komisi ', (string)$validated['id_komisi']);
            $komisi = Komisi::where('nama_komisi', 'like', "%{$codeQuery}%")
                ->orWhere('id_komisi', $validated['id_komisi'])
                ->first();
        }
        if (! $komisi) {
            $komisi = Komisi::firstOrCreate(
                ['nama_komisi' => is_numeric($validated['id_komisi']) ? "Komisi {$validated['id_komisi']}" : (string)$validated['id_komisi']],
                ['deskripsi' => 'Komisi Senat Akademik Polines']
            );
        }

        $periode = PeriodeSenat::latest('id_periode_senat')->first();
        if (! $periode) {
            $periode = PeriodeSenat::create([
                'nama_periode' => '2026–2029',
                'tanggal_mulai' => now()->toDateString(),
                'tanggal_selesai' => now()->addYears(3)->toDateString(),
            ]);
        }

        $user = null;
        if (!empty($validated['nim_nip'])) {
            $user = User::where('nim_nip', $validated['nim_nip'])->first();
        }
        if (! $user) {
            $user = User::where('nama', $validated['nama'])->first();
        }

        $nip = !empty($validated['nim_nip']) ? $validated['nim_nip'] : 'NIP-' . time() . '-' . rand(10, 99);
        $email = !empty($validated['email']) ? $validated['email'] : 'anggota.' . time() . rand(10, 99) . '@polines.ac.id';

        if (! $user) {
            $user = User::create([
                'nim_nip' => $nip,
                'nama' => $validated['nama'],
                'email' => $email,
                'password' => bcrypt('password123'),
                'role' => 'anggota',
                'foto_profil' => $validated['foto_profil'] ?? null,
            ]);
        } else {
            $user->update([
                'nama' => $validated['nama'],
                'foto_profil' => $validated['foto_profil'] ?? $user->foto_profil,
            ]);
        }

        $enrollment = Enrollment::updateOrCreate(
            [
                'id_user' => $user->id_user,
                'id_komisi' => $komisi->id_komisi,
                'id_periode_senat' => $periode->id_periode_senat,
            ],
            [
                'jabatan_komisi' => $validated['jabatan_komisi'],
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Anggota berhasil ditambahkan ke komisi',
            'data' => [
                'id_enrollment' => $enrollment->id_enrollment,
                'id_user' => $user->id_user,
                'id_komisi' => $komisi->id_komisi,
                'nama' => $user->nama,
                'nim_nip' => $user->nim_nip,
                'jabatan_komisi' => $enrollment->jabatan_komisi,
                'komisi_name' => $komisi->nama_komisi,
            ]
        ], 201);
    }

    public function updateMember(Request $request, $id)
    {
        $validated = $request->validate([
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'nama_lama' => ['nullable', 'string', 'max:255'],
            'nim_nip' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'id_komisi' => ['nullable'],
            'jabatan_komisi' => ['sometimes', 'required', 'string', 'max:255'],
            'foto_profil' => ['nullable', 'string', 'max:255'],
        ]);

        $enrollment = null;
        if (is_numeric($id)) {
            $enrollment = Enrollment::with('user')->find($id);
            if (! $enrollment) {
                $enrollment = Enrollment::with('user')->where('id_user', $id)->first();
            }
        }

        if (! $enrollment) {
            $targetName = $request->input('nama_lama') ?: $request->input('nama') ?: urldecode((string)$id);
            $user = User::where('nama', $targetName)->first();
            if (! $user && !empty($validated['nim_nip'])) {
                $user = User::where('nim_nip', $validated['nim_nip'])->first();
            }
            if (! $user) {
                $user = User::all()->first(fn($u) => Str::slug($u->nama) === (string)$id);
            }
            if ($user) {
                $query = Enrollment::with('user')->where('id_user', $user->id_user);
                if (!empty($validated['id_komisi'])) {
                    $codeQuery = str_replace('KOM-', 'Komisi ', (string)$validated['id_komisi']);
                    $komisi = Komisi::where('nama_komisi', 'like', "%{$codeQuery}%")->orWhere('id_komisi', $validated['id_komisi'])->first();
                    if ($komisi) {
                        $query->where('id_komisi', $komisi->id_komisi);
                    }
                }
                $enrollment = $query->first();

                // Jika belum punya enrollment di komisi ini, buat enrollment baru
                if (! $enrollment) {
                    $periode = PeriodeSenat::latest('id_periode_senat')->first();
                    $codeQuery = str_replace('KOM-', 'Komisi ', (string)($validated['id_komisi'] ?? '1'));
                    $komisi = Komisi::where('nama_komisi', 'like', "%{$codeQuery}%")->orWhere('id_komisi', $validated['id_komisi'] ?? 1)->first();
                    if ($komisi && $periode) {
                        $enrollment = Enrollment::create([
                            'id_user' => $user->id_user,
                            'id_komisi' => $komisi->id_komisi,
                            'id_periode_senat' => $periode->id_periode_senat,
                            'jabatan_komisi' => $validated['jabatan_komisi'] ?? 'Anggota',
                        ]);
                        $enrollment->load('user');
                    }
                }
            }
        }

        if ($enrollment) {
            if ($request->has('jabatan_komisi')) {
                $enrollment->jabatan_komisi = $validated['jabatan_komisi'];
            }
            if (!empty($validated['id_komisi'])) {
                $komisi = is_numeric($validated['id_komisi'])
                    ? Komisi::find($validated['id_komisi'])
                    : Komisi::where('nama_komisi', 'like', "%{$validated['id_komisi']}%")->first();
                if ($komisi) {
                    $enrollment->id_komisi = $komisi->id_komisi;
                }
            }
            $enrollment->save();

            if ($enrollment->user) {
                if ($request->has('nama')) {
                    $enrollment->user->nama = $validated['nama'];
                }
                if ($request->has('nim_nip') && !empty($validated['nim_nip'])) {
                    $enrollment->user->nim_nip = $validated['nim_nip'];
                }
                if ($request->has('email') && !empty($validated['email'])) {
                    $enrollment->user->email = $validated['email'];
                }
                $enrollment->user->save();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Data anggota berhasil diperbarui',
                'data' => [
                    'id_enrollment' => $enrollment->id_enrollment,
                    'nama' => $enrollment->user?->nama,
                    'nim_nip' => $enrollment->user?->nim_nip,
                    'jabatan_komisi' => $enrollment->jabatan_komisi,
                ]
            ]);
        }

        $user = is_numeric($id) ? User::find($id) : null;
        if (! $user) {
            $user = User::where('nama', $request->input('nama'))->first();
        }
        if ($user) {
            if ($request->has('nama')) $user->nama = $validated['nama'];
            if ($request->has('nim_nip') && !empty($validated['nim_nip'])) $user->nim_nip = $validated['nim_nip'];
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Data anggota berhasil diperbarui',
                'data' => $user
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Data anggota tidak ditemukan'
        ], 404);
    }

    public function destroyMember(Request $request, $id)
    {
        $enrollment = null;
        if (is_numeric($id)) {
            $enrollment = Enrollment::find($id);
            if (! $enrollment) {
                $enrollment = Enrollment::where('id_user', $id)->first();
            }
        }

        if (! $enrollment) {
            $name = $request->input('nama') ?: urldecode((string)$id);
            $user = User::where('nama', $name)->first();
            if (! $user) {
                $user = User::all()->first(fn($u) => Str::slug($u->nama) === (string)$id);
            }
            if ($user) {
                $query = Enrollment::where('id_user', $user->id_user);
                $commCode = $request->input('committee_code');
                if ($commCode) {
                    $codeQuery = str_replace('KOM-', 'Komisi ', (string)$commCode);
                    $komisi = Komisi::where('nama_komisi', 'like', "%{$codeQuery}%")->orWhere('id_komisi', $commCode)->first();
                    if ($komisi) {
                        $query->where('id_komisi', $komisi->id_komisi);
                    }
                }
                $enrollment = $query->first();
            }
        }

        if ($enrollment) {
            $enrollment->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Anggota berhasil dihapus dari komisi'
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Data anggota tidak ditemukan'
        ], 404);
    }
}
