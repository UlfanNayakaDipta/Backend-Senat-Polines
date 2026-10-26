<?php

namespace App\Http\Controllers;

use App\Models\Notulensi;

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
            'file_risalah' => ['nullable', 'string', 'max:500'],
            'tanggal_dibuat' => ['nullable', 'date'],
        ];
    }

    public function index()
    {
        $request = request();
        $query = $this->model::with($this->with);

        if ($request->filled('id_rapat')) {
            $query->where('id_rapat', $request->query('id_rapat'));
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
}
