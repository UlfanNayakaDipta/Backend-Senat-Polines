<?php

namespace App\Http\Controllers;

use App\Jobs\ProsesRekamanJob;
use App\Models\RapatRekaman;
use Illuminate\Http\Request;

/**
 * Rekaman rapat → notulensi otomatis.
 *
 * Unggah mengembalikan 202 seketika; pekerjaan berat berjalan di queue dan
 * klien memantau lewat GET .../rekaman/{id}.
 */
class RapatRekamanController extends Controller
{
    public function index($id_rapat)
    {
        $rows = RapatRekaman::with('user:id_user,nama')
            ->where('id_rapat', (int) $id_rapat)
            ->latest('id_rekaman')
            ->get()
            ->map(fn (RapatRekaman $r) => $this->ringkas($r));

        return response()->json($rows);
    }

    public function store(Request $request, $id_rapat)
    {
        $data = $request->validate([
            'id_user' => ['required', 'exists:users,id_user'],
            'berkas' => ['required', 'file', 'max:512000'], // 500 MB
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

    public function show($id_rapat, $id_rekaman)
    {
        $rekaman = RapatRekaman::where('id_rapat', (int) $id_rapat)
            ->findOrFail((int) $id_rekaman);

        return response()->json($this->ringkas($rekaman));
    }

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
