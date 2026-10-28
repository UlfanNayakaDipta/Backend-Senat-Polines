<?php

namespace Database\Seeders;

use App\Models\AnggotaRapat;
use App\Models\JenisRapat;
use App\Models\OpsiVoting;
use App\Models\Rapat;
use App\Models\SuaraVoting;
use App\Models\User;
use App\Models\Voting;
use Illuminate\Database\Seeder;

class VotingSeeder extends Seeder
{
    public function run(): void
    {
        $userAdmin = User::where('role', 'admin')->first()
            ?? User::where('email', 'admin@senat.test')->first()
            ?? User::first();

        $jenisRapat = JenisRapat::where('nama_jenis', 'Rapat Pleno')->first()
            ?? JenisRapat::firstOrCreate(['nama_jenis' => 'Rapat Pleno']);

        $rapat = Rapat::firstOrCreate(
            ['nama_agenda' => 'Sidang Pleno Penetapan Standar Akademik & Kurikulum Vokasi 2026/2027'],
            [
                'id_user' => $userAdmin->id_user,
                'id_jenis_rapat' => $jenisRapat->id_jenis_rapat,
                'tanggal' => now()->toDateString(),
                'waktu' => '09:00:00',
                'tempat' => 'Ruang Sidang Utama Gedung Direktorat Polines',
                'status' => 'berlangsung',
            ]
        );

        $users = User::all();
        foreach ($users as $u) {
            AnggotaRapat::firstOrCreate(
                [
                    'id_rapat' => $rapat->id_rapat,
                    'id_user' => $u->id_user,
                ],
                [
                    'status_kehadiran' => 'hadir',
                    'waktu_presensi' => now()->subMinutes(90),
                ]
            );
        }

        $voting = Voting::firstOrCreate(
            [
                'id_rapat' => $rapat->id_rapat,
                'judul_voting' => 'Persetujuan Pengesahan Perubahan Kurikulum MBKM Vokasi 2026/2027',
            ],
            [
                'deskripsi' => 'Apakah Sidang Pleno menyetujui draf revisi kurikulum vokasi berbasis industri untuk disahkan menjadi Peraturan Senat Akademik?',
                'status' => 'dibuka',
                'waktu_mulai' => now()->subHours(1),
            ]
        );

        $opsiSetuju = OpsiVoting::firstOrCreate(
            ['id_voting' => $voting->id_voting, 'nama_opsi' => 'Setuju']
        );
        $opsiTidakSetuju = OpsiVoting::firstOrCreate(
            ['id_voting' => $voting->id_voting, 'nama_opsi' => 'Tidak Setuju']
        );
        $opsiAbstain = OpsiVoting::firstOrCreate(
            ['id_voting' => $voting->id_voting, 'nama_opsi' => 'Abstain']
        );

        $distribusiOpsi = [
            0 => $opsiSetuju->id_opsi,
            1 => $opsiSetuju->id_opsi,
            2 => $opsiSetuju->id_opsi,
            3 => $opsiTidakSetuju->id_opsi,
            4 => $opsiAbstain->id_opsi,
        ];

        foreach ($users as $index => $u) {
            $idOpsi = $distribusiOpsi[$index] ?? $opsiSetuju->id_opsi;

            SuaraVoting::firstOrCreate(
                [
                    'id_voting' => $voting->id_voting,
                    'id_user' => $u->id_user,
                ],
                [
                    'id_opsi' => $idOpsi,
                    'waktu_vote' => now()->subMinutes(30),
                ]
            );
        }
    }
}
