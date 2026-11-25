<?php

namespace Tests\Feature;

use App\Models\AnggotaRapat;
use App\Models\JenisRapat;
use App\Models\OpsiVoting;
use App\Models\Rapat;
use App\Models\SuaraVoting;
use App\Models\User;
use App\Models\Voting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VotingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): Voting
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);
        $jenis = JenisRapat::create(['nama_jenis' => 'Pleno']);
        $rapat = Rapat::create([
            'id_user' => $user->id_user, 'id_jenis_rapat' => $jenis->id_jenis_rapat,
            'nama_agenda' => 'Lifecycle', 'tanggal' => now()->toDateString(),
            'waktu' => '09:00', 'tempat' => 'Senat', 'status' => 'berlangsung',
        ]);

        return Voting::create(['id_rapat' => $rapat->id_rapat, 'judul_voting' => 'Lifecycle', 'status' => 'draft']);
    }

    public function test_status_writes_and_material_edits_are_guarded(): void
    {
        $voting = $this->fixture();
        $url = '/api/voting/'.$voting->id_voting;
        foreach (['dibuka', null, 'draft'] as $status) {
            $this->putJson($url, ['status' => $status])->assertUnprocessable();
        }
        foreach (['active', 'berlangsung', 'closed', 'bogus'] as $status) {
            $this->postJson('/api/voting', ['id_rapat' => $voting->id_rapat, 'judul_voting' => 'Invalid', 'status' => $status])->assertUnprocessable();
        }
        $this->putJson($url, ['waktu_mulai' => now()->addHour()->toISOString(), 'waktu_selesai' => now()->addHours(2)->toISOString()])->assertOk();
        $this->putJson($url, ['waktu_mulai' => now()->addHours(3)->toISOString()])->assertUnprocessable();
        $this->putJson($url, ['waktu_selesai' => now()->toISOString()])->assertUnprocessable();
        $this->postJson($url.'/buka')->assertOk();
        $this->assertNotNull($voting->fresh()->waktu_selesai);
        $this->assertTrue($voting->fresh()->waktu_mulai->isFuture());
        foreach (['judul_voting' => 'Changed', 'deskripsi' => 'Changed', 'id_rapat' => $voting->id_rapat,
            'waktu_mulai' => null, 'waktu_selesai' => null, 'opsi' => ['Changed']] as $key => $value) {
            $this->putJson($url, [$key => $value])->assertUnprocessable();
        }
        $this->deleteJson($url)->assertUnprocessable();
        $this->postJson($url.'/tutup')->assertOk();
        $this->postJson($url.'/buka')->assertUnprocessable();
        $this->deleteJson($url)->assertUnprocessable();
    }

    public function test_expired_and_balloted_sessions_are_retained_and_cannot_reopen(): void
    {
        $voting = $this->fixture();
        $opsi = $voting->opsiVotings()->create(['nama_opsi' => 'Yes']);
        $ballot = SuaraVoting::create(['id_voting' => $voting->id_voting, 'id_opsi' => $opsi->id_opsi, 'id_user' => auth()->id()]);
        $url = '/api/voting/'.$voting->id_voting;
        $this->putJson($url, ['judul_voting' => 'Changed'])->assertUnprocessable();
        $this->postJson($url.'/buka')->assertUnprocessable();
        $this->deleteJson($url)->assertUnprocessable();
        $this->assertDatabaseHas('suara_votings', ['id_suara' => $ballot->id_suara]);
        $voting->update(['status' => 'dibuka', 'waktu_mulai' => now()->subHour(), 'waktu_selesai' => now()->subMinute()]);
        $this->postJson($url.'/buka')->assertUnprocessable();
        $this->assertTrue($voting->fresh()->waktu_selesai->isPast());
        $empty = $this->fixture();
        $empty->opsiVotings()->create(['nama_opsi' => 'Yes']);
        $this->deleteJson('/api/voting/'.$empty->id_voting)->assertNoContent();
        $this->assertDatabaseMissing('votings', ['id_voting' => $empty->id_voting]);
    }

    public function test_options_freeze_and_archived_ballots_survive(): void
    {
        $voting = $this->fixture();
        $other = $this->fixture();
        $opsi = $voting->opsiVotings()->create(['nama_opsi' => 'Keep']);
        $url = '/api/opsi-voting/'.$opsi->id_opsi;
        $this->putJson($url, ['nama_opsi' => 'Renamed'])->assertOk();
        $this->putJson($url, ['id_voting' => $other->id_voting])->assertOk();
        $this->assertSame($other->id_voting, $opsi->fresh()->id_voting);
        $ballot = SuaraVoting::create(['id_voting' => $other->id_voting, 'id_opsi' => $opsi->id_opsi, 'id_user' => auth()->id()]);
        $this->putJson($url, ['nama_opsi' => 'Frozen'])->assertUnprocessable();
        $this->putJson($url, ['id_voting' => $voting->id_voting])->assertUnprocessable();
        $this->deleteJson($url)->assertUnprocessable();
        $this->postJson('/api/opsi-voting', ['id_voting' => $other->id_voting, 'nama_opsi' => 'New'])->assertUnprocessable();
        $this->assertDatabaseHas('opsi_votings', ['id_opsi' => $opsi->id_opsi, 'nama_opsi' => 'Renamed']);
        $this->assertDatabaseHas('suara_votings', ['id_suara' => $ballot->id_suara]);
        $empty = $this->fixture();
        $fresh = $empty->opsiVotings()->create(['nama_opsi' => 'Fresh']);
        $this->deleteJson('/api/opsi-voting/'.$fresh->id_opsi)->assertNoContent();
    }

    public function test_lists_filter_windows_and_paginate_safely(): void
    {
        $voting = $this->fixture();
        $voting->update(['status' => 'dibuka', 'waktu_mulai' => now()->subMinute(), 'waktu_selesai' => now()->addMinute()]);
        $future = $this->fixture();
        $future->update(['status' => 'dibuka', 'waktu_mulai' => now()->addHour()]);
        $expired = $this->fixture();
        $expired->update(['status' => 'aktif', 'waktu_mulai' => now()->subHour(), 'waktu_selesai' => now()->subMinute()]);
        $legacy = $this->fixture();
        $legacy->update(['status' => 'selesai']);
        $ids = $this->getJson('/api/voting?active=1')->assertOk()->json('data.*.id_voting');
        $this->assertSame([$voting->id_voting], $ids);
        $this->assertSame('ditutup', $this->getJson('/api/voting/'.$legacy->id_voting)->assertOk()->json('status'));
        $this->assertSame([$legacy->id_voting], $this->getJson('/api/voting?status=selesai')->json('data.*.id_voting'));
        for ($i = 0; $i < 16; $i++) { $this->fixture(); }
        $this->assertCount(15, $this->getJson('/api/voting')->assertOk()->json('data'));
        $all = $this->getJson('/api/voting?all=1')->assertOk()->json();
        $this->assertArrayHasKey('data', $all);
        $this->assertGreaterThan(15, count($all['data']));
    }

    public function test_expire_command_closes_only_expired_open_and_keeps_finish(): void
    {
        $voting = $this->fixture();
        $voting->update(['status' => 'dibuka', 'waktu_mulai' => now()->subHour(), 'waktu_selesai' => now()->subMinute()]);
        $finish = $voting->fresh()->waktu_selesai;
        $future = $this->fixture();
        $future->update(['status' => 'dibuka', 'waktu_mulai' => now()->subMinute(), 'waktu_selesai' => now()->addHour()]);
        $this->artisan('voting:expire')->assertExitCode(0);
        $this->assertSame('ditutup', $voting->fresh()->status);
        $this->assertTrue($finish->equalTo($voting->fresh()->waktu_selesai));
        $this->assertSame('dibuka', $future->fresh()->status);
    }

    public function test_create_is_atomic_and_serialized_even_with_no_votings(): void
    {
        $voting = $this->fixture();
        $payload = ['id_rapat' => $voting->id_rapat, 'judul_voting' => 'Atomic', 'status' => 'dibuka', 'opsi' => ['One', 'Two']];
        $voting->delete();
        $level = DB::transactionLevel();
        Voting::creating(function () use ($level): void {
            $this->assertGreaterThan($level, DB::transactionLevel());
            $lock = Cache::store('file')->lock('evoting-write', 30);
            $held = $lock->get();
            if ($held) { $lock->release(); }
            $this->assertFalse($held);
        });
        OpsiVoting::creating(fn () => throw new \RuntimeException('option failure'));
        try {
            $this->postJson('/api/voting', $payload)->assertStatus(500);
            $this->assertDatabaseCount('votings', 0);
        } finally {
            Voting::flushEventListeners();
            OpsiVoting::flushEventListeners();
        }
        $first = $this->postJson('/api/voting', $payload)->assertCreated()->json('id_voting');
        $this->postJson('/api/voting', $payload)->assertCreated();
        $this->assertSame(1, Voting::where('status', 'dibuka')->count());
        $this->assertSame('ditutup', Voting::find($first)->status);
    }
}
