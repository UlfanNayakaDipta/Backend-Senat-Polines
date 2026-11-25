<?php

namespace App\Http\Controllers;

use App\Models\Berita;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicNewsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Berita::with('user')
            ->whereNotNull('tanggal_publish')
            ->where('tanggal_publish', '<=', now())
            ->orderByDesc('tanggal_publish')
            ->orderByDesc('id_berita');

        $search = trim((string) ($request->query('search') ?? $request->query('q') ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                    ->orWhere('isi', 'like', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 12);
        if ($perPage < 1) {
            $perPage = 12;
        } elseif ($perPage > 100) {
            $perPage = 100;
        }

        $paginator = $query->paginate($perPage);

        $paginator->getCollection()->transform(function (Berita $berita) {
            $item = $berita->toArray();
            $item['slug'] = Str::slug($berita->judul ?? ('berita-' . $berita->id_berita));
            $item['author'] = $berita->user->nama ?? 'Sekretariat Senat Polines';
            return $item;
        });

        return response()->json($paginator);
    }

    public function show(string $slugOrId): JsonResponse
    {
        $query = Berita::with('user')
            ->whereNotNull('tanggal_publish')
            ->where('tanggal_publish', '<=', now());

        $berita = null;
        if (ctype_digit($slugOrId)) {
            $berita = (clone $query)->where('id_berita', (int) $slugOrId)->first();
        }

        if (! $berita) {
            $berita = $query->get()->first(function (Berita $b) use ($slugOrId) {
                return Str::slug($b->judul ?? '') === $slugOrId;
            });
        }

        if (! $berita) {
            abort(404, 'Berita tidak ditemukan.');
        }

        $item = $berita->toArray();
        $item['slug'] = Str::slug($berita->judul ?? ('berita-' . $berita->id_berita));
        $item['author'] = $berita->user->nama ?? 'Sekretariat Senat Polines';

        return response()->json(['data' => $item]);
    }
}
