<?php

namespace Tests\Feature;

use App\Models\Berita;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BeritaGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_berita_api_persists_multiple_images_and_attachments(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $gallery = [
            'https://example.test/berita/foto-satu.jpg',
            'https://example.test/berita/foto-dua.jpg',
        ];
        $attachments = [
            ['name' => 'Risalah Sidang', 'url' => 'https://example.test/berkas/risalah.pdf'],
        ];

        $response = $this->postJson('/api/berita', [
            'id_user' => $user->id_user,
            'judul' => 'Dokumentasi Sidang Pleno',
            'isi' => 'Isi berita untuk pengujian.',
            'gambar' => $gallery[0],
            'galeri' => $gallery,
            'lampiran' => $attachments,
            'tanggal_publish' => now()->toDateTimeString(),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('galeri', $gallery)
            ->assertJsonPath('lampiran', $attachments);

        $berita = Berita::findOrFail($response->json('id_berita'));
        $this->assertSame($gallery, $berita->galeri);
        $this->assertSame($attachments, $berita->lampiran);
    }

    public function test_berita_api_rejects_non_http_media_urls(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $this->postJson('/api/berita', [
            'id_user' => $user->id_user,
            'judul' => 'Berita Dengan URL Tidak Valid',
            'isi' => 'Isi berita.',
            'galeri' => ['javascript:alert(1)'],
            'lampiran' => [['name' => 'Lampiran', 'url' => 'javascript:alert(1)']],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['galeri.0', 'lampiran.0.url']);
    }
}
