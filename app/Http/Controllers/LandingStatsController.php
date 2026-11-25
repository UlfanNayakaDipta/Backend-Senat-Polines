<?php

namespace App\Http\Controllers;

use App\Models\Berita;
use App\Models\Enrollment;
use App\Models\Komisi;
use App\Models\PeriodeSenat;
use Illuminate\Http\JsonResponse;

class LandingStatsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $period = PeriodeSenat::active();

        return response()->json([
            'members' => $period ? Enrollment::where('id_periode_senat', $period->id_periode_senat)->distinct()->count('id_user') : 0,
            'committees' => Komisi::count(),
            'published_news' => Berita::whereNotNull('tanggal_publish')->where('tanggal_publish', '<=', now())->count(),
        ]);
    }
}
