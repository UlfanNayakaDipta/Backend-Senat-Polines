<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LandingStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_aggregate_counts_unique_members_and_published_news(): void
    {
        $user = DB::table('users')->insertGetId(['nama' => 'Test', 'email' => 'stats@example.test', 'password' => bcrypt('test'), 'nim_nip' => '123', 'role' => 'anggota'], 'id_user');
        $period = DB::table('periode_senats')->insertGetId(['nama_periode' => 'Now', 'tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2027-01-01', 'is_active' => true], 'id_periode_senat');
        $old = DB::table('periode_senats')->insertGetId(['nama_periode' => 'Old', 'tanggal_mulai' => '2020-01-01', 'tanggal_selesai' => '2021-01-01', 'is_active' => false], 'id_periode_senat');
        foreach (['A', 'B'] as $name) {
            $committee = DB::table('komisis')->insertGetId(['nama_komisi' => $name], 'id_komisi');
            DB::table('enrollments')->insert(['id_user' => $user, 'id_komisi' => $committee, 'id_periode_senat' => $period, 'jabatan_komisi' => 'Anggota']);
        }
        $other = DB::table('users')->insertGetId(['nama' => 'Old', 'email' => 'old@example.test', 'password' => bcrypt('test'), 'nim_nip' => '456', 'role' => 'anggota'], 'id_user');
        DB::table('enrollments')->insert(['id_user' => $other, 'id_komisi' => $committee, 'id_periode_senat' => $old, 'jabatan_komisi' => 'Anggota']);
        foreach (range(1, 21) as $i) {
            DB::table('beritas')->insert(['id_user' => $user, 'judul' => "News $i", 'isi' => 'Content', 'tanggal_publish' => '2026-01-01 00:00:00']);
        }
        DB::table('beritas')->insert(['id_user' => $user, 'judul' => 'Draft', 'isi' => 'Content', 'tanggal_publish' => null]);
        DB::table('beritas')->insert(['id_user' => $user, 'judul' => 'Scheduled', 'isi' => 'Content', 'tanggal_publish' => now()->addYear()]);
        $this->getJson('/api/landing-stats')->assertOk()->assertExactJson(['members' => 1, 'committees' => 2, 'published_news' => 21]);
    }

    public function test_when_no_period_is_active_latest_period_is_used(): void
    {
        $user = DB::table('users')->insertGetId(['nama' => 'Fallback', 'email' => 'fallback@example.test', 'password' => bcrypt('test'), 'nim_nip' => '789', 'role' => 'anggota'], 'id_user');
        DB::table('periode_senats')->insert(['nama_periode' => 'Old', 'tanggal_mulai' => '2020-01-01', 'tanggal_selesai' => '2021-01-01', 'is_active' => false]);
        $latest = DB::table('periode_senats')->insertGetId(['nama_periode' => 'Latest', 'tanggal_mulai' => '2022-01-01', 'tanggal_selesai' => '2023-01-01', 'is_active' => false], 'id_periode_senat');
        $committee = DB::table('komisis')->insertGetId(['nama_komisi' => 'A'], 'id_komisi');
        DB::table('enrollments')->insert(['id_user' => $user, 'id_komisi' => $committee, 'id_periode_senat' => $latest, 'jabatan_komisi' => 'Anggota']);
        $this->getJson('/api/landing-stats')->assertOk()->assertExactJson(['members' => 1, 'committees' => 1, 'published_news' => 0]);
    }

    public function test_empty_database_returns_zero_counts(): void
    {
        $this->getJson('/api/landing-stats')->assertOk()->assertExactJson(['members' => 0, 'committees' => 0, 'published_news' => 0]);
    }
}
