<?php

namespace App\Http\Controllers;

use App\Models\Voting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

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

    public function buka($id): JsonResponse
    {
        $voting = Voting::findOrFail($id);
        $voting->update([
            'status' => 'dibuka',
            'waktu_mulai' => now(),
        ]);

        return response()->json($voting->fresh($this->with));
    }

    public function tutup($id): JsonResponse
    {
        $voting = Voting::findOrFail($id);
        $voting->update([
            'status' => 'ditutup',
            'waktu_selesai' => now(),
        ]);

        return response()->json($voting->fresh($this->with));
    }

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
}

