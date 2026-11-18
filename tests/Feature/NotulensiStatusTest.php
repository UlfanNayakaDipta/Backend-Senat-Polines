<?php

namespace Tests\Feature;

use App\Models\JenisRapat;
use App\Models\Notulensi;
use App\Models\Rapat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Status notulensi mengikuti istilah keputusan senat:
 * ditetapkan / dipertimbangkan / dikembalikan.
 */
class NotulensiStatusTest extends TestCase
{
    use RefreshDatabase;

    private const STATUS_BARU = ['ditetapkan', 'dipertimbangkan', 'dikembalikan'];

    private const STATUS_LAMA = ['draf', 'review', 'disahkan'];

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function buatRapat(User $user): Rapat
    {
        $jenis = JenisRapat::create(['nama_jenis' => 'Rapat Pleno']);

        return Rapat::create([
            'id_user' => $user->id_user,
            'id_jenis_rapat' => $jenis->id_jenis_rapat,
            'nama_agenda' => 'RAPAT PLENO PENETAPAN STATUS NOTULENSI',
            'tanggal' => '2026-11-18',
            'waktu' => '13:00',
            'tempat' => 'AD.II.16',
            'status' => 'selesai',
        ]);
    }

    private function buatNotulensi(User $user): Notulensi
    {
        $rapat = $this->buatRapat($user);

        return Notulensi::create([
            'id_rapat' => $rapat->id_rapat,
            'id_user' => $user->id_user,
            'isi_notulensi' => 'Catatan sidang pleno penetapan status notulensi.',
            'status' => 'dipertimbangkan',
        ]);
    }

    public function test_update_menerima_tiga_status_baru(): void
    {
        $user = $this->admin();
        Sanctum::actingAs($user);
        $notulensi = $this->buatNotulensi($user);

        foreach (self::STATUS_BARU as $status) {
            $this->putJson("/api/notulensi/{$notulensi->id_notulensi}", ['status' => $status])
                ->assertOk();

            $this->assertSame($status, $notulensi->fresh()->status);
        }
    }

    public function test_update_menolak_status_lama(): void
    {
        $user = $this->admin();
        Sanctum::actingAs($user);
        $notulensi = $this->buatNotulensi($user);

        foreach (self::STATUS_LAMA as $status) {
            $this->putJson("/api/notulensi/{$notulensi->id_notulensi}", ['status' => $status])
                ->assertStatus(422);

            $this->assertSame('dipertimbangkan', $notulensi->fresh()->status);
        }
    }

    public function test_store_tanpa_status_memakai_status_dipertimbangkan(): void
    {
        $user = $this->admin();
        Sanctum::actingAs($user);
        $rapat = $this->buatRapat($user);

        $response = $this->postJson('/api/notulensi', [
            'id_rapat' => $rapat->id_rapat,
            'id_user' => $user->id_user,
            'isi_notulensi' => 'Notulensi tanpa status eksplisit.',
        ])->assertCreated();

        $id = $response->json('id_notulensi');
        $this->assertSame('dipertimbangkan', Notulensi::findOrFail($id)->status);
    }

    public function test_kolom_status_default_dipertimbangkan(): void
    {
        $user = $this->admin();
        $rapat = $this->buatRapat($user);

        DB::table('notulensis')->insert([
            'id_rapat' => $rapat->id_rapat,
            'id_user' => $user->id_user,
            'isi_notulensi' => 'Baris tanpa kolom status.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(
            'dipertimbangkan',
            DB::table('notulensis')->where('isi_notulensi', 'Baris tanpa kolom status.')->value('status')
        );
    }

    public function test_status_kapital_disimpan_huruf_kecil(): void
    {
        Sanctum::actingAs($this->admin());
        $notulensi = $this->buatNotulensi($this->admin());

        $this->putJson("/api/notulensi/{$notulensi->id_notulensi}", ['status' => 'Ditetapkan'])
            ->assertOk();

        $this->assertSame('ditetapkan', $notulensi->fresh()->status);
    }
}
