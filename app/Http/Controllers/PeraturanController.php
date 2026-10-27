<?php

namespace App\Http\Controllers;

use App\Models\Peraturan;

class PeraturanController extends CrudController
{
    protected string $model = Peraturan::class;
    protected array $with = ['periodeSenat'];

    protected function rules(?int $id = null): array
    {
        return [
            'id_periode_senat' => ['required', 'exists:periode_senats,id_periode_senat'],
            'nomor_sk' => ['required', 'string', 'max:255'],
            'judul' => ['required', 'string', 'max:255'],
            'kategori' => ['required', 'string', 'max:255'],
            'tahap_pembentukan' => ['required', 'string', 'max:255'],
            'file_dokumen' => ['nullable', 'string', 'max:500'],
            'tanggal_terbit' => ['required', 'date'],
        ];
    }

    public function index()
    {
        $request = request();
        $query = $this->model::with($this->with);

        if ($request->filled('kategori') && $request->query('kategori') !== 'all') {
            $query->where('kategori', $request->query('kategori'));
        }

        if ($request->filled('tahap') && $request->query('tahap') !== 'all') {
            $query->where('tahap_pembentukan', $request->query('tahap'));
        }

        if ($request->filled('periode')) {
            $query->where('id_periode_senat', $request->query('periode'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhere('nomor_sk', 'like', "%{$search}%")
                  ->orWhere('kategori', 'like', "%{$search}%")
                  ->orWhere('tahap_pembentukan', 'like', "%{$search}%");
            });
        }

        $query->orderByDesc('tanggal_terbit')->orderByDesc('id_peraturan');

        if ($request->boolean('all')) {
            return response()->json($query->get());
        }

        return $query->paginate($request->integer('per_page', 15));
    }
}
