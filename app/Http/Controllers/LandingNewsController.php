<?php

namespace App\Http\Controllers;

use App\Models\Berita;
use Illuminate\Http\JsonResponse;

class LandingNewsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => Berita::whereNotNull('tanggal_publish')
            ->where('tanggal_publish', '<=', now())
            ->orderByDesc('tanggal_publish')
            ->orderByDesc('id_berita')
            ->limit(6)->get()]);
    }
}
