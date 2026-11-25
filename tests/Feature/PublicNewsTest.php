<?php

namespace Tests\Feature;

use App\Models\Berita;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicNewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_news_returns_paginated_published_news_beyond_six(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 1)->setTime(12, 0));
        $user = DB::table('users')->insertGetId([
            'nama' => 'Humas Senat',
            'email' => 'humas@example.test',
            'password' => bcrypt('test'),
            'nim_nip' => '19800101',
            'role' => 'anggota',
        ], 'id_user');

        // 20 published news items
        foreach (range(1, 20) as $i) {
            DB::table('beritas')->insert([
                'id_user' => $user,
                'judul' => sprintf('Berita Publik %02d', $i),
                'isi' => "Isi lengkap berita nomor $i.",
                'gambar' => "https://example.test/img$i.jpg",
                'tanggal_publish' => now()->subMinutes(25 - $i),
            ]);
        }

        // Draft and future scheduled news
        DB::table('beritas')->insert([
            'id_user' => $user,
            'judul' => 'Draf Rahasia',
            'isi' => 'Konten draf yang belum terbit.',
            'tanggal_publish' => null,
        ]);
        DB::table('beritas')->insert([
            'id_user' => $user,
            'judul' => 'Berita Masa Depan',
            'isi' => 'Dijadwalkan besok.',
            'tanggal_publish' => now()->addDay(),
        ]);

        // Request page 1 with 15 per page
        $res = $this->getJson('/api/public-news?per_page=15')->assertOk();

        $json = $res->json();
        $this->assertSame(20, $json['total']);
        $this->assertSame(15, count($json['data']));
        $this->assertSame(15, $json['per_page']);
        $this->assertSame(1, $json['current_page']);
        $this->assertSame(2, $json['last_page']);

        // Check order: newest first (Berita Publik 20)
        $this->assertSame('Berita Publik 20', $json['data'][0]['judul']);
        $this->assertSame('berita-publik-20', $json['data'][0]['slug']);
        $this->assertSame('Humas Senat', $json['data'][0]['author']);

        // Ensure drafts and scheduled are not exposed
        $titles = array_column($json['data'], 'judul');
        $this->assertNotContains('Draf Rahasia', $titles);
        $this->assertNotContains('Berita Masa Depan', $titles);

        // Request page 2
        $resPage2 = $this->getJson('/api/public-news?per_page=15&page=2')->assertOk();
        $jsonPage2 = $resPage2->json();
        $this->assertSame(5, count($jsonPage2['data']));
        $this->assertSame(2, $jsonPage2['current_page']);
    }

    public function test_public_news_search_filters_published_news(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 1)->setTime(12, 0));
        $user = DB::table('users')->insertGetId([
            'nama' => 'Editor Senat',
            'email' => 'editor@example.test',
            'password' => bcrypt('test'),
            'nim_nip' => '19800102',
            'role' => 'anggota',
        ], 'id_user');

        DB::table('beritas')->insert([
            'id_user' => $user,
            'judul' => 'Sosialisasi Kurikulum Terapan',
            'isi' => 'Rapat pleno membahas kurikulum vokasi.',
            'tanggal_publish' => now()->subHour(),
        ]);
        DB::table('beritas')->insert([
            'id_user' => $user,
            'judul' => 'Kunjungan Industri Mitra',
            'isi' => 'Penjajakan kerja sama industri.',
            'tanggal_publish' => now()->subHours(2),
        ]);
        // Draft matching keyword should NOT be returned
        DB::table('beritas')->insert([
            'id_user' => $user,
            'judul' => 'Draf Kurikulum Baru',
            'isi' => 'Rencana kurikulum rahasia.',
            'tanggal_publish' => null,
        ]);

        $res = $this->getJson('/api/public-news?search=Kurikulum')->assertOk();
        $json = $res->json();
        $this->assertSame(1, $json['total']);
        $this->assertSame('Sosialisasi Kurikulum Terapan', $json['data'][0]['judul']);
    }

    public function test_public_news_detail_by_slug_and_id(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 1)->setTime(12, 0));
        $user = DB::table('users')->insertGetId([
            'nama' => 'Humas Senat',
            'email' => 'humas2@example.test',
            'password' => bcrypt('test'),
            'nim_nip' => '19800103',
            'role' => 'anggota',
        ], 'id_user');

        $id = DB::table('beritas')->insertGetId([
            'id_user' => $user,
            'judul' => 'Sidang Pleno Senat Mei 2026',
            'isi' => "Paragraf satu.\n\nParagraf dua.",
            'gambar' => 'https://example.test/sidang.jpg',
            'galeri' => json_encode(['https://example.test/sidang1.jpg', 'https://example.test/sidang2.jpg']),
            'lampiran' => json_encode([['name' => 'Dokumen Risalah', 'url' => 'https://example.test/risalah.pdf']]),
            'tanggal_publish' => now()->subDay(),
        ], 'id_berita');

        // Test find by slug
        $resBySlug = $this->getJson('/api/public-news/sidang-pleno-senat-mei-2026')->assertOk();
        $dataBySlug = $resBySlug->json('data');
        $this->assertSame($id, $dataBySlug['id_berita']);
        $this->assertSame('Sidang Pleno Senat Mei 2026', $dataBySlug['judul']);
        $this->assertSame('sidang-pleno-senat-mei-2026', $dataBySlug['slug']);
        $this->assertSame('Humas Senat', $dataBySlug['author']);
        $this->assertCount(2, $dataBySlug['galeri']);
        $this->assertCount(1, $dataBySlug['lampiran']);

        // Test find by ID
        $resById = $this->getJson("/api/public-news/{$id}")->assertOk();
        $this->assertSame('Sidang Pleno Senat Mei 2026', $resById->json('data.judul'));
    }

    public function test_public_news_detail_does_not_reveal_draft_or_future_news(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 1)->setTime(12, 0));
        $user = DB::table('users')->insertGetId([
            'nama' => 'Humas Senat',
            'email' => 'humas3@example.test',
            'password' => bcrypt('test'),
            'nim_nip' => '19800104',
            'role' => 'anggota',
        ], 'id_user');

        $draftId = DB::table('beritas')->insertGetId([
            'id_user' => $user,
            'judul' => 'Draf Belum Terbit',
            'isi' => 'Konten draf.',
            'tanggal_publish' => null,
        ], 'id_berita');

        $futureId = DB::table('beritas')->insertGetId([
            'id_user' => $user,
            'judul' => 'Berita Terjadwal Nanti',
            'isi' => 'Konten terjadwal.',
            'tanggal_publish' => now()->addWeek(),
        ], 'id_berita');

        $this->getJson("/api/public-news/{$draftId}")->assertNotFound();
        $this->getJson('/api/public-news/draf-belum-terbit')->assertNotFound();

        $this->getJson("/api/public-news/{$futureId}")->assertNotFound();
        $this->getJson('/api/public-news/berita-terjadwal-nanti')->assertNotFound();
    }

    public function test_public_news_empty_database_returns_empty_paginator(): void
    {
        $res = $this->getJson('/api/public-news')->assertOk();
        $this->assertSame(0, $res->json('total'));
        $this->assertSame([], $res->json('data'));
    }
}
