<?php

namespace App\Http\Controllers;

use App\Support\HeroSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Throwable;

#[OA\Schema(
    schema: 'HeroSettings',
    required: ['eyebrow', 'title', 'description', 'background_image', 'primary_label', 'secondary_label'],
    properties: [
        new OA\Property(property: 'eyebrow', type: 'string', maxLength: 160, example: 'Organ Tertinggi Normatif Akademik'),
        new OA\Property(property: 'title', description: 'Satu atau dua baris, tanpa baris kosong.', type: 'string', maxLength: 160, example: "Senat Akademik\nPoliteknik Negeri Semarang"),
        new OA\Property(property: 'description', type: 'string', maxLength: 500, example: 'Perumus kebijakan akademik dan penjaga mutu pendidikan vokasi.'),
        new OA\Property(property: 'background_image', description: 'Path aman di bawah /images/ atau URL HTTP(S) valid tanpa kredensial.', type: 'string', maxLength: 2048, example: '/images/gedung-polines.jpg'),
        new OA\Property(property: 'primary_label', description: 'Label CTA yang tersimpan, hanya dapat dibaca.', type: 'string', readOnly: true, maxLength: 80, example: 'Profil & Komisi'),
        new OA\Property(property: 'secondary_label', description: 'Label CTA yang tersimpan, hanya dapat dibaca.', type: 'string', readOnly: true, maxLength: 80, example: 'Repositori Produk Hukum'),
    ],
    type: 'object'
)]
class HeroSettingController extends Controller
{
    #[OA\Get(
        path: '/hero-settings',
        summary: 'Baca pengaturan Hero beranda',
        description: 'Endpoint publik. Mengembalikan nilai tersimpan atau nilai default jika belum ada.',
        tags: ['Hero'],
        responses: [
            new OA\Response(response: 200, description: 'Pengaturan Hero', content: new OA\JsonContent(ref: '#/components/schemas/HeroSettings')),
            new OA\Response(response: 503, description: 'Pengaturan Hero tidak tersedia'),
        ]
    )]
    public function show()
    {
        try {
            $row = DB::table('hero_settings')->where('id', 1)->first(array_keys(HeroSettings::DEFAULTS));
            return response()->json($row ? (array) $row : HeroSettings::DEFAULTS);
        } catch (Throwable) {
            Log::warning('hero_read_failed', ['category' => 'http', 'status' => 503]);
            return response()->json(['message' => 'Pengaturan hero tidak tersedia.'], 503);
        }
    }

    #[OA\Put(
        path: '/hero-settings',
        summary: 'Perbarui pengaturan Hero beranda',
        description: 'Hanya admin yang terautentikasi. Semua empat field wajib diisi; label dan tautan CTA tidak dapat diubah melalui endpoint ini.',
        tags: ['Hero'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['eyebrow', 'title', 'description', 'background_image'],
                properties: [
                    new OA\Property(property: 'eyebrow', description: 'Satu baris dan tidak kosong.', type: 'string', maxLength: 160, example: 'Organ Tertinggi Normatif Akademik'),
                    new OA\Property(property: 'title', description: 'Satu atau dua baris, tanpa baris kosong.', type: 'string', maxLength: 160, example: "Senat Akademik\nPoliteknik Negeri Semarang"),
                    new OA\Property(property: 'description', description: 'Teks tidak kosong.', type: 'string', maxLength: 500, example: 'Perumus kebijakan akademik dan penjaga mutu pendidikan vokasi.'),
                    new OA\Property(property: 'background_image', description: 'Path aman di bawah /images/ atau URL HTTP(S) valid tanpa kredensial.', type: 'string', maxLength: 2048, example: '/images/gedung-polines.jpg'),
                ],
                type: 'object',
                additionalProperties: false
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Pengaturan tersimpan termasuk label CTA yang dipertahankan', content: new OA\JsonContent(ref: '#/components/schemas/HeroSettings')),
            new OA\Response(response: 401, description: 'Token tidak ada atau tidak valid'),
            new OA\Response(response: 403, description: 'Hanya admin yang dapat mengubah pengaturan'),
            new OA\Response(response: 422, description: 'Validasi gagal atau field tidak dapat diubah'),
            new OA\Response(response: 503, description: 'Pengaturan Hero gagal disimpan'),
        ]
    )]
    public function update(Request $request)
    {
        $data = HeroSettings::validateUpdate($request->json()->all());
        try {
            $hero = DB::transaction(function () use ($data) {
                DB::table('hero_settings')->upsert([
                    ['id' => 1] + $data + HeroSettings::DEFAULTS + ['created_at' => now(), 'updated_at' => now()],
                ], ['id'], [...array_keys($data), 'updated_at']);
                return (array) DB::table('hero_settings')->where('id', 1)->first(array_keys(HeroSettings::DEFAULTS));
            });
            return response()->json($hero);
        } catch (Throwable) {
            Log::warning('hero_write_failed', ['category' => 'http', 'status' => 503]);
            return response()->json(['message' => 'Pengaturan hero gagal disimpan.'], 503);
        }
    }
}
