<?php

namespace App\Http\Controllers;

use App\Models\PeriodeSenat;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PeriodeSenatController extends CrudController
{
    protected string $model = PeriodeSenat::class;
    protected array $with = [];

    protected function rules(?int $id = null): array
    {
        return [
            'nama_periode' => ['required', 'string', 'max:255'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    #[OA\Get(
        path: '/periode-senat',
        summary: 'Daftar Periode Masa Bakti Senat',
        tags: ['Master Data'],
        responses: [
            new OA\Response(response: 200, description: 'Daftar periode masa jabatan senat')
        ]
    )]
    public function index()
    {
        $query = PeriodeSenat::withCount('enrollments')
            ->orderByDesc('is_active')
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id_periode_senat');

        if (request()->boolean('all')) {
            return response()->json([
                'status' => 'success',
                'data' => $query->get(),
            ]);
        }

        return $query->paginate(request()->integer('per_page', 50));
    }

    #[OA\Post(
        path: '/periode-senat',
        summary: 'Tambah Periode Masa Bakti Baru',
        tags: ['Master Data'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nama_periode', 'tanggal_mulai', 'tanggal_selesai'],
                properties: [
                    new OA\Property(property: 'nama_periode', type: 'string', example: '2026–2029'),
                    new OA\Property(property: 'tanggal_mulai', type: 'string', format: 'date', example: '2026-01-01'),
                    new OA\Property(property: 'tanggal_selesai', type: 'string', format: 'date', example: '2029-12-31'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: false)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Periode berhasil dibuat'),
            new OA\Response(response: 422, description: 'Validasi gagal')
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $shouldActivate = (bool) ($validated['is_active'] ?? false) || PeriodeSenat::count() === 0;

        if ($shouldActivate) {
            PeriodeSenat::query()->update(['is_active' => false]);
        }

        $periode = PeriodeSenat::create([
            'nama_periode' => $validated['nama_periode'],
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_selesai' => $validated['tanggal_selesai'],
            'is_active' => $shouldActivate,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $periode->loadCount('enrollments'),
        ], 201);
    }

    #[OA\Get(
        path: '/periode-senat/{id}',
        summary: 'Detail Periode Senat',
        tags: ['Master Data'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Detail periode'),
            new OA\Response(response: 404, description: 'Periode tidak ditemukan')
        ]
    )]
    public function show($id)
    {
        return parent::show($id);
    }

    #[OA\Put(
        path: '/periode-senat/{id}',
        summary: 'Perbarui Periode Senat',
        tags: ['Master Data'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nama_periode', type: 'string', example: '2026–2029'),
                    new OA\Property(property: 'tanggal_mulai', type: 'string', format: 'date'),
                    new OA\Property(property: 'tanggal_selesai', type: 'string', format: 'date')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Periode berhasil diperbarui')
        ]
    )]
    public function update(Request $request, $id)
    {
        return parent::update($request, $id);
    }

    #[OA\Post(
        path: '/periode-senat/{id}/activate',
        summary: 'Aktifkan Periode Senat Tertentu',
        tags: ['Master Data'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Periode berhasil diaktifkan')
        ]
    )]
    public function activate($id)
    {
        $periode = PeriodeSenat::findOrFail($id);

        PeriodeSenat::query()->update(['is_active' => false]);
        $periode->update(['is_active' => true]);

        return response()->json([
            'status' => 'success',
            'message' => "Periode {$periode->nama_periode} berhasil diaktifkan.",
            'data' => $periode->fresh()->loadCount('enrollments'),
        ]);
    }

    #[OA\Delete(
        path: '/periode-senat/{id}',
        summary: 'Hapus Periode Senat',
        tags: ['Master Data'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Periode berhasil dihapus')
        ]
    )]
    public function destroy($id)
    {
        $periode = PeriodeSenat::findOrFail($id);

        if (PeriodeSenat::count() <= 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Minimal harus ada satu periode jabatan senat yang tersisa.',
            ], 422);
        }

        $wasActive = (bool) $periode->is_active;
        $periode->delete();

        if ($wasActive) {
            $fallback = PeriodeSenat::latest('id_periode_senat')->first();
            if ($fallback) {
                $fallback->update(['is_active' => true]);
            }
        }

        return response()->noContent();
    }
}
