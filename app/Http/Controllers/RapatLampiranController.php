<?php

namespace App\Http\Controllers;

use App\Models\RapatLampiran;
use App\Support\RapatFileStore;
use Illuminate\Http\Request;

/**
 * Lampiran notulensi: unggah berkas (multipart) atau daftarkan URL.
 */
class RapatLampiranController extends Controller
{
    public function index(Request $request, $id_rapat)
    {
        return response()->json(
            RapatLampiran::with('user:id_user,nama,role')
                ->where('id_rapat', (int) $id_rapat)
                ->latest('id_lampiran')
                ->get()
        );
    }

    public function store(Request $request, $id_rapat)
    {
        $data = $request->validate([
            'id_user' => ['required', 'exists:users,id_user'],
            'file' => ['nullable', 'file', 'max:1024000'],
            'nama' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:2048'],
        ]);

        $adaFile = $request->hasFile('file');

        if (! $adaFile && empty($data['url'])) {
            return response()->json([
                'message' => 'Berkas atau URL lampiran wajib diisi.',
                'errors' => ['file' => ['Berkas atau URL lampiran wajib diisi.']],
            ], 422);
        }

        $meta = $adaFile
            ? RapatFileStore::store($request->file('file'), (int) $id_rapat)
            : [
                'nama' => $data['nama'] ?? basename(parse_url($data['url'], PHP_URL_PATH) ?: 'lampiran'),
                'url' => $data['url'],
                'ukuran' => 0,
                'mime' => null,
            ];

        if (! empty($data['nama'])) {
            $meta['nama'] = $data['nama'];
        }

        $lampiran = RapatLampiran::create([
            'id_rapat' => (int) $id_rapat,
            'id_user' => (int) $data['id_user'],
            'nama' => $meta['nama'],
            'url' => $meta['url'],
            'ukuran' => $meta['ukuran'],
            'mime' => $meta['mime'],
        ]);

        return response()->json($lampiran->load('user:id_user,nama,role'), 201);
    }

    public function destroy($id_rapat, $id_lampiran)
    {
        $lampiran = RapatLampiran::where('id_rapat', (int) $id_rapat)
            ->findOrFail((int) $id_lampiran);

        $lampiran->delete();

        return response()->noContent();
    }
}
