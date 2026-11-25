<?php

namespace App\Http\Controllers;

use App\Models\Komisi;
use App\Models\User;
use App\Models\Enrollment;
use App\Models\PeriodeSenat;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class KomisiController extends CrudController
{
    protected string $model = Komisi::class;
    protected array $with = ['tugas'];

    protected function rules(?int $id = null): array
    {
        return [
            'kode' => ['nullable', 'string', 'max:20', 'unique:komisis,kode,' . ($id ?? 'NULL') . ',id_komisi'],
            'nama_komisi' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
        ];
    }

    #[OA\Get(
        path: '/komisi',
        summary: 'Daftar Master Data Komisi Senat',
        description: 'Mengambil daftar seluruh komisi Senat Akademik Politeknik Negeri Semarang.',
        tags: ['Komisi & Anggota Senat'],
        responses: [
            new OA\Response(response: 200, description: 'Daftar komisi berhasil dimuat')
        ]
    )]
    public function index()
    {
        return parent::index();
    }

    #[OA\Post(
        path: '/komisi',
        summary: 'Tambah Master Data Komisi Baru',
        description: 'Membuat komisi atau organ kerja senat baru.',
        tags: ['Komisi & Anggota Senat'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nama_komisi'],
                properties: [
                    new OA\Property(property: 'nama_komisi', type: 'string', example: 'Badan Pekerja (BP)'),
                    new OA\Property(property: 'deskripsi', type: 'string', example: 'Koordinasi kerja antar-komisi dan penyiapan berkas sidang')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Komisi berhasil dibuat'),
            new OA\Response(response: 422, description: 'Validasi data komisi gagal')
        ]
    )]
    public function store(Request $request)
    {
        return parent::store($request);
    }

    #[OA\Get(
        path: '/komisi/{id}',
        summary: 'Detail Data Komisi Senat',
        tags: ['Komisi & Anggota Senat'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Detail data komisi'),
            new OA\Response(response: 404, description: 'Komisi tidak ditemukan')
        ]
    )]
    public function show($id)
    {
        return parent::show($id);
    }

    #[OA\Put(
        path: '/komisi/{id}',
        summary: 'Perbarui Data Komisi Senat',
        tags: ['Komisi & Anggota Senat'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nama_komisi', type: 'string', example: 'Komisi I (Akademik)'),
                    new OA\Property(property: 'deskripsi', type: 'string', example: 'Fokus kurikulum dan pembelajaran')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Komisi berhasil diperbarui')
        ]
    )]
    public function update(Request $request, $id)
    {
        return parent::update($request, $id);
    }

    #[OA\Delete(
        path: '/komisi/{id}',
        summary: 'Hapus Data Komisi Senat',
        tags: ['Komisi & Anggota Senat'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Komisi berhasil dihapus')
        ]
    )]
    public function destroy($id)
    {
        return parent::destroy($id);
    }

    #[OA\Get(
        path: '/committees',
        summary: 'Direktori Publik Komisi Senat & Anggota',
        description: 'Endpoint publik untuk halaman direktori komisi frontend. Mengembalikan seluruh komisi fungsional dengan susunan Ketua, Sekretaris, daftar Anggota lengkap, NIP, email, jabatan, serta fokus mandat.',
        tags: ['Komisi & Anggota Senat'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Data direktori komisi dan anggota berhasil dimuat',
                content: new OA\JsonContent(
                    example: [
                        'status' => 'success',
                        'data' => [
                            [
                                'id' => 1,
                                'code' => 'KOM-I',
                                'name' => 'Komisi Pendidikan dan Pengajaran',
                                'lead' => 'Tri Raharjo Yudantoro, S.Kom., M.Kom.',
                                'secretary' => 'Iwan Budiyono, S.E., M.Si., Ak.',
                                'focus' => 'Kebijakan & norma akademik, penjaminan mutu pembelajaran, dan kebebasan akademik.',
                                'tupoksi' => 'Merumuskan dan memberi pertimbangan kebijakan serta norma akademik.',
                                'tupoksi_items' => ['Merumuskan dan memberi pertimbangan kebijakan serta norma akademik.'],
                                'members_count' => 7,
                                'members' => [
                                    [
                                        'id_enrollment' => 9,
                                        'id_user' => 43,
                                        'nama' => 'Tri Raharjo Yudantoro, S.Kom., M.Kom.',
                                        'nim_nip' => '196810252000121001',
                                        'email' => 'tri.yudantoro@senat.test',
                                        'jabatan' => 'Ketua',
                                        'foto_profil' => 'images/members/196810252000121001.jpg',
                                    ],
                                ],
                            ],
                        ],
                    ]
                )
            )
        ]
    )]
    public function publicCommittees(Request $request)
    {
        $komisis = Komisi::with(['enrollments.user', 'tugas'])->orderBy('id_komisi')->get();

        // Gunakan periode dari query ?id_periode_senat=... jika ada, atau periode aktif saat ini.
        $requestedPeriodeId = $request->integer('id_periode_senat');
        $selectedPeriode = $requestedPeriodeId
            ? PeriodeSenat::find($requestedPeriodeId)
            : PeriodeSenat::active();
        $activePeriodeId = $selectedPeriode?->id_periode_senat;

        $data = $komisis->map(function ($komisi) use ($activePeriodeId) {
            $enrollments = $activePeriodeId
                ? $komisi->enrollments->where('id_periode_senat', $activePeriodeId)
                : $komisi->enrollments;

            // Satu orang dapat memegang lebih dari satu jabatan (mis. Ketua Senat + Anggota komisi).
            // Tampilkan sekali saja dengan prioritas jabatan komisi: Ketua > Sekretaris > Anggota.
            $memberRank = function ($enrollment): int {
                $jab = strtolower(trim($enrollment->jabatan_komisi ?? ''));
                return match (true) {
                    $jab === 'ketua' => 1,
                    $jab === 'sekretaris' => 2,
                    str_starts_with($jab, 'anggota') => 3,
                    default => 4,
                };
            };

            $uniqueMembers = $enrollments
                ->sortBy(fn ($e) => $memberRank($e))
                ->unique(fn ($e) => $e->id_user)
                ->values();

            $ketua = $enrollments->first(function ($enrollment) {
                $jab = strtolower(trim($enrollment->jabatan_komisi ?? ''));
                return $jab === 'ketua'
                    || str_starts_with($jab, 'ketua komisi')
                    || str_starts_with($jab, 'ketua bp');
            });

            $secretary = $enrollments->first(function ($enrollment) {
                $jab = strtolower(trim($enrollment->jabatan_komisi ?? ''));
                return $jab === 'sekretaris'
                    || str_starts_with($jab, 'sekretaris komisi')
                    || str_starts_with($jab, 'sekretaris bp');
            });

            $code = $komisi->kode;
            if (! $code) {
                $namaLower = strtolower(trim($komisi->nama_komisi));
                if ($namaLower === 'bp' || str_contains($namaLower, 'badan pekerja')) {
                    $code = 'BP';
                } else {
                    $code = strtoupper(str_replace('Komisi ', 'KOM-', $komisi->nama_komisi));
                }
            }

            $tupoksiItems = $komisi->tugas->pluck('uraian_tugas')->values()->all();
            $tupoksiText = ! empty($tupoksiItems)
                ? implode('; ', $tupoksiItems)
                : ($komisi->deskripsi ?? 'Belum ada deskripsi tugas pokok dan fungsi.');

            return [
                'id' => $komisi->id_komisi,
                'code' => $code ?: 'KOM',
                'name' => $komisi->nama_komisi,
                'lead' => $ketua?->user?->nama ?? 'Belum ditentukan',
                'secretary' => $secretary?->user?->nama ?? 'Belum ditentukan',
                'focus' => $komisi->deskripsi ?? 'Belum ada deskripsi komisi.',
                'tupoksi' => $tupoksiText,
                'tupoksi_items' => $tupoksiItems,
                'members_count' => $uniqueMembers->count(),
                'members' => $uniqueMembers->map(function ($enrollment) {
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
            'periode' => $selectedPeriode,
            'data' => $data,
        ]);
    }

    /** Resolusi entitas Komisi berdasarkan ID numerik, kode KOM-*, BP, atau nama komisi. */
    private function resolveKomisi(string|int $identifier): ?Komisi
    {
        if (is_numeric($identifier)) {
            $komisi = Komisi::find($identifier);
            if ($komisi) {
                return $komisi;
            }
        }

        $raw = trim((string) $identifier);

        // Cocokkan langsung dengan kolom kode komisi resmi (KOM-I s.d. KOM-V, BP).
        $komisi = Komisi::where('kode', $raw)->first();
        if ($komisi) {
            return $komisi;
        }

        $lower = strtolower($raw);
        if ($lower === 'bp' || str_contains($lower, 'badan pekerja')) {
            $komisi = Komisi::where('nama_komisi', 'like', '%Badan Pekerja%')
                ->orWhere('nama_komisi', 'BP')
                ->first();
            if ($komisi) {
                return $komisi;
            }
        }

        $codeQuery = str_replace('KOM-', 'Komisi ', $raw);
        return Komisi::where('nama_komisi', 'like', "%{$codeQuery}%")
            ->orWhere('nama_komisi', 'like', "%{$raw}%")
            ->orWhere('id_komisi', $raw)
            ->first();
    }

    #[OA\Post(
        path: '/committees/members',
        summary: 'Tambah Anggota ke Komisi Senat',
        description: 'Mendaftarkan anggota/dosen ke dalam komisi tertentu beserta jabatannya.',
        tags: ['Komisi & Anggota Senat'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nama', 'id_komisi', 'jabatan_komisi'],
                properties: [
                    new OA\Property(property: 'nama', type: 'string', example: 'Dr. Sukamto, S.Kom., M.T.'),
                    new OA\Property(property: 'nim_nip', type: 'string', example: '197101172003121001'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'senat1@senat.polines.ac.id'),
                    new OA\Property(property: 'id_komisi', type: 'string', example: 'KOM-I', description: 'ID numerik atau kode unit kerja (KOM-I s.d. KOM-V, atau BP)'),
                    new OA\Property(property: 'jabatan_komisi', type: 'string', example: 'Ketua', description: 'Jabatan dalam komisi / BP (Ketua, Sekretaris, Anggota)'),
                    new OA\Property(property: 'foto_profil', type: 'string', nullable: true, example: 'images/members/197101172003121001.jpg', description: 'Path foto profil (opsional)')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Anggota berhasil ditambahkan ke komisi',
                content: new OA\JsonContent(
                    example: [
                        'status' => 'success',
                        'message' => 'Anggota berhasil ditambahkan ke komisi',
                        'data' => [
                            'id_enrollment' => 61,
                            'id_user' => 70,
                            'id_komisi' => 1,
                            'nama' => 'Dr. Sukamto, S.Kom., M.T.',
                            'nim_nip' => '197101172003121001',
                            'jabatan_komisi' => 'Anggota',
                            'komisi_name' => 'Komisi Pendidikan dan Pengajaran',
                            'foto_profil' => 'images/members/197101172003121001.jpg',
                        ],
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Belum login / token akses tidak valid'),
            new OA\Response(response: 403, description: 'Token valid tetapi role tidak berhak (khusus admin/ketua/sekretaris)'),
            new OA\Response(response: 422, description: 'Validasi data gagal atau kode komisi tidak dikenal')
        ]
    )]
    public function storeMember(Request $request)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'nim_nip' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'id_komisi' => ['required'],
            'jabatan_komisi' => ['required', 'string', 'max:255'],
            'foto_profil' => ['nullable', 'string', 'max:255'],
            'id_periode_senat' => ['nullable', 'integer', 'exists:periode_senats,id_periode_senat'],
        ]);

        $komisi = $this->resolveKomisi($validated['id_komisi']);
        if (! $komisi) {
            return response()->json([
                'status' => 'error',
                'message' => 'Komisi tidak ditemukan. Gunakan kode komisi yang valid (contoh: KOM-I s.d. KOM-V, atau BP).',
            ], 422);
        }

        $periode = !empty($validated['id_periode_senat'])
            ? PeriodeSenat::find($validated['id_periode_senat'])
            : PeriodeSenat::active();
        if (! $periode) {
            $periode = PeriodeSenat::create([
                'nama_periode' => '2026–2029',
                'tanggal_mulai' => now()->toDateString(),
                'tanggal_selesai' => now()->addYears(3)->toDateString(),
                'is_active' => true,
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
                'foto_profil' => $user->foto_profil,
            ]
        ], 201);
    }

    #[OA\Put(
        path: '/committees/members/{id}',
        summary: 'Perbarui Data/Jabatan Anggota Komisi',
        description: 'Memperbarui informasi nama, NIP, email, mutasi komisi, atau perubahan jabatan anggota.',
        tags: ['Komisi & Anggota Senat'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID Enrollment, ID User, atau slug nama anggota', schema: new OA\Schema(type: 'string', example: '1'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nama', type: 'string', example: 'Dr. Sukamto, S.Kom., M.T.'),
                    new OA\Property(property: 'nama_lama', type: 'string', nullable: true),
                    new OA\Property(property: 'nim_nip', type: 'string', example: '197101172003121001'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'senat1@senat.polines.ac.id'),
                    new OA\Property(property: 'id_komisi', type: 'string', example: 'KOM-I'),
                    new OA\Property(property: 'jabatan_komisi', type: 'string', example: 'Ketua'),
                    new OA\Property(property: 'foto_profil', type: 'string', nullable: true, example: 'images/members/197101172003121001.jpg')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Data anggota berhasil diperbarui',
                content: new OA\JsonContent(
                    example: [
                        'status' => 'success',
                        'message' => 'Data anggota berhasil diperbarui',
                        'data' => [
                            'id_enrollment' => 9,
                            'nama' => 'Tri Raharjo Yudantoro, S.Kom., M.Kom.',
                            'nim_nip' => '196810252000121001',
                            'jabatan_komisi' => 'Ketua',
                            'foto_profil' => 'images/members/196810252000121001.jpg',
                        ],
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Belum login / token akses tidak valid'),
            new OA\Response(response: 403, description: 'Token valid tetapi role tidak berhak (khusus admin/ketua/sekretaris)'),
            new OA\Response(response: 404, description: 'Data anggota tidak ditemukan'),
            new OA\Response(response: 422, description: 'Validasi data gagal atau kode komisi tidak dikenal')
        ]
    )]
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

        // Tolak kode komisi yang tidak dikenal agar tidak membuat data komisi sampah.
        if (! empty($validated['id_komisi']) && ! $this->resolveKomisi($validated['id_komisi'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Komisi tidak ditemukan. Gunakan kode komisi yang valid (contoh: KOM-I s.d. KOM-V, atau BP).',
            ], 422);
        }

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
                    $komisi = $this->resolveKomisi($validated['id_komisi']);
                    if ($komisi) {
                        $query->where('id_komisi', $komisi->id_komisi);
                    }
                }
                $enrollment = $query->first();

                // Jika belum punya enrollment di komisi ini, buat enrollment baru
                if (! $enrollment) {
                    $periode = $request->filled('id_periode_senat')
                        ? PeriodeSenat::find($request->integer('id_periode_senat'))
                        : PeriodeSenat::active();
                    $komisi = $this->resolveKomisi($validated['id_komisi'] ?? 1);
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
                $komisi = $this->resolveKomisi($validated['id_komisi']);
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
                if ($request->has('foto_profil') && !empty($validated['foto_profil'])) {
                    $enrollment->user->foto_profil = $validated['foto_profil'];
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
                    'foto_profil' => $enrollment->user?->foto_profil,
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

    #[OA\Delete(
        path: '/committees/members/{id}',
        summary: 'Hapus Anggota dari Komisi Senat',
        description: 'Menghapus penugasan (enrollment) anggota dari sebuah komisi.',
        tags: ['Komisi & Anggota Senat'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID Enrollment, ID User, atau slug nama anggota', schema: new OA\Schema(type: 'string', example: '1')),
            new OA\Parameter(name: 'nama', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'committee_code', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'KOM-I'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Anggota berhasil dihapus dari komisi',
                content: new OA\JsonContent(
                    example: [
                        'status' => 'success',
                        'message' => 'Anggota berhasil dihapus dari komisi',
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Belum login / token akses tidak valid'),
            new OA\Response(response: 403, description: 'Token valid tetapi role tidak berhak (khusus admin/ketua/sekretaris)'),
            new OA\Response(response: 404, description: 'Data anggota tidak ditemukan')
        ]
    )]
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
                    $komisi = $this->resolveKomisi($commCode);
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
