<?php

namespace Tests\Feature;

use App\Models\AnggotaRapat;
use App\Models\JenisRapat;
use App\Models\Rapat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PresensiSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function rapat(array $attributes = []): Rapat
    {
        $owner = User::factory()->create(['role' => 'admin']);
        $jenis = JenisRapat::create(['nama_jenis' => 'Pleno']);

        return Rapat::create(array_merge([
            'id_user' => $owner->id_user,
            'id_jenis_rapat' => $jenis->id_jenis_rapat,
            'nama_agenda' => 'Presensi Security',
            'tanggal' => now()->toDateString(),
            'waktu' => '09:00',
            'tempat' => 'Ruang Senat',
            'status' => 'Sedang Berlangsung',
            'token_qr' => 'token-valid-123',
            'kode_sesi' => 'ABC123',
            'is_presensi_open' => true,
            'qr_expires_at' => now()->addHour(),
            'is_kuorum_locked' => false,
        ], $attributes));
    }

    public function test_self_enrollment_is_blocked_and_token_identity_wins(): void
    {
        $rapat = $this->rapat();
        $actor = User::factory()->create(['role' => 'anggota']);
        $other = User::factory()->create(['role' => 'anggota']);
        AnggotaRapat::create(['id_rapat' => $rapat->id_rapat, 'id_user' => $other->id_user, 'status_kehadiran' => 'menunggu']);
        $before = AnggotaRapat::count();
        $this->postJson('/api/rapat/presensi', ['token_qr' => 'token-valid-123'])->assertUnauthorized();
        $this->assertSame($before, AnggotaRapat::count());

        Sanctum::actingAs($actor);
        $this->postJson('/api/rapat/presensi', ['token_qr' => 'token-valid-123'])
            ->assertForbidden()->assertJsonPath('success', false);
        $this->postJson('/api/rapat/presensi', [
            'token_qr' => 'token-valid-123', 'id_rapat' => $rapat->id_rapat,
            'id_user' => $other->id_user,
        ])->assertForbidden()->assertJsonPath('success', false);
        $this->assertSame($before, AnggotaRapat::count());
        $this->assertDatabaseHas('anggota_rapats', [
            'id_user' => $other->id_user, 'status_kehadiran' => 'menunggu', 'waktu_presensi' => null,
        ]);
    }

    public function test_invalid_credential_never_falls_back_to_id_rapat(): void
    {
        $rapat = $this->rapat();
        $actor = User::factory()->create(['role' => 'anggota']);
        $invitation = AnggotaRapat::create([
            'id_rapat' => $rapat->id_rapat, 'id_user' => $actor->id_user, 'status_kehadiran' => 'izin',
        ]);
        Sanctum::actingAs($actor);
        foreach ([
            ['token_qr' => 'wrong-token', 'id_rapat' => $rapat->id_rapat],
            ['kode_sesi' => 'WRONG1', 'id_rapat' => $rapat->id_rapat],
            ['token_qr' => 'wrong-token', 'kode_sesi' => 'WRONG1', 'id_rapat' => $rapat->id_rapat],
        ] as $payload) {
            $this->postJson('/api/rapat/presensi', $payload)->assertNotFound()->assertJsonPath('success', false);
        }
        $this->assertDatabaseCount('anggota_rapats', 1);
        $this->assertDatabaseHas('anggota_rapats', [
            'id_undangan' => $invitation->id_undangan, 'status_kehadiran' => 'izin', 'waktu_presensi' => null,
        ]);
        $this->postJson('/api/rapat/presensi', ['token_qr' => 'token-valid-123'])->assertOk();
        $this->assertDatabaseHas('anggota_rapats', ['id_undangan' => $invitation->id_undangan, 'status_kehadiran' => 'hadir']);
    }

    public function test_id_rapat_path_without_credentials_requires_existing_invitation(): void
    {
        $rapat = $this->rapat();
        $actor = User::factory()->create(['role' => 'anggota']);
        Sanctum::actingAs($actor);
        AnggotaRapat::create(['id_rapat' => $rapat->id_rapat, 'id_user' => $actor->id_user, 'status_kehadiran' => 'menunggu']);
        $this->postJson('/api/rapat/presensi', ['id_rapat' => $rapat->id_rapat])
            ->assertOk()->assertJsonPath('data.status_kehadiran', 'hadir')->assertJsonPath('data.metode_presensi', 'token_input');
        $this->assertDatabaseCount('anggota_rapats', 1);
    }

    public function test_closed_and_expired_credential_behavior_is_preserved(): void
    {
        $actor = User::factory()->create(['role' => 'anggota']);
        Sanctum::actingAs($actor);
        $closed = $this->rapat(['token_qr' => 'closed-token', 'is_presensi_open' => false]);
        AnggotaRapat::create(['id_rapat' => $closed->id_rapat, 'id_user' => $actor->id_user, 'status_kehadiran' => 'menunggu']);
        $this->postJson('/api/rapat/presensi', ['token_qr' => 'closed-token'])
            ->assertUnprocessable()->assertJsonPath('success', false);
        $expired = $this->rapat(['token_qr' => 'expired-token', 'qr_expires_at' => now()->subMinute()]);
        AnggotaRapat::create(['id_rapat' => $expired->id_rapat, 'id_user' => $actor->id_user, 'status_kehadiran' => 'menunggu']);
        $this->postJson('/api/rapat/presensi', ['token_qr' => 'expired-token'])
            ->assertUnprocessable()->assertJsonPath('success', false);
        $this->assertDatabaseMissing('anggota_rapats', ['status_kehadiran' => 'hadir']);
    }

    public function test_happy_path_qr_scan_and_token_input(): void
    {
        $rapat = $this->rapat();
        $byQr = User::factory()->create(['role' => 'anggota']);
        $byCode = User::factory()->create(['role' => 'anggota']);
        foreach ([$byQr, $byCode] as $user) {
            AnggotaRapat::create(['id_rapat' => $rapat->id_rapat, 'id_user' => $user->id_user, 'status_kehadiran' => 'menunggu']);
        }
        Sanctum::actingAs($byQr);
        $this->postJson('/api/rapat/presensi', ['token_qr' => 'token-valid-123'])
            ->assertOk()->assertJsonPath('data.metode_presensi', 'qr_scan');
        Sanctum::actingAs($byCode);
        $this->postJson('/api/rapat/presensi', ['kode_sesi' => 'abc123'])
            ->assertOk()->assertJsonPath('data.metode_presensi', 'token_input');
        $this->assertSame(2, AnggotaRapat::where('status_kehadiran', 'hadir')->count());
    }

    public function test_management_routes_require_privileged_roles(): void
    {
        $rapat = $this->rapat();
        $member = User::factory()->create(['role' => 'anggota']);
        AnggotaRapat::create(['id_rapat' => $rapat->id_rapat, 'id_user' => $member->id_user, 'status_kehadiran' => 'menunggu']);
        $routes = [
            ['post', "/api/rapat/{$rapat->id_rapat}/generate-qr", []],
            ['post', "/api/rapat/{$rapat->id_rapat}/freeze-presensi", []],
            ['put', "/api/rapat/{$rapat->id_rapat}/anggota/{$member->id_user}/validasi", ['status_kehadiran' => 'hadir']],
        ];
        foreach ($routes as [$method, $url, $payload]) {
            $this->json($method, $url, $payload)->assertUnauthorized();
        }
        foreach (['anggota', 'dosen', 'mahasiswa'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            Sanctum::actingAs($user);
            foreach ($routes as [$method, $url, $payload]) {
                $this->json($method, $url, $payload)->assertForbidden()->assertJsonPath('status', 'error');
            }
        }
        foreach (['admin', 'ketua_senat', 'sekretaris'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            Sanctum::actingAs($user);
            $this->postJson("/api/rapat/{$rapat->id_rapat}/generate-qr")->assertOk();
            $this->putJson("/api/rapat/{$rapat->id_rapat}/anggota/{$member->id_user}/validasi", [
                'status_kehadiran' => 'izin',
            ])->assertOk();
            $this->postJson("/api/rapat/{$rapat->id_rapat}/freeze-presensi")->assertOk();
            $rapat->update([
                'is_presensi_open' => true, 'is_kuorum_locked' => false,
                'qr_expires_at' => now()->addHour(),
            ]);
        }
    }

    public function test_public_payloads_omit_presensi_secrets_but_privileged_calls_keep_them(): void
    {
        $rapat = $this->rapat();
        $publicActive = $this->assertOmitted($this->getJson('/api/rapat/active')->assertOk()->json('data'));
        $publicLive = $this->assertOmitted($this->getJson("/api/rapat/{$rapat->id_rapat}/live-attendance")->assertOk()->json('data'));
        $this->assertSame($rapat->id_rapat, $publicActive['id_rapat']);
        $this->assertSame($rapat->id_rapat, $publicLive['id_rapat']);
        $tokenFor = fn (string $role): string => 'Bearer '
            .User::factory()->create(['role' => $role])->createToken('t')->plainTextToken;
        $memberHeaders = ['Authorization' => $tokenFor('anggota')];
        $this->assertOmitted($this->withHeaders($memberHeaders)
            ->getJson('/api/rapat/active')->assertOk()->json('data'));
        $this->assertOmitted($this->withHeaders($memberHeaders)
            ->getJson("/api/rapat/{$rapat->id_rapat}/live-attendance")->assertOk()->json('data'));
        foreach (['admin', 'ketua_senat', 'sekretaris'] as $role) {
            $this->app['auth']->forgetGuards();
            $headers = ['Authorization' => $tokenFor($role)];
            $active = $this->withHeaders($headers)->getJson('/api/rapat/active')->assertOk()->json('data');
            $live = $this->withHeaders($headers)
                ->getJson("/api/rapat/{$rapat->id_rapat}/live-attendance")->assertOk()->json('data');
            $this->assertSame('token-valid-123', $active['token_qr']);
            $this->assertSame('ABC123', $active['kode_sesi']);
            $this->assertSame('token-valid-123', $live['token_qr']);
            $this->assertSame('ABC123', $live['kode_sesi']);
            $this->assertArrayHasKey('anggota', $live);
        }
    }

    private function assertOmitted(array $data): array
    {
        $this->assertArrayNotHasKey('token_qr', $data);
        $this->assertArrayNotHasKey('kode_sesi', $data);

        return $data;
    }

    public function test_manual_validation_is_rejected_after_freeze(): void
    {
        $rapat = $this->rapat(['is_presensi_open' => false, 'is_kuorum_locked' => true]);
        $member = User::factory()->create(['role' => 'anggota']);
        $invitation = AnggotaRapat::create([
            'id_rapat' => $rapat->id_rapat, 'id_user' => $member->id_user, 'status_kehadiran' => 'menunggu',
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->putJson("/api/rapat/{$rapat->id_rapat}/anggota/{$member->id_user}/validasi", ['status_kehadiran' => 'hadir'])
            ->assertUnprocessable()->assertJsonPath('success', false);
        $this->assertDatabaseHas('anggota_rapats', [
            'id_undangan' => $invitation->id_undangan, 'status_kehadiran' => 'menunggu', 'is_edited_by_admin' => false,
        ]);
    }
}
