<?php

namespace App\Http\Controllers;

use App\Jobs\ProsesRekamanJob;
use App\Models\RapatRekaman;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Rekaman rapat → notulensi otomatis.
 *
 * Unggah mengembalikan 202 seketika; pekerjaan berat berjalan di queue dan
 * klien memantau lewat GET .../rekaman/{id}.
 */
class RapatRekamanController extends Controller
{
    #[OA\Get(
        path: '/rapat/{id_rapat}/rekaman',
        summary: 'Daftar Berkas Rekaman Audio/Video Rapat',
        description: 'Mengambil daftar seluruh berkas rekaman sidang untuk rapat tertentu beserta status pemrosesan transkripsi AI dan notulensinya.',
        tags: ['Rekaman & AI Notulensi'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id_rapat', in: 'path', required: true, description: 'ID Rapat', schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar rekaman rapat berhasil dimuat')
        ]
    )]
    public function index($id_rapat)
    {
        $rows = RapatRekaman::with('user:id_user,nama')
            ->where('id_rapat', (int) $id_rapat)
            ->latest('id_rekaman')
            ->get()
            ->map(fn (RapatRekaman $r) => $this->ringkas($r));

        return response()->json($rows);
    }

    #[OA\Post(
        path: '/rapat/{id_rapat}/rekaman',
        summary: 'Unggah Rekaman Sidang untuk Transkripsi & Notulensi Otomatis',
        description: 'Menerima berkas audio atau video rapat hingga 1GB / durasi 4 jam (format MP3, WAV, MP4, MOV, MKV, WebM, dll). Sistem mengembalikan status 202 Accepted dan memproses transkripsi Whisper serta perumusan notulensi secara asinkron di antrean.',
        tags: ['Rekaman & AI Notulensi'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id_rapat', in: 'path', required: true, description: 'ID Rapat', schema: new OA\Schema(type: 'integer', example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['id_user', 'berkas'],
                    properties: [
                        new OA\Property(property: 'id_user', type: 'integer', example: 1, description: 'ID pengguna pengunggah'),
                        new OA\Property(property: 'berkas', type: 'string', format: 'binary', description: 'Berkas audio/video rekaman (maks 1GB)'),
                        new OA\Property(property: 'bahasa', type: 'string', example: 'id', description: 'Kode bahasa (default: id)')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 202, description: 'Berkas diterima, proses transkripsi & notulensi dijadwalkan di antrean background job'),
            new OA\Response(response: 422, description: 'Format berkas tidak didukung atau ukuran melebihi batas 1GB')
        ]
    )]
    public function store(Request $request, $id_rapat)
    {
        $data = $request->validate([
            'id_user' => ['required', 'exists:users,id_user'],
            'berkas' => ['required', 'file', 'max:1024000'], // 1 GB
            'bahasa' => ['nullable', 'string', 'max:10'],
        ]);

        $file = $request->file('berkas');
        $ekstensi = strtolower($file->getClientOriginalExtension());

        $diizinkan = ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'flac', 'opus', 'mp4', 'mov', 'mkv', 'webm', 'avi', '3gp', 'amr'];
        if (! in_array($ekstensi, $diizinkan, true)) {
            return response()->json([
                'message' => 'Format tidak didukung. Gunakan audio/video: ' . implode(', ', $diizinkan),
                'errors' => ['berkas' => ['Format tidak didukung.']],
            ], 422);
        }

        // Metadata harus dibaca SEBELUM move(): setelah dipindah, SplFileInfo
        // menunjuk path sementara yang sudah tidak ada.
        $nama = $file->getClientOriginalName();
        $ukuran = (int) $file->getSize();
        $mime = $file->getClientMimeType();

        $dir = public_path('uploads/rekaman/' . (int) $id_rapat);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $simpan = $file->move($dir, uniqid('rek-', true) . '.' . $ekstensi);

        $rekaman = RapatRekaman::create([
            'id_rapat' => (int) $id_rapat,
            'id_user' => (int) $data['id_user'],
            'nama_asli' => $nama,
            'berkas' => 'uploads/rekaman/' . (int) $id_rapat . '/' . $simpan->getFilename(),
            'mime' => $mime,
            'ukuran' => $ukuran,
            'status' => 'unggah',
            'progres' => 2,
        ]);

        ProsesRekamanJob::dispatch($rekaman->id_rekaman);

        return response()->json($this->ringkas($rekaman->fresh()), 202);
    }

    #[OA\Get(
        path: '/rapat/{id_rapat}/rekaman/{id_rekaman}',
        summary: 'Cek Status & Hasil Transkripsi / Notulensi Rekaman',
        description: 'Memantau progres pemrosesan rekaman (unggah, proses, selesai, gagal), persentase progres, hasil transkripsi lengkap, serta rancangan notulensi yang dihasilkan AI.',
        tags: ['Rekaman & AI Notulensi'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id_rapat', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'id_rekaman', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Status rekaman dan hasil pemrosesan AI'),
            new OA\Response(response: 404, description: 'Rekaman tidak ditemukan')
        ]
    )]
    public function show($id_rapat, $id_rekaman)
    {
        $rekaman = RapatRekaman::where('id_rapat', (int) $id_rapat)
            ->findOrFail((int) $id_rekaman);

        return response()->json($this->ringkas($rekaman));
    }

    #[OA\Delete(
        path: '/rapat/{id_rapat}/rekaman/{id_rekaman}',
        summary: 'Hapus Berkas Rekaman Sidang',
        tags: ['Rekaman & AI Notulensi'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id_rapat', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'id_rekaman', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Berkas rekaman dan metadata berhasil dihapus'),
            new OA\Response(response: 404, description: 'Rekaman tidak ditemukan')
        ]
    )]
    public function destroy($id_rapat, $id_rekaman)
    {
        $rekaman = RapatRekaman::where('id_rapat', (int) $id_rapat)
            ->findOrFail((int) $id_rekaman);

        $path = public_path($rekaman->berkas);
        if (is_file($path)) {
            @unlink($path);
        }

        $rekaman->delete();

        return response()->noContent();
    }

    private function ringkas(RapatRekaman $r): array
    {
        return [
            'id_rekaman' => $r->id_rekaman,
            'id_rapat' => $r->id_rapat,
            'nama_asli' => $r->nama_asli,
            'ukuran' => $r->ukuran,
            'durasi' => $r->durasi,
            'status' => $r->status,
            'progres' => $r->progres,
            'transkrip' => $r->transkrip,
            'notulen' => $r->notulen,
            'id_notulensi' => $r->id_notulensi,
            'error' => $r->error,
            'pengunggah' => $r->user->nama ?? null,
            'dibuat' => $r->created_at,
            'selesai' => $r->status === 'selesai',
        ];
    }
}
