<?php

namespace Tests\Feature;

use App\Models\JenisRapat;
use App\Models\Rapat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
        ]);
    }

    private function createAnggota(): User
    {
        return User::factory()->create([
            'role' => 'anggota',
        ]);
    }

    public function test_public_routes_can_be_accessed_without_authentication(): void
    {
        $this->getJson('/api/berita')->assertOk();
        $this->getJson('/api/peraturan')->assertOk();
        $this->getJson('/api/komisi')->assertOk();
        $this->getJson('/api/rapat')->assertOk();
    }

    public function test_authenticated_route_rejects_unauthenticated_requests(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
        $this->postJson('/api/rapat/presensi')->assertStatus(401);
    }

    public function test_admin_route_rejects_unauthenticated_requests(): void
    {
        $this->postJson('/api/user', [])->assertStatus(401);
        $this->postJson('/api/voting', [])->assertStatus(401);
    }

    public function test_admin_route_rejects_anggota_user(): void
    {
        $anggota = $this->createAnggota();
        Sanctum::actingAs($anggota);

        $response = $this->postJson('/api/voting', [
            'judul_voting' => 'Test Voting',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status'  => 'error',
                'message' => 'Forbidden. Anda tidak memiliki hak akses untuk tindakan ini.',
            ]);
    }

    public function test_admin_route_allows_admin_user(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $jenis = JenisRapat::create(['nama_jenis' => 'Sidang Pleno']);
        $rapat = Rapat::create([
            'id_user'        => $admin->id_user,
            'id_jenis_rapat' => $jenis->id_jenis_rapat,
            'nama_agenda'    => 'Agenda Pleno',
            'tanggal'        => now()->toDateString(),
            'waktu'          => '10:00',
            'tempat'         => 'Gedung Senat',
            'status'         => 'berlangsung',
        ]);

        $response = $this->postJson('/api/voting', [
            'id_rapat'     => $rapat->id_rapat,
            'judul_voting' => 'Voting Uji Kebijakan',
        ]);

        $response->assertStatus(201);
    }
}
