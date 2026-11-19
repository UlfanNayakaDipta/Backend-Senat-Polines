<?php

namespace Tests\Feature;

use App\Models\AnggotaRapat;
use App\Models\JenisRapat;
use App\Models\Rapat;
use App\Models\SuaraVoting;
use App\Models\User;
use App\Models\Voting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VotingQuorumTest extends TestCase
{
    use RefreshDatabase;

    private function rapat(int $min = 67, ?int $invited = null): Rapat
    {
        $owner = User::factory()->create(['role' => 'admin']);
        $jenis = JenisRapat::create(['nama_jenis' => 'Pleno']);
        $rapat = Rapat::create([
            'id_user' => $owner->id_user, 'id_jenis_rapat' => $jenis->id_jenis_rapat,
            'nama_agenda' => 'Kuorum', 'tanggal' => now()->toDateString(),
            'waktu' => '09:00', 'tempat' => 'Senat', 'status' => 'berlangsung',
            'kuorum_min_persen' => $min,
        ]);
        for ($i = 0; $i < ($invited ?? 0); $i++) {
            AnggotaRapat::create([
                'id_rapat' => $rapat->id_rapat,
                'id_user' => User::factory()->create(['role' => 'anggota'])->id_user,
                'status_kehadiran' => 'menunggu',
            ]);
        }

        return $rapat;
    }

    private function voting(Rapat $rapat, array $opsi = ['Setuju', 'Tolak']): Voting
    {
        $voting = Voting::create([
            'id_rapat' => $rapat->id_rapat, 'judul_voting' => 'Kuorum',
            'status' => 'dibuka', 'waktu_mulai' => now()->subMinute(),
        ]);
        foreach ($opsi as $name) {
            $voting->opsiVotings()->create(['nama_opsi' => $name]);
        }

        return $voting;
    }

    private function vote(Voting $voting, string $opsi, int $count): void
    {
        $idOpsi = $voting->opsiVotings()->where('nama_opsi', $opsi)->value('id_opsi');
        for ($i = 0; $i < $count; $i++) {
            SuaraVoting::create([
                'id_voting' => $voting->id_voting, 'id_opsi' => $idOpsi,
                'id_user' => User::factory()->create()->id_user,
            ]);
        }
    }

    private function rekap(Voting $voting): array
    {
        return $this->getJson("/api/voting/{$voting->id_voting}/rekap")->assertOk()->json();
    }

    public function test_list_and_detail_payloads_include_quorum_block(): void
    {
        $rapat = $this->rapat(67, 3);
        $voting = $this->voting($rapat);
        AnggotaRapat::query()->limit(2)->update(['status_kehadiran' => 'hadir']);

        $list = $this->getJson('/api/voting?all=1')->assertOk()->json('data');
        $item = collect($list)->firstWhere('id_voting', $voting->id_voting);
        $this->assertNotNull($item['kuorum'] ?? null, 'Payload daftar voting harus memuat blok kuorum.');
        $this->assertSame(3, $item['kuorum']['total_invited']);
        $this->assertSame(2, $item['kuorum']['present_count']);
        $this->assertFalse($item['kuorum']['is_kuorum']);

        $detail = $this->getJson("/api/voting/{$voting->id_voting}")->assertOk()->json();
        $d = $detail['data'] ?? $detail;
        $this->assertSame(2, $d['kuorum']['present_count']);
        $this->assertFalse($d['kuorum']['is_kuorum']);
    }

    public function test_quorum_uses_canonical_map_and_percent_boundary(): void
    {
        $rapat = $this->rapat(67, 3);
        $voting = $this->voting($rapat);
        AnggotaRapat::query()->limit(2)->update(['status_kehadiran' => 'hadir']);
        $data = $this->rekap($voting);
        $this->assertFalse($data['kuorum']['is_kuorum']);
        $this->assertSame(3, $data['kuorum']['ambang_kuorum']);
        $this->assertSame(2, $data['kuorum']['present_count']);
        $this->assertSame(false, $data['kuorum']['kuorum_terpenuhi']);
        AnggotaRapat::query()->update(['status_kehadiran' => 'hadir']);
        $data = $this->rekap($voting);
        $this->assertTrue($data['kuorum']['is_kuorum']);
        $this->assertSame(true, $data['kuorum']['kuorum_terpenuhi']);
        $this->assertSame(3, $data['kuorum']['total_invited']);
        $this->assertSame(3, $data['kuorum']['total_undangan']);
        $this->assertSame('Kuorum Terpenuhi', $data['kuorum']['status_kuorum_label']);
        $this->assertSame(100.0, (float) $data['kuorum']['present_percentage']);
        $this->assertSame(67, $data['kuorum']['kuorum_min_persen']);

        $half = $this->rapat(67, 2);
        $halfVoting = $this->voting($half);
        AnggotaRapat::where('id_rapat', $half->id_rapat)->limit(1)
            ->update(['status_kehadiran' => 'hadir']);
        $data = $this->rekap($halfVoting);
        $this->assertFalse($data['kuorum']['is_kuorum']);
        $this->assertSame(2, $data['kuorum']['ambang_kuorum']);
        $this->assertSame(1, $data['kuorum']['present_count']);
    }

    public function test_only_hadir_counts_even_with_presence_timestamp(): void
    {
        $rapat = $this->rapat(67, 4);
        $voting = $this->voting($rapat);
        $rows = AnggotaRapat::all();
        $rows[0]->update(['status_kehadiran' => 'hadir', 'waktu_presensi' => now()]);
        $rows[1]->update(['status_kehadiran' => 'izin', 'waktu_presensi' => now()]);
        $rows[2]->update(['status_kehadiran' => 'tidak_hadir', 'waktu_presensi' => now()]);
        $rows[3]->update(['status_kehadiran' => null, 'waktu_presensi' => now()]);
        $data = $this->rekap($voting);
        $this->assertSame(1, $data['kuorum']['present_count']);
        $this->assertSame(4, $data['kuorum']['total_invited']);
        $this->assertSame(3, $data['kuorum']['ambang_kuorum']);
        $this->assertFalse($data['kuorum']['is_kuorum']);
        $this->assertSame(1, $data['kuorum']['izin_count']);
        $this->assertSame(1, $data['kuorum']['tidak_hadir_count']);
        $this->assertSame(1, $data['kuorum']['menunggu_count']);
    }

    public function test_no_quorum_suppresses_decision(): void
    {
        $rapat = $this->rapat(67, 3);
        $voting = $this->voting($rapat);
        AnggotaRapat::query()->limit(1)->update(['status_kehadiran' => 'hadir']);
        $this->vote($voting, 'Setuju', 5);
        $data = $this->rekap($voting);
        $this->assertFalse($data['kuorum']['is_kuorum']);
        $this->assertNull($data['keputusan_sidang']);
        $this->assertSame('kuorum_belum_terpenuhi', $data['keputusan_alasan']);
        $this->assertFalse($data['seri']);
        $this->assertSame(5, $data['kuorum']['total_suara']);
    }

    public function test_unique_winner_requires_quorum(): void
    {
        $rapat = $this->rapat(67, 3);
        $voting = $this->voting($rapat);
        AnggotaRapat::query()->update(['status_kehadiran' => 'hadir']);
        $this->vote($voting, 'Setuju', 2);
        $this->vote($voting, 'Tolak', 1);
        $data = $this->rekap($voting);
        $this->assertTrue($data['kuorum']['is_kuorum']);
        $this->assertSame('Setuju', $data['keputusan_sidang']);
        $this->assertNull($data['keputusan_alasan']);
        $this->assertFalse($data['seri']);
        $this->assertSame([], $data['opsi_seri']);
    }

    public function test_ties_suppress_decision_and_list_tied_options(): void
    {
        $rapat = $this->rapat(67, 3);
        $voting = $this->voting($rapat, ['A', 'B', 'C']);
        AnggotaRapat::query()->update(['status_kehadiran' => 'hadir']);
        $this->vote($voting, 'A', 2);
        $this->vote($voting, 'B', 2);
        $this->vote($voting, 'C', 1);
        $data = $this->rekap($voting);
        $this->assertTrue($data['kuorum']['is_kuorum']);
        $this->assertTrue($data['seri']);
        $this->assertNull($data['keputusan_sidang']);
        $this->assertSame('seri', $data['keputusan_alasan']);
        $this->assertSame(['A', 'B'], $data['opsi_seri']);

        $three = $this->voting($rapat, ['X', 'Y', 'Z']);
        $this->vote($three, 'X', 1);
        $this->vote($three, 'Y', 1);
        $this->vote($three, 'Z', 1);
        $data = $this->rekap($three);
        $this->assertTrue($data['seri']);
        $this->assertSame(['X', 'Y', 'Z'], $data['opsi_seri']);
        $this->assertNull($data['keputusan_sidang']);
    }

    public function test_tie_with_no_quorum_is_still_no_decision(): void
    {
        $rapat = $this->rapat(67, 3);
        $voting = $this->voting($rapat);
        $this->vote($voting, 'Setuju', 1);
        $this->vote($voting, 'Tolak', 1);
        $data = $this->rekap($voting);
        $this->assertFalse($data['kuorum']['is_kuorum']);
        $this->assertNull($data['keputusan_sidang']);
        $this->assertSame('kuorum_belum_terpenuhi', $data['keputusan_alasan']);
        $this->assertTrue($data['seri']);
        $this->assertSame(['Setuju', 'Tolak'], $data['opsi_seri']);
    }

    public function test_zero_ballots_is_not_a_tie(): void
    {
        $rapat = $this->rapat(67, 3);
        $voting = $this->voting($rapat);
        AnggotaRapat::query()->update(['status_kehadiran' => 'hadir']);
        $data = $this->rekap($voting);
        $this->assertTrue($data['kuorum']['is_kuorum']);
        $this->assertFalse($data['seri']);
        $this->assertNull($data['keputusan_sidang']);
        $this->assertSame('tidak_ada_suara', $data['keputusan_alasan']);
        $this->assertSame([], $data['opsi_seri']);
    }

    public function test_zero_invitations_and_missing_rapat_keep_shape(): void
    {
        $rapat = $this->rapat(67, 0);
        $voting = $this->voting($rapat);
        $data = $this->rekap($voting);
        $this->assertSame(0, $data['kuorum']['total_invited']);
        $this->assertSame(0, $data['kuorum']['ambang_kuorum']);
        $this->assertFalse($data['kuorum']['is_kuorum']);
        $this->assertSame('kuorum_belum_terpenuhi', $data['keputusan_alasan']);
        $this->assertSame('voting', array_key_first($data));
        $this->assertArrayHasKey('rapat', $data);
        $this->assertSame(['total_undangan', 'total_hadir', 'total_suara', 'persentase_kehadiran',
            'kuorum_terpenuhi', 'total_invited', 'present_count', 'izin_count', 'tidak_hadir_count',
            'menunggu_count', 'kuorum_min_persen', 'ambang_kuorum', 'present_percentage', 'is_kuorum',
            'status_kuorum_label', 'is_presensi_open', 'is_kuorum_locked'], array_keys($data['kuorum']));

        $orphan = $this->voting($this->rapat(67, 1));
        $mapping = app(\App\Http\Controllers\VotingController::class)->kuorumPayload(null);
        $this->assertSame(['total_invited' => 0, 'present_count' => 0, 'kuorum_min_persen' => 67,
            'ambang_kuorum' => 0, 'present_percentage' => 0, 'is_kuorum' => false], array_intersect_key(
            $mapping, array_flip(['total_invited', 'present_count', 'kuorum_min_persen',
                'ambang_kuorum', 'present_percentage', 'is_kuorum'])
        ));
        $data = $this->rekap($orphan);
        $this->assertSame(1, $data['kuorum']['total_invited']);
    }

    public function test_closed_session_rekap_is_public_read_only(): void
    {
        $rapat = $this->rapat(67, 3);
        $voting = $this->voting($rapat);
        AnggotaRapat::query()->update(['status_kehadiran' => 'hadir']);
        $this->vote($voting, 'Setuju', 1);
        $voting->update(['status' => 'ditutup', 'waktu_selesai' => now()]);
        $data = $this->rekap($voting);
        $this->assertSame('Setuju', $data['keputusan_sidang']);
        $this->assertSame('ditutup', $data['voting']['status']);
        $this->assertSame(1, $data['kuorum']['total_suara']);
        $this->assertArrayNotHasKey('suara_votings', $data['voting']);
    }
}
