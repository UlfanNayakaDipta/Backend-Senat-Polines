<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImpersonateTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_impersonate_user(): void
    {
        $member = User::factory()->create(['role' => 'anggota']);

        $response = $this->postJson("/api/admin/impersonate/{$member->id_user}");
        $response->assertStatus(401);
    }

    public function test_anggota_cannot_impersonate_another_user(): void
    {
        $memberA = User::factory()->create(['role' => 'anggota']);
        $memberB = User::factory()->create(['role' => 'anggota']);

        Sanctum::actingAs($memberA);

        $response = $this->postJson("/api/admin/impersonate/{$memberB->id_user}");
        $response->assertStatus(403);
    }

    public function test_admin_cannot_impersonate_self(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/impersonate/{$admin->id_user}");
        $response->assertStatus(422)
            ->assertJson([
                'status'  => 'error',
                'message' => 'Tidak dapat melakukan impersonasi ke akun sendiri.',
            ]);
    }

    public function test_admin_cannot_impersonate_non_existent_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/impersonate/99999');
        $response->assertStatus(404);
    }

    public function test_admin_can_impersonate_member_and_receive_valid_token(): void
    {
        $admin = User::factory()->create([
            'nama' => 'Administrator',
            'role' => 'admin',
        ]);
        $member = User::factory()->create([
            'nama'    => 'Dr. Sukamto',
            'email'   => 'sukamto@senat.test',
            'role'    => 'anggota',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/impersonate/{$member->id_user}");

        $response->assertOk()
            ->assertJson([
                'status'     => 'success',
                'message'    => 'Impersonasi berhasil dimulai.',
                'token_type' => 'Bearer',
                'user'       => [
                    'id_user' => $member->id_user,
                    'nama'    => 'Dr. Sukamto',
                    'role'    => 'anggota',
                ],
                'impersonator' => [
                    'id_user' => $admin->id_user,
                    'nama'    => 'Administrator',
                    'role'    => 'admin',
                ],
            ]);

        $token = $response->json('token');
        $this->assertNotEmpty($token);

        // Reset acting user from Sanctum to test raw Bearer token authentication
        auth()->forgetGuards();

        // Verify the issued token works to authenticate as the member
        $meResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me');

        $meResponse->assertOk()
            ->assertJson([
                'status' => 'success',
                'user'   => [
                    'id_user' => $member->id_user,
                    'nama'    => 'Dr. Sukamto',
                ],
            ]);
    }
}
