<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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

    public function test_google_login_rejects_unverified_or_spoofed_requests(): void
    {
        config()->set('services.google.sso_secret', 'test-sso-secret');

        User::factory()->create([
            'email' => 'admin@senat.test',
            'role'  => 'admin',
        ]);

        // No headers and no tokens
        $this->postJson('/api/login/google', ['email' => 'admin@senat.test'])
            ->assertStatus(401);

        // Tampered HMAC signature
        $this->withHeaders([
            'X-Google-Bridge-Timestamp' => (string) time(),
            'X-Google-Bridge-Signature' => 'invalid-signature',
        ])->postJson('/api/login/google', ['email' => 'admin@senat.test'])
            ->assertStatus(401);
    }

    public function test_google_login_rejects_non_polines_domains(): void
    {
        $secret = 'test-sso-secret';
        config()->set('services.google.sso_secret', $secret);
        $ts = (string) time();

        foreach (['asing@gmail.com', 'spoof@fakepolines.ac.id', 'bad@polines.ac.id.evil.com'] as $email) {
            $sig = hash_hmac('sha256', "{$email}|{$ts}", $secret);

            $response = $this->withHeaders([
                'X-Google-Bridge-Timestamp' => $ts,
                'X-Google-Bridge-Signature' => $sig,
            ])->postJson('/api/login/google', ['email' => $email]);

            $response->assertStatus(403)
                ->assertJsonPath('status', 'error');

            $this->assertDatabaseMissing('users', ['email' => $email]);
        }
    }

    public function test_google_login_auto_provisions_polines_domain_and_subdomain_accounts(): void
    {
        $secret = 'test-sso-secret';
        config()->set('services.google.sso_secret', $secret);
        $ts = (string) time();

        // Main domain user not yet in DB -> auto-created as anggota
        $mainEmail = 'dosen.baru@polines.ac.id';
        $sigMain = hash_hmac('sha256', "{$mainEmail}|{$ts}", $secret);
        $resMain = $this->withHeaders([
            'X-Google-Bridge-Timestamp' => $ts,
            'X-Google-Bridge-Signature' => $sigMain,
        ])->postJson('/api/login/google', [
            'email' => $mainEmail,
            'name'  => 'Dr. Dosen Baru',
        ]);

        $resMain->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('user.email', $mainEmail)
            ->assertJsonPath('user.nama', 'Dr. Dosen Baru')
            ->assertJsonPath('user.role', 'anggota');

        $this->assertDatabaseHas('users', [
            'email' => $mainEmail,
            'role'  => 'anggota',
        ]);

        // Official subdomain user not yet in DB -> auto-created as anggota
        $subEmail = 'mahasiswa.test@mhs.polines.ac.id';
        $sigSub = hash_hmac('sha256', "{$subEmail}|{$ts}", $secret);
        $this->withHeaders([
            'X-Google-Bridge-Timestamp' => $ts,
            'X-Google-Bridge-Signature' => $sigSub,
        ])->postJson('/api/login/google', ['email' => $subEmail])
            ->assertOk()
            ->assertJsonPath('user.role', 'anggota');
    }

    public function test_google_login_preserves_existing_user_role_with_bridge_signature(): void
    {
        $secret = 'test-sso-secret';
        config()->set('services.google.sso_secret', $secret);

        $user = User::factory()->create([
            'email' => 'Dosen.Senat@polines.ac.id',
            'role'  => 'admin',
        ]);

        $email = 'dosen.senat@polines.ac.id';
        $ts = (string) time();
        $sig = hash_hmac('sha256', "{$email}|{$ts}", $secret);

        $response = $this->withHeaders([
            'X-Google-Bridge-Timestamp' => $ts,
            'X-Google-Bridge-Signature' => $sig,
        ])->postJson('/api/login/google', ['email' => $email]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('user.id_user', $user->id_user)
            ->assertJsonPath('user.role', 'admin');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id_user,
            'name' => 'auth_token_google',
        ]);
    }

    public function test_google_login_verifies_google_id_token_when_bridge_not_used(): void
    {
        config()->set('services.google.sso_secret', null);
        config()->set('services.google.client_id', 'expected-google-client-id');

        $user = User::factory()->create([
            'email' => 'ketua@senat.polines.ac.id',
            'role'  => 'admin',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'aud' => 'expected-google-client-id',
                'email' => 'ketua@senat.polines.ac.id',
                'email_verified' => 'true',
                'name' => 'Ketua Senat',
            ], 200),
        ]);

        $response = $this->postJson('/api/login/google', [
            'id_token' => 'mock-google-id-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('user.id_user', $user->id_user)
            ->assertJsonPath('user.role', 'admin');
    }

    public function test_issued_token_expires_after_72_hours(): void
    {
        $user = User::factory()->create([
            'email'    => 'ttl@polines.ac.id',
            'password' => Hash::make('secret123'),
        ]);

        $login = $this->postJson('/api/login', [
            'identifier' => 'ttl@polines.ac.id',
            'password'   => 'secret123',
        ])->assertOk();

        $token = $login->json('token');

        $this->withToken($token)->getJson('/api/me')->assertOk();

        $this->travel(73)->hours();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/me')->assertStatus(401);
    }
}
