<?php

namespace App\Http\Controllers;

use App\Models\RapatCatatan;
use Illuminate\Http\Request;

/**
 * Catatan risalah anggota per rapat (satu catatan per anggota per rapat).
 */
class RapatCatatanController extends Controller
{
    public function index(Request $request, $id_rapat)
    {
        $query = RapatCatatan::with('user:id_user,nama,role')
            ->where('id_rapat', (int) $id_rapat);

        if ($request->filled('id_user')) {
            $query->where('id_user', $request->integer('id_user'));
        }

        return response()->json($query->get());
    }

    /** Simpan / perbarui catatan milik satu anggota pada satu rapat. */
    public function upsert(Request $request, $id_rapat)
    {
        $data = $request->validate([
            'id_user' => ['required', 'exists:users,id_user'],
            'isi' => ['nullable', 'string'],
        ]);

        $catatan = RapatCatatan::updateOrCreate(
            [
                'id_rapat' => (int) $id_rapat,
                'id_user' => (int) $data['id_user'],
            ],
            ['isi' => $data['isi'] ?? '']
        );

        return response()->json($catatan->load('user:id_user,nama,role'), 200);
    }
}
