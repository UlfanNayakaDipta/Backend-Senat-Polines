<?php

namespace App\Http\Controllers;

use App\Models\RapatBerbagi;
use App\Support\RapatFileStore;
use Illuminate\Http\Request;

/**
 * Berbagi dokumen & tautan antar anggota pada satu rapat.
 */
class RapatBerbagiController extends Controller
{
    public function index(Request $request, $id_rapat)
    {
        return response()->json(
            RapatBerbagi::with('user:id_user,nama,role')
                ->where('id_rapat', (int) $id_rapat)
                ->latest('id_berbagi')
                ->get()
        );
    }

    public function store(Request $request, $id_rapat)
    {
        $data = $request->validate([
            'id_user' => ['required', 'exists:users,id_user'],
            'jenis' => ['nullable', 'in:file,link'],
            'nama' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:2048'],
            'file' => ['nullable', 'file', 'max:1024000'],
        ]);

        if ($request->hasFile('file')) {
            $meta = RapatFileStore::store($request->file('file'), (int) $id_rapat);
            $jenis = 'file';
        } else {
            $url = $data['url'] ?? null;

            if (! $url || ! preg_match('#^https?://#i', $url)) {
                return response()->json([
                    'message' => 'Tautan harus diawali http:// atau https://',
                    'errors' => ['url' => ['Tautan harus diawali http:// atau https://']],
                ], 422);
            }

            $jenis = $data['jenis'] ?? 'link';
            $meta = [
                'nama' => $data['nama'] ?? (parse_url($url, PHP_URL_HOST) ?: $url),
                'url' => $url,
                'ukuran' => 0,
                'mime' => null,
            ];
        }

        $item = RapatBerbagi::create([
            'id_rapat' => (int) $id_rapat,
            'id_user' => (int) $data['id_user'],
            'jenis' => $jenis,
            'nama' => $meta['nama'],
            'url' => $meta['url'],
            'ukuran' => $meta['ukuran'],
            'mime' => $meta['mime'] ?? null,
        ]);

        return response()->json($item->load('user:id_user,nama,role'), 201);
    }

    public function destroy($id_rapat, $id_berbagi)
    {
        RapatBerbagi::where('id_rapat', (int) $id_rapat)
            ->findOrFail((int) $id_berbagi)
            ->delete();

        return response()->noContent();
    }
}
