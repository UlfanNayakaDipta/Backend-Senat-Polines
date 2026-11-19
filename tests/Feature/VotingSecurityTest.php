<?php

namespace Tests\Feature;

use App\Models\AnggotaRapat;
use App\Models\JenisRapat;
use App\Models\Rapat;
use App\Models\SuaraVoting;
use App\Models\User;
use App\Models\Voting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VotingSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function votingFixture(): array
    {
        $user = User::factory()->create(['role' => 'anggota']);
        $jenis = JenisRapat::create(['nama_jenis' => 'Pleno']);
        $rapat = Rapat::create([
            'id_user' => $user->id_user, 'id_jenis_rapat' => $jenis->id_jenis_rapat,
            'nama_agenda' => 'Security', 'tanggal' => now()->toDateString(),
            'waktu' => '09:00', 'tempat' => 'Senat', 'status' => 'berlangsung',
        ]);
        $voting = Voting::create([
            'id_rapat' => $rapat->id_rapat, 'judul_voting' => 'Security',
            'status' => 'dibuka', 'waktu_mulai' => now()->subMinute(),
            'waktu_selesai' => now()->addHour(),
        ]);
        $opsi = $voting->opsiVotings()->create(['nama_opsi' => 'Setuju']);
        AnggotaRapat::create([
            'id_rapat' => $rapat->id_rapat, 'id_user' => $user->id_user,
            'status_kehadiran' => 'hadir',
        ]);

        return [$user, $voting, $opsi];
    }

    public function test_only_invited_present_senators_can_vote(): void
    {
        [$user, $voting, $opsi] = $this->votingFixture();
        $payload = ['id_voting' => $voting->id_voting, 'id_opsi' => $opsi->id_opsi];
        $this->postJson('/api/suara-voting', $payload)->assertUnauthorized();
        Sanctum::actingAs($user);
        foreach (['admin', 'dosen', 'mahasiswa'] as $role) {
            $user->update(['role' => $role]);
            $this->postJson('/api/suara-voting', $payload)->assertForbidden();
        }
        $user->update(['role' => 'anggota']);
        foreach ([null, 'izin', 'alpa', 'Hadir'] as $status) {
            AnggotaRapat::where('id_user', $user->id_user)->update([
                'status_kehadiran' => $status, 'waktu_presensi' => now(),
            ]);
            $this->postJson('/api/suara-voting', $payload)->assertForbidden();
        }
        AnggotaRapat::where('id_user', $user->id_user)->delete();
        $this->postJson('/api/suara-voting', $payload)->assertForbidden();
        $this->assertDatabaseCount('suara_votings', 0);
        foreach (['anggota', 'ketua_senat', 'sekretaris'] as $role) {
            $senator = User::factory()->create(['role' => $role]);
            AnggotaRapat::create([
                'id_rapat' => $voting->id_rapat, 'id_user' => $senator->id_user,
                'status_kehadiran' => 'hadir',
            ]);
            Sanctum::actingAs($senator);
            $this->postJson('/api/suara-voting', $payload)->assertCreated();
        }
    }

    public function test_public_responses_and_loaded_models_never_serialize_ballots(): void
    {
        [$user, $voting, $opsi] = $this->votingFixture();
        SuaraVoting::create([
            'id_voting' => $voting->id_voting, 'id_opsi' => $opsi->id_opsi,
            'id_user' => $user->id_user,
        ]);
        $assertSafe = function (array $data) use (&$assertSafe): void {
            foreach ($data as $key => $value) {
                $this->assertNotContains($key, ['suara_votings', 'suaraVotings', 'id_suara', 'waktu_vote']);
                if (is_array($value)) {
                    $assertSafe($value);
                }
            }
        };
        foreach (['/api/voting', "/api/voting/{$voting->id_voting}", "/api/voting/{$voting->id_voting}/rekap",
            '/api/opsi-voting', "/api/opsi-voting/{$opsi->id_opsi}"] as $url) {
            $assertSafe($this->getJson($url)->assertOk()->json());
        }
        $assertSafe($voting->load('suaraVotings', 'opsiVotings.suaraVotings', 'rapat.votings.suaraVotings')->toArray());
        $assertSafe($opsi->load('suaraVotings', 'voting.suaraVotings')->toArray());
        $this->assertSame(1, $voting->total_suara);
        $this->assertSame(1, $opsi->jumlah_suara);
    }

    public function test_my_ballot_exact_contract_uses_token_identity_and_explains_ineligibility(): void
    {
        [$user, $voting, $opsi] = $this->votingFixture();
        $url = "/api/voting/{$voting->id_voting}/my-ballot";
        $this->getJson($url)->assertUnauthorized();
        $other = User::factory()->create();
        SuaraVoting::create([
            'id_voting' => $voting->id_voting, 'id_opsi' => $opsi->id_opsi,
            'id_user' => $other->id_user, 'waktu_vote' => now(),
        ]);
        Sanctum::actingAs($user);
        $this->json('GET', $url.'?id_user='.$other->id_user, ['id_user' => $other->id_user])
            ->assertOk()->assertExactJson([
                'has_voted' => false, 'my_ballot' => null, 'eligible' => true,
                'is_invited' => true, 'is_present' => true,
            ]);
        $ballot = SuaraVoting::create([
            'id_voting' => $voting->id_voting, 'id_opsi' => $opsi->id_opsi,
            'id_user' => $user->id_user, 'waktu_vote' => now(),
        ]);
        $expected = [
            'has_voted' => true,
            'my_ballot' => ['id_opsi' => (int) $opsi->id_opsi, 'nama_opsi' => 'Setuju',
                'waktu_vote' => $ballot->waktu_vote->toISOString()],
            'eligible' => true, 'is_invited' => true, 'is_present' => true,
        ];
        $this->getJson($url)->assertOk()->assertExactJson($expected);
        foreach (['admin', 'dosen', 'mahasiswa'] as $role) {
            $user->update(['role' => $role]);
            $expected['eligible'] = false;
            $this->getJson($url)->assertOk()->assertExactJson($expected);
        }
        $user->update(['role' => 'anggota']);
        foreach ([null, 'izin', 'alpa'] as $status) {
            AnggotaRapat::where('id_user', $user->id_user)->update([
                'status_kehadiran' => $status, 'waktu_presensi' => now(),
            ]);
            $expected['is_present'] = false;
            $this->getJson($url)->assertOk()->assertExactJson($expected);
        }
        AnggotaRapat::where('id_user', $user->id_user)->delete();
        $expected['is_invited'] = false;
        $this->getJson($url)->assertOk()->assertExactJson($expected);
        $this->getJson('/api/voting/999999/my-ballot')->assertNotFound();
    }

    public function test_session_window_is_start_inclusive_and_finish_exclusive(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$user, $voting, $opsi] = $this->votingFixture();
        Sanctum::actingAs($user);
        $url = "/api/voting/{$voting->id_voting}/my-ballot";
        $payload = ['id_voting' => $voting->id_voting, 'id_opsi' => $opsi->id_opsi];
        foreach ([
            ['status' => 'draft', 'waktu_mulai' => now()->subMinute(), 'waktu_selesai' => null],
            ['status' => 'ditutup', 'waktu_mulai' => now()->subMinute(), 'waktu_selesai' => null],
            ['status' => 'dibuka', 'waktu_mulai' => now()->addSecond(), 'waktu_selesai' => null],
            ['status' => 'dibuka', 'waktu_mulai' => now()->subMinute(), 'waktu_selesai' => now()],
            ['status' => 'dibuka', 'waktu_mulai' => now()->subMinute(), 'waktu_selesai' => now()->subSecond()],
        ] as $state) {
            $voting->update($state);
            $this->getJson($url)->assertOk()->assertJsonPath('eligible', false);
            $this->postJson('/api/suara-voting', $payload)->assertUnprocessable();
        }
        $voting->update(['status' => 'dibuka', 'waktu_mulai' => now(), 'waktu_selesai' => now()->addSecond()]);
        $this->getJson($url)->assertOk()->assertJsonPath('eligible', true);
        $this->postJson('/api/suara-voting', $payload)->assertCreated();
        $this->travelBack();
    }

    public function test_ballots_cannot_be_deleted_or_updated(): void
    {
        [$user, $voting, $opsi] = $this->votingFixture();
        $ballot = SuaraVoting::create([
            'id_voting' => $voting->id_voting, 'id_opsi' => $opsi->id_opsi,
            'id_user' => $user->id_user,
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->deleteJson('/api/suara-voting/'.$ballot->id_suara)->assertStatus(405);
        $this->putJson('/api/suara-voting/'.$ballot->id_suara, ['id_opsi' => 999])->assertStatus(405);
        $this->assertDatabaseHas('suara_votings', ['id_suara' => $ballot->id_suara, 'id_opsi' => $opsi->id_opsi]);
    }

    public function test_vote_holds_shared_lock_and_transaction_and_reloads_parent(): void
    {
        [$user, $voting, $opsi] = $this->votingFixture();
        Sanctum::actingAs($user);
        $level = \Illuminate\Support\Facades\DB::transactionLevel();
        SuaraVoting::creating(function () use ($level): void {
            $this->assertGreaterThan($level, \Illuminate\Support\Facades\DB::transactionLevel());
            $lock = \Illuminate\Support\Facades\Cache::store('file')->lock('evoting-write', 30);
            $acquired = $lock->get();
            if ($acquired) {
                $lock->release();
            }
            $this->assertFalse($acquired);
        });
        try {
            $this->postJson('/api/suara-voting', [
                'id_voting' => $voting->id_voting, 'id_opsi' => $opsi->id_opsi,
            ])->assertCreated();
        } finally {
            SuaraVoting::flushEventListeners();
        }
    }

    public function test_composite_unique_race_returns_422_and_rolls_back(): void
    {
        [$user, $voting, $opsi] = $this->votingFixture();
        Sanctum::actingAs($user);
        SuaraVoting::creating(function ($ballot): void {
            \Illuminate\Support\Facades\DB::table('suara_votings')->insert($ballot->getAttributes());
        });
        try {
            $this->postJson('/api/suara-voting', [
                'id_voting' => $voting->id_voting, 'id_opsi' => $opsi->id_opsi,
            ])->assertUnprocessable()->assertJson(['message' => 'Anda sudah memberikan suara pada sesi voting ini.']);
            $this->assertDatabaseCount('suara_votings', 0);
        } finally {
            SuaraVoting::flushEventListeners();
        }
    }

    public function test_unrelated_database_errors_are_not_swallowed(): void
    {
        [$user, $voting, $opsi] = $this->votingFixture();
        Sanctum::actingAs($user);
        $this->withoutExceptionHandling();
        SuaraVoting::creating(fn () => \Illuminate\Support\Facades\DB::table('missing_security_table')->insert(['id' => 1]));
        $this->expectException(\Illuminate\Database\QueryException::class);
        try {
            $this->postJson('/api/suara-voting', [
                'id_voting' => $voting->id_voting, 'id_opsi' => $opsi->id_opsi,
            ]);
        } finally {
            SuaraVoting::flushEventListeners();
        }
    }

    public function test_ballot_identity_is_only_the_authenticated_user(): void
    {
        [$user, $voting, $opsi] = $this->votingFixture();
        $other = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/suara-voting', [
            'id_voting' => $voting->id_voting, 'id_opsi' => $opsi->id_opsi,
            'id_user' => $other->id_user,
        ])->assertCreated();
        $this->assertDatabaseHas('suara_votings', ['id_user' => $user->id_user]);
        $this->assertDatabaseMissing('suara_votings', ['id_user' => $other->id_user]);
    }
}
