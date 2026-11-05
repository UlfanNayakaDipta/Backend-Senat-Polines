<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthSanctumTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receive_sanctum_token(): void
    {
        $user = User::factory()->create([
            'email'    => 'testuser@senat.test',
            'nim_nip'  => '198701012020',
            'password' => Hash::make('secret123'),
            'role'     => 'anggota',
        ]);

        $response = $this->postJson('/api/login', [
            'identifier' => 'testuser@senat.test',
            'password'   => 'secret123',
        ]);

        $response->assertOk()
            ->assertJson([
                'status'     => 'success',
                'token_type' => 'Bearer',
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'token',
                'token_type',
                'user' => ['id_user', 'nim_nip', 'nama', 'email', 'role'],
            ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id'   => $user->id_user,
            'tokenable_type' => User::class,
            'name'           => 'auth_token',
        ]);
    }

    public function test_user_login_fails_with_invalid_password(): void
    {
        User::factory()->create([
            'email'    => 'testuser@senat.test',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'identifier' => 'testuser@senat.test',
            'password'   => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status'  => 'error',
                'message' => 'NIP/Email atau kata sandi yang Anda masukkan salah.',
            ]);
    }
}
