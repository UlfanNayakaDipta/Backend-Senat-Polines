<?php

namespace App\Http\Controllers;

use App\Models\Notulensi;
use App\Services\NotulenPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Throwable;

class NotulensiController extends CrudController
{
    protected string $model = Notulensi::class;
    protected array $with = ['user', 'rapat', 'rapat.jenisRapat'];

    protected function rules(?int $id = null): array
    {
        return [
            'id_rapat' => ['required', 'exists:rapats,id_rapat'],
            'id_user' => ['required', 'exists:users,id_user'],
            'isi_notulensi' => ['required', 'string'],
            'status' => ['nullable', 'string', 'in:draf,review,disahkan,Draf,Review,Disahkan'],
            'rekomendasi_keputusan' => ['nullable', 'string', 'in:Pertimbangan,Penetapan,Keputusan'],
            'file_risalah' => ['nullable', 'string', 'max:500'],
            'tanggal_dibuat' => ['nullable', 'date'],
        ];
    }

    #[OA\Get(
        path: '/notulensi',
        summary: 'Daftar Notulensi & Risalah Rapat Senat',
        description: 'Mengambil daftar notulensi rapat dengan filter id_rapat, status pengesahan, dan pencarian teks risalah.',
        tags: ['Notulensi & Risalah'],
        parameters: [
            new OA\Parameter(name: 'id_rapat', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['draf', 'review', 'disahkan'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 15))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar notulensi terpaginasi')
        ]
    )]
    public function index()
    {
        $request = request();
        $query = $this->model::with($this->with);

        if ($request->filled('id_rapat')) {
            $query->where('id_rapat', $request->query('id_rapat'));
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', strtolower($request->query('status')));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('isi_notulensi', 'like', "%{$search}%")
                  ->orWhereHas('rapat', function ($qr) use ($search) {
                      $qr->where('nama_agenda', 'like', "%{$search}%")
                         ->orWhere('tempat', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->boolean('all')) {
            return response()->json($query->latest('id_notulensi')->get());
        }

        $perPage = $request->integer('per_page', 15);
        return $query->latest('id_notulensi')->paginate($perPage);
    }

    #[OA\Post(
        path: '/notulensi',
        summary: 'Buat Notulensi Baru',
        description: 'Menyimpan draf notulensi atau risalah rapat senat.',
        tags: ['Notulensi & Risalah'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_rapat', 'id_user', 'isi_notulensi'],
                properties: [
                    new OA\Property(property: 'id_rapat', type: 'integer', example: 1),
                    new OA\Property(property: 'id_user', type: 'integer', example: 3),
                    new OA\Property(property: 'isi_notulensi', type: 'string', example: 'Rapat pleno memutuskan penerimaan usulan kurikulum OBE 2026.'),
                    new OA\Property(property: 'status', type: 'string', enum: ['draf', 'review', 'disahkan'], example: 'draf'),
                    new OA\Property(property: 'rekomendasi_keputusan', type: 'string', enum: ['Pertimbangan', 'Penetapan', 'Keputusan'], example: 'Penetapan'),
                    new OA\Property(property: 'file_risalah', type: 'string', nullable: true),
                    new OA\Property(property: 'tanggal_dibuat', type: 'string', format: 'date', example: '2026-11-12')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Notulensi berhasil dibuat'),
            new OA\Response(response: 422, description: 'Validasi data notulensi gagal')
        ]
    )]
    public function store(Request $request)
    {
        return parent::store($request);
    }

    #[OA\Get(
        path: '/notulensi/{id}',
        summary: 'Detail Notulensi Rapat',
        tags: ['Notulensi & Risalah'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Detail notulensi beserta rapat terkait'),
            new OA\Response(response: 404, description: 'Notulensi tidak ditemukan')
        ]
    )]
    public function show($id)
    {
        return parent::show($id);
    }

    #[OA\Put(
        path: '/notulensi/{id}',
        summary: 'Perbarui Notulensi Rapat',
        tags: ['Notulensi & Risalah'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'isi_notulensi', type: 'string'),
                    new OA\Property(property: 'status', type: 'string', enum: ['draf', 'review', 'disahkan'], example: 'disahkan'),
                    new OA\Property(property: 'rekomendasi_keputusan', type: 'string', enum: ['Pertimbangan', 'Penetapan', 'Keputusan'])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Notulensi berhasil diperbarui')
        ]
    )]
    public function update(Request $request, $id)
    {
        $res = parent::update($request, $id);

        // Notulen yang disunting dicetak ulang supaya PDF selalu sejalan dengan teks terbaru.
        if ($request->filled('isi_notulensi')) {
            try {
                $notulensi = Notulensi::find((int) $id);
                if ($notulensi) {
                    app(NotulenPdfService::class)->buat($notulensi);
                }
            } catch (Throwable $e) {
                Log::warning('Gagal mencetak ulang PDF notulen: ' . $e->getMessage(), ['id_notulensi' => $id]);
            }
        }

        return $res;
    }

    #[OA\Delete(
        path: '/notulensi/{id}',
        summary: 'Hapus Notulensi Rapat',
        tags: ['Notulensi & Risalah'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Notulensi berhasil dihapus')
        ]
    )]
    public function destroy($id)
    {
        return parent::destroy($id);
    }

    #[OA\Get(
        path: '/notulensi/{id}/pdf',
        summary: 'Unduh PDF Notulen Rapat',
        description: 'Mengunduh notulen resmi berformat FPM 5.5.01/L2. Jika PDF belum ada, dokumen dicetak saat permintaan.',
        tags: ['Notulensi & Risalah'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Berkas PDF notulen',
                content: new OA\MediaType(
                    mediaType: 'application/pdf',
                    schema: new OA\Schema(type: 'string', format: 'binary')
                )
            ),
            new OA\Response(response: 404, description: 'Notulensi tidak ditemukan'),
            new OA\Response(response: 500, description: 'Gagal mencetak PDF')
        ]
    )]
    public function pdf($id, NotulenPdfService $pdfService)
    {
        $notulensi = Notulensi::with('rapat')->findOrFail((int) $id);

        $path = $pdfService->path($notulensi);

        if ($path === null) {
            try {
                $path = public_path($pdfService->buat($notulensi));
            } catch (Throwable $e) {
                Log::error('Gagal mencetak PDF notulen: ' . $e->getMessage(), ['id_notulensi' => $id]);

                return response()->json(['message' => 'Gagal mencetak PDF notulen.'], 500);
            }
        }

        $nama = 'Notulen Rapat - ' . ($notulensi->rapat->nama_agenda ?? 'Senat') . '.pdf';
        $nama = preg_replace('/[^A-Za-z0-9 ._-]+/', '_', $nama) ?: 'notulen.pdf';

        return response()->download($path, $nama, ['Content-Type' => 'application/pdf']);
    }

    #[OA\Post(
        path: '/notulensi/{id}/pdf',
        summary: 'Cetak Ulang PDF Notulen',
        description: 'Mencetak ulang PDF dari isi_notulensi terbaru (dipakai setelah notulen diedit).',
        tags: ['Notulensi & Risalah'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'PDF berhasil dicetak ulang'),
            new OA\Response(response: 404, description: 'Notulensi tidak ditemukan'),
            new OA\Response(response: 500, description: 'Gagal mencetak PDF')
        ]
    )]
    public function cetak($id, NotulenPdfService $pdfService)
    {
        $notulensi = Notulensi::findOrFail((int) $id);

        try {
            $relatif = $pdfService->buat($notulensi);
        } catch (Throwable $e) {
            Log::error('Gagal mencetak PDF notulen: ' . $e->getMessage(), ['id_notulensi' => $id]);

            return response()->json(['message' => 'Gagal mencetak PDF notulen.'], 500);
        }

        return response()->json([
            'berkas_pdf' => $relatif,
            'berkas_pdf_url' => url($relatif),
        ], 201);
    }
}
