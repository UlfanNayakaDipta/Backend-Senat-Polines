<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LandingNewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_latest_published_news_is_returned_even_beyond_first_page(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 1)->setTime(12, 0));
        $user = DB::table('users')->insertGetId(['nama' => 'Test', 'email' => 'news@example.test', 'password' => bcrypt('test'), 'nim_nip' => '123', 'role' => 'anggota'], 'id_user');
        foreach (range(1, 22) as $i) {
            DB::table('beritas')->insert(['id_user' => $user, 'judul' => "News $i", 'isi' => 'Content', 'tanggal_publish' => now()->subMinutes(23 - $i)]);
        }
        DB::table('beritas')->insert(['id_user' => $user, 'judul' => 'Draft', 'isi' => 'Content', 'tanggal_publish' => null]);
        DB::table('beritas')->insert(['id_user' => $user, 'judul' => 'Future', 'isi' => 'Content', 'tanggal_publish' => now()->addSecond()]);
        $result = $this->getJson('/api/landing-news')->assertOk()->json('data');
        $this->assertSame(['News 22', 'News 21', 'News 20', 'News 19', 'News 18', 'News 17'], array_column($result, 'judul'));
        $this->getJson('/api/landing-stats')->assertOk()->assertJsonPath('published_news', 22);
    }

    public function test_empty_list_is_successful(): void
    {
        $this->getJson('/api/landing-news')->assertOk()->assertExactJson(['data' => []]);
    }
}
