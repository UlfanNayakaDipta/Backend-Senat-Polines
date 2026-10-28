<?php

namespace App\Http\Controllers;

use App\Models\Voting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use OpenApi\Attributes as OA;

class VotingController extends CrudController
{
    protected string $model = Voting::class;

    public function __construct()
    {
        $this->with = [
            'rapat',
            'opsiVotings' => fn ($q) => $q->withCount('suaraVotings'),
        ];
    }

    protected function rules(?int $id = null): array
    {
        return [
            'id_rapat' => ['required', 'exists:rapats,id_rapat'],
            'judul_voting' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:255'],
            'waktu_mulai' => ['nullable', 'date'],
            'waktu_selesai' => ['nullable', 'date', 'after:waktu_mulai'],
            'opsi' => ['nullable', 'array'],
            'opsi.*' => ['required', 'string', 'max:255'],
        ];
    }

    #[OA\Get(
        path: '/voting',
        summary: 'Daftar Sesi E-Voting Sidang Senat',
        description: 'Mengambil daftar sesi pemungutan suara (e-voting) rapat senat beserta jumlah suara.',
        tags: ['Voting'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['draft', 'dibuka', 'ditutup'])),
            new OA\Parameter(name: 'active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar voting terpaginasi')
        ]
    )]
    public function index(?Request $request = null)
    {
        $request = $request ?? request();
        $query = Voting::with($this->with)->withCount('suaraVotings');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        } elseif ($request->boolean('active') || $request->query('active') === '1') {
            $query->where('status', 'dibuka');
        }

        return $query->latest()->paginate(15);
    }

    #[OA\Post(
        path: '/voting',
        summary: 'Buat Sesi E-Voting Baru',
        description: 'Membuat agenda pemungutan suara sidang senat beserta opsi pilihan (default: Setuju, Tidak Setuju, Abstain).',
        tags: ['Voting'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_rapat', 'judul_voting'],
                properties: [
                    new OA\Property(property: 'id_rapat', type: 'integer', example: 1),
                    new OA\Property(property: 'judul_voting', type: 'string', example: 'Persetujuan Pembukaan Program Studi Magister Terapan Rekayasa Komputer'),
                    new OA\Property(property: 'deskripsi', type: 'string', example: 'Pemungutan suara tertutup oleh anggota senat akademik.'),
                    new OA\Property(property: 'status', type: 'string', enum: ['draft', 'dibuka', 'ditutup'], example: 'draft'),
                    new OA\Property(
                        property: 'opsi',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ['Setuju', 'Tidak Setuju', 'Abstain']
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Sesi voting berhasil dibuat'),
            new OA\Response(response: 422, description: 'Validasi gagal')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $votingData = Arr::except($data, ['opsi']);
        $votingData['status'] = $votingData['status'] ?? 'draft';

        $voting = Voting::create($votingData);

        $opsi = $request->input('opsi');
        if (! is_array($opsi) || empty($opsi)) {
            $opsi = ['Setuju', 'Tidak Setuju', 'Abstain'];
        }

        foreach ($opsi as $namaOpsi) {
            $voting->opsiVotings()->create(['nama_opsi' => $namaOpsi]);
        }

        return response()->json($voting->fresh($this->with), 201);
    }

    #[OA\Post(
        path: '/voting/{id}/buka',
        summary: 'Buka Sesi Pemungutan Suara',
        description: 'Mengubah status voting menjadi dibuka sehingga anggota senat dapat memberikan suara.',
        tags: ['Voting'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Sesi voting berhasil dibuka')
        ]
    )]
    public function buka($id): JsonResponse
    {
        $voting = Voting::findOrFail($id);
        $voting->update([
            'status' => 'dibuka',
            'waktu_mulai' => now(),
        ]);

        return response()->json($voting->fresh($this->with));
    }

    #[OA\Post(
        path: '/voting/{id}/tutup',
        summary: 'Tutup Sesi Pemungutan Suara',
        description: 'Menutup sesi voting sidang senat sehingga tidak menerima suara baru.',
        tags: ['Voting'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Sesi voting berhasil ditutup')
        ]
    )]
    public function tutup($id): JsonResponse
    {
        $voting = Voting::findOrFail($id);
        $voting->update([
            'status' => 'ditutup',
            'waktu_selesai' => now(),
        ]);

        return response()->json($voting->fresh($this->with));
    }

    #[OA\Get(
        path: '/voting/{id}/rekap',
        summary: 'Rekapitulasi Hasil E-Voting & Status Kuorum Sidang',
        description: 'Menampilkan ringkasan perolehan suara per opsi, persentase kehadiran kuorum, dan hasil keputusan sidang.',
        tags: ['Voting'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Hasil rekapitulasi voting dan keputusan sidang')
        ]
    )]
    public function rekap($id): JsonResponse
    {
        $voting = Voting::with([
            'rapat.anggotaRapats',
            'rapat.jenisRapat',
            'opsiVotings' => fn ($q) => $q->withCount('suaraVotings'),
        ])->withCount('suaraVotings')->findOrFail($id);

        $rapat = $voting->rapat;
        $totalUndangan = $rapat ? $rapat->anggotaRapats->count() : 0;
        $totalHadir = $rapat ? $rapat->anggotaRapats->filter(function ($a) {
            return strtolower((string) $a->status_kehadiran) === 'hadir' || ! is_null($a->waktu_presensi);
        })->count() : 0;

        $totalSuara = (int) $voting->total_suara;
        $persentaseKehadiran = $totalUndangan > 0 ? round(($totalHadir / $totalUndangan) * 100, 2) : 0;
        $kuorumTerpenuhi = $totalUndangan > 0 ? ($totalHadir > ($totalUndangan / 2)) : ($totalSuara > 0);

        $rekapitulasi = $voting->hasil_rekap;
        $opsiTerbanyak = collect($rekapitulasi)->sortByDesc('jumlah_suara')->first();
        $keputusanSidang = ($totalSuara > 0 && $opsiTerbanyak) ? $opsiTerbanyak['nama_opsi'] : null;

        return response()->json([
            'voting' => $voting,
            'rapat' => $rapat,
            'kuorum' => [
                'total_undangan' => $totalUndangan,
                'total_hadir' => $totalHadir,
                'total_suara' => $totalSuara,
                'persentase_kehadiran' => $persentaseKehadiran,
                'kuorum_terpenuhi' => $kuorumTerpenuhi,
            ],
            'rekapitulasi' => $rekapitulasi,
            'total_suara' => $totalSuara,
            'opsi_terbanyak' => $opsiTerbanyak,
            'keputusan_sidang' => $keputusanSidang,
        ]);
    }

    #[OA\Get(
        path: '/voting/{id}',
        summary: 'Detail Sesi Voting',
        tags: ['Voting'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Detail data sesi voting'),
            new OA\Response(response: 404, description: 'Data tidak ditemukan')
        ]
    )]
    public function show($id)
    {
        return parent::show($id);
    }

    #[OA\Put(
        path: '/voting/{id}',
        summary: 'Perbarui Sesi Voting',
        tags: ['Voting'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'judul_voting', type: 'string'),
                    new OA\Property(property: 'deskripsi', type: 'string'),
                    new OA\Property(property: 'status', type: 'string', enum: ['draft', 'dibuka', 'ditutup'])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Data voting berhasil diperbarui')
        ]
    )]
    public function update(Request $request, $id)
    {
        return parent::update($request, $id);
    }

    #[OA\Delete(
        path: '/voting/{id}',
        summary: 'Hapus Sesi Voting',
        tags: ['Voting'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Voting berhasil dihapus')
        ]
    )]
    public function destroy($id)
    {
        return parent::destroy($id);
    }
}
