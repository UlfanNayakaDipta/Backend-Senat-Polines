<?php

namespace Tests\Feature;

use App\Models\AnggotaRapat;
use App\Models\JenisRapat;
use App\Models\Kegiatan;
use App\Models\Rapat;
use App\Models\SuaraVoting;
use App\Models\User;
use App\Models\Voting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VotingRetentionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: User, 2: JenisRapat, 3: Kegiatan, 4: Rapat, 5: Voting} */
    private function fixture(bool $withBallot): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $voter = User::factory()->create(['role' => 'anggota']);
        $jenis = JenisRapat::create(['nama_jenis' => 'Pleno Retensi']);
        $kegiatan = Kegiatan::create([
            'id_user' => $admin->id_user, 'nama_kegiatan' => 'Kegiatan Retensi',
            'tanggal_kegiatan' => now()->toDateString(),
        ]);
        $rapat = Rapat::create([
            'id_user' => $admin->id_user, 'id_jenis_rapat' => $jenis->id_jenis_rapat,
            'id_kegiatan' => $kegiatan->id_kegiatan,
            'nama_agenda' => 'Retensi', 'tanggal' => now()->toDateString(),
            'waktu' => '09:00', 'tempat' => 'Senat', 'status' => 'berlangsung',
        ]);
        $voting = Voting::create([
            'id_rapat' => $rapat->id_rapat, 'judul_voting' => 'Retensi',
            'status' => 'dibuka', 'waktu_mulai' => now()->subMinute(),
            'waktu_selesai' => now()->addHour(),
        ]);
        $opsi = $voting->opsiVotings()->create(['nama_opsi' => 'Setuju']);
        AnggotaRapat::create([
            'id_rapat' => $rapat->id_rapat, 'id_user' => $voter->id_user,
            'status_kehadiran' => 'hadir',
        ]);
        if ($withBallot) {
            SuaraVoting::create([
                'id_voting' => $voting->id_voting, 'id_opsi' => $opsi->id_opsi,
                'id_user' => $voter->id_user,
            ]);
        }

        return [$admin, $voter, $jenis, $kegiatan, $rapat, $voting];
    }

    public function test_parents_with_ballot_history_cannot_be_deleted(): void
    {
        [$admin, $voter, $jenis, $kegiatan, $rapat] = $this->fixture(true);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/rapat/{$rapat->id_rapat}")->assertStatus(422);
        $this->deleteJson("/api/user/{$voter->id_user}")->assertStatus(422);
        $this->deleteJson("/api/user/{$admin->id_user}")->assertStatus(422);
        $this->deleteJson("/api/jenis-rapat/{$jenis->id_jenis_rapat}")->assertStatus(422);
        $this->deleteJson("/api/kegiatan/{$kegiatan->id_kegiatan}")->assertStatus(422);

        $this->assertDatabaseCount('suara_votings', 1);
        $this->assertDatabaseCount('votings', 1);
        $this->assertDatabaseCount('rapats', 1);
    }

    public function test_parents_without_ballots_can_still_be_deleted(): void
    {
        [$admin, $voter, $jenis, $kegiatan] = $this->fixture(false);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/kegiatan/{$kegiatan->id_kegiatan}")->assertNoContent();
        $this->deleteJson("/api/jenis-rapat/{$jenis->id_jenis_rapat}")->assertNoContent();
        $this->deleteJson("/api/user/{$voter->id_user}")->assertNoContent();
        $this->deleteJson("/api/user/{$admin->id_user}")->assertNoContent();

        $this->assertDatabaseCount('rapats', 0);
        $this->assertDatabaseCount('votings', 0);
    }
}
