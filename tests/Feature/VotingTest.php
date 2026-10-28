<?php

namespace Tests\Feature;

use App\Models\JenisRapat;
use App\Models\OpsiVoting;
use App\Models\Rapat;
use App\Models\SuaraVoting;
use App\Models\User;
use App\Models\Voting;
use Database\Seeders\VotingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VotingTest extends TestCase
{
    use RefreshDatabase;

    private function createRapat(): Rapat
    {
        $user = User::factory()->create();
        $jenis = JenisRapat::create(['nama_jenis' => 'Rapat Pleno']);

        return Rapat::create([
            'id_user' => $user->id_user,
            'id_jenis_rapat' => $jenis->id_jenis_rapat,
            'nama_agenda' => 'Agenda Rapat Uji',
            'tanggal' => now()->toDateString(),
            'waktu' => '09:00',
            'tempat' => 'Ruang Sidang',
            'status' => 'berlangsung',
        ]);
    }

    public function test_voting_and_opsi_voting_models_and_accessors(): void
    {
        $rapat = $this->createRapat();
        $voting = Voting::create([
            'id_rapat' => $rapat->id_rapat,
            'judul_voting' => 'Voting Uji',
            'status' => 'dibuka',
            'waktu_mulai' => now(),
        ]);

        $opsi1 = $voting->opsiVotings()->create(['nama_opsi' => 'Setuju']);
        $opsi2 = $voting->opsiVotings()->create(['nama_opsi' => 'Tolak']);

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        SuaraVoting::create([
            'id_voting' => $voting->id_voting,
            'id_opsi' => $opsi1->id_opsi,
            'id_user' => $u1->id_user,
        ]);
        SuaraVoting::create([
            'id_voting' => $voting->id_voting,
            'id_opsi' => $opsi2->id_opsi,
            'id_user' => $u2->id_user,
        ]);

        $this->assertInstanceOf(Rapat::class, $voting->rapat);
        $this->assertCount(2, $voting->opsiVotings);
        $this->assertCount(2, $voting->suaraVotings);
        $this->assertEquals(2, $voting->total_suara);

        $this->assertInstanceOf(Voting::class, $opsi1->voting);
        $this->assertCount(1, $opsi1->suaraVotings);
        $this->assertEquals(1, $opsi1->jumlah_suara);

        $rekap = $voting->hasil_rekap;
        $this->assertCount(2, $rekap);
        $this->assertEquals(50.0, $rekap[0]['persentase']);
        $this->assertEquals(50.0, $rekap[1]['persentase']);
    }

    public function test_voting_controller_store_default_and_custom_options(): void
    {
        $rapat = $this->createRapat();

        $res1 = $this->postJson('/api/voting', [
            'id_rapat' => $rapat->id_rapat,
            'judul_voting' => 'Voting Default Opsi',
        ]);
        $res1->assertStatus(201);
        $id1 = $res1->json('id_voting');
        $voting1 = Voting::with('opsiVotings')->find($id1);
        $this->assertCount(3, $voting1->opsiVotings);
        $this->assertEquals(['Setuju', 'Tidak Setuju', 'Abstain'], $voting1->opsiVotings->pluck('nama_opsi')->all());

        $res2 = $this->postJson('/api/voting', [
            'id_rapat' => $rapat->id_rapat,
            'judul_voting' => 'Voting Custom Opsi',
            'opsi' => ['Pilihan A', 'Pilihan B'],
        ]);
        $res2->assertStatus(201);
        $id2 = $res2->json('id_voting');
        $voting2 = Voting::with('opsiVotings')->find($id2);
        $this->assertCount(2, $voting2->opsiVotings);
        $this->assertEquals(['Pilihan A', 'Pilihan B'], $voting2->opsiVotings->pluck('nama_opsi')->all());
    }

    public function test_voting_controller_index_and_filters(): void
    {
        $rapat = $this->createRapat();

        Voting::create([
            'id_rapat' => $rapat->id_rapat,
            'judul_voting' => 'Voting Terbuka',
            'status' => 'dibuka',
        ]);
        Voting::create([
            'id_rapat' => $rapat->id_rapat,
            'judul_voting' => 'Voting Ditutup',
            'status' => 'ditutup',
        ]);

        $resSemua = $this->getJson('/api/voting');
        $resSemua->assertStatus(200);
        $this->assertCount(2, $resSemua->json('data'));

        $resDibuka = $this->getJson('/api/voting?status=dibuka');
        $resDibuka->assertStatus(200);
        $this->assertCount(1, $resDibuka->json('data'));
        $this->assertEquals('dibuka', $resDibuka->json('data.0.status'));

        $resDitutup = $this->getJson('/api/voting?status=ditutup');
        $resDitutup->assertStatus(200);
        $this->assertCount(1, $resDitutup->json('data'));
        $this->assertEquals('ditutup', $resDitutup->json('data.0.status'));

        $resActive = $this->getJson('/api/voting?active=1');
        $resActive->assertStatus(200);
        $this->assertCount(1, $resActive->json('data'));
        $this->assertEquals('dibuka', $resActive->json('data.0.status'));
    }

    public function test_voting_controller_buka_tutup_rekap(): void
    {
        $rapat = $this->createRapat();
        $voting = Voting::create([
            'id_rapat' => $rapat->id_rapat,
            'judul_voting' => 'Voting Siklus Hidup',
            'status' => 'draft',
        ]);
        $opsi1 = $voting->opsiVotings()->create(['nama_opsi' => 'Setuju']);
        $opsi2 = $voting->opsiVotings()->create(['nama_opsi' => 'Tolak']);

        $resBuka = $this->postJson("/api/voting/{$voting->id_voting}/buka");
        $resBuka->assertStatus(200);
        $this->assertEquals('dibuka', $resBuka->json('status'));
        $this->assertNotNull($resBuka->json('waktu_mulai'));

        $user = User::factory()->create();
        SuaraVoting::create([
            'id_voting' => $voting->id_voting,
            'id_opsi' => $opsi1->id_opsi,
            'id_user' => $user->id_user,
        ]);

        $resRekap = $this->getJson("/api/voting/{$voting->id_voting}/rekap");
        $resRekap->assertStatus(200);
        $resRekap->assertJsonStructure([
            'voting',
            'rapat',
            'kuorum' => ['total_undangan', 'total_hadir', 'total_suara', 'persentase_kehadiran', 'kuorum_terpenuhi'],
            'rekapitulasi',
            'opsi_terbanyak',
            'keputusan_sidang',
        ]);
        $this->assertEquals('Setuju', $resRekap->json('keputusan_sidang'));

        $resTutup = $this->postJson("/api/voting/{$voting->id_voting}/tutup");
        $resTutup->assertStatus(200);
        $this->assertEquals('ditutup', $resTutup->json('status'));
        $this->assertNotNull($resTutup->json('waktu_selesai'));
    }

    public function test_suara_voting_controller_validations_and_store(): void
    {
        $rapat = $this->createRapat();
        $voting = Voting::create([
            'id_rapat' => $rapat->id_rapat,
            'judul_voting' => 'Voting Tertutup',
            'status' => 'ditutup',
        ]);
        $opsi = $voting->opsiVotings()->create(['nama_opsi' => 'Setuju']);
        $user = User::factory()->create();

        $resClosed = $this->postJson('/api/suara-voting', [
            'id_voting' => $voting->id_voting,
            'id_opsi' => $opsi->id_opsi,
            'id_user' => $user->id_user,
        ]);
        $resClosed->assertStatus(422);
        $resClosed->assertJson(['message' => 'Voting sedang ditutup atau belum dibuka.']);

        $voting->update(['status' => 'dibuka', 'waktu_mulai' => now()]);

        $resVote = $this->postJson('/api/suara-voting', [
            'id_voting' => $voting->id_voting,
            'id_opsi' => $opsi->id_opsi,
            'id_user' => $user->id_user,
        ]);
        $resVote->assertStatus(201);
        $resVote->assertJsonStructure([
            'message',
            'suara',
            'total_suara',
            'hasil_rekap',
            'ringkasan' => ['total_suara', 'hasil_rekap'],
        ]);
        $this->assertEquals(1, $resVote->json('ringkasan.total_suara'));

        $resDuplicate = $this->postJson('/api/suara-voting', [
            'id_voting' => $voting->id_voting,
            'id_opsi' => $opsi->id_opsi,
            'id_user' => $user->id_user,
        ]);
        $resDuplicate->assertStatus(422);
        $resDuplicate->assertJson(['message' => 'Anda sudah memberikan suara pada sesi voting ini.']);
    }

    public function test_voting_seeder_execution(): void
    {
        User::create([
            'nim_nip' => 'admin',
            'nama' => 'Administrator',
            'email' => 'admin@senat.test',
            'role' => 'admin',
            'password' => 'password',
        ]);
        User::create([
            'nim_nip' => '2021001',
            'nama' => 'Dr. Sukamto',
            'email' => 'ketua@senat.test',
            'role' => 'anggota',
            'password' => 'password',
        ]);

        $this->seed(VotingSeeder::class);

        $this->assertDatabaseHas('rapats', [
            'nama_agenda' => 'Sidang Pleno Penetapan Standar Akademik & Kurikulum Vokasi 2026/2027',
        ]);

        $voting = Voting::where('judul_voting', 'Persetujuan Pengesahan Perubahan Kurikulum MBKM Vokasi 2026/2027')->first();
        $this->assertNotNull($voting);
        $this->assertEquals('dibuka', $voting->status);

        $opsiNames = $voting->opsiVotings()->pluck('nama_opsi')->all();
        $this->assertContains('Setuju', $opsiNames);
        $this->assertContains('Tidak Setuju', $opsiNames);
        $this->assertContains('Abstain', $opsiNames);

        $this->assertGreaterThan(0, $voting->suaraVotings()->count());
    }
}
