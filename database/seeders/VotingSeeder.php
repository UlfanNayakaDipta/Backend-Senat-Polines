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

        // ================= RIWAYAT SESI 2 (DITUTUP) =================
        $rapat2 = Rapat::firstOrCreate(
            ['nama_agenda' => 'Sidang Khusus Komisi II Ketenagaan & Guru Besar'],
            [
                'id_user' => $userAdmin->id_user,
                'id_jenis_rapat' => $jenisRapat->id_jenis_rapat,
                'tanggal' => now()->subDays(15)->toDateString(),
                'waktu' => '13:30:00',
                'tempat' => 'Ruang Sidang Senat Lantai 2',
                'status' => 'selesai',
            ]
        );

        foreach ($users as $u) {
            AnggotaRapat::firstOrCreate(
                [
                    'id_rapat' => $rapat2->id_rapat,
                    'id_user' => $u->id_user,
                ],
                [
                    'status_kehadiran' => 'hadir',
                    'waktu_presensi' => now()->subDays(15)->setTime(13, 15),
                ]
            );
        }

        $voting2 = Voting::firstOrCreate(
            [
                'id_rapat' => $rapat2->id_rapat,
                'judul_voting' => 'Pertimbangan Pemberian Gelar Profesor Kehormatan Bidang Mekatronika Cerdas',
            ],
            [
                'deskripsi' => 'Pemberian pertimbangan dan rekomendasi Senat Akademik atas berkas usulan Guru Besar Kehormatan pada Jurusan Teknik Mesin dan Elektro.',
                'status' => 'ditutup',
                'waktu_mulai' => now()->subDays(15)->setTime(13, 30),
                'waktu_selesai' => now()->subDays(15)->setTime(15, 0),
            ]
        );

        $v2Setuju = OpsiVoting::firstOrCreate(['id_voting' => $voting2->id_voting, 'nama_opsi' => 'Setuju']);
        $v2Tolak = OpsiVoting::firstOrCreate(['id_voting' => $voting2->id_voting, 'nama_opsi' => 'Tidak Setuju']);
        $v2Abstain = OpsiVoting::firstOrCreate(['id_voting' => $voting2->id_voting, 'nama_opsi' => 'Abstain']);

        foreach ($users as $idx => $u) {
            $opt = ($idx % 7 === 0) ? $v2Tolak->id_opsi : (($idx % 11 === 0) ? $v2Abstain->id_opsi : $v2Setuju->id_opsi);
            SuaraVoting::firstOrCreate(
                [
                    'id_voting' => $voting2->id_voting,
                    'id_user' => $u->id_user,
                ],
                [
                    'id_opsi' => $opt,
                    'waktu_vote' => now()->subDays(15)->setTime(14, 10),
                ]
            );
        }

        // ================= RIWAYAT SESI 3 (DITUTUP) =================
        $rapat3 = Rapat::firstOrCreate(
            ['nama_agenda' => 'Sidang Pleno Pembukaan Program Studi Magister Terapan'],
            [
                'id_user' => $userAdmin->id_user,
                'id_jenis_rapat' => $jenisRapat->id_jenis_rapat,
                'tanggal' => now()->subDays(35)->toDateString(),
                'waktu' => '10:00:00',
                'tempat' => 'Ruang Sidang Utama Gedung Direktorat Polines',
                'status' => 'selesai',
            ]
        );

        foreach ($users as $u) {
            AnggotaRapat::firstOrCreate(
                [
                    'id_rapat' => $rapat3->id_rapat,
                    'id_user' => $u->id_user,
                ],
                [
                    'status_kehadiran' => 'hadir',
                    'waktu_presensi' => now()->subDays(35)->setTime(9, 45),
                ]
            );
        }

        $voting3 = Voting::firstOrCreate(
            [
                'id_rapat' => $rapat3->id_rapat,
                'judul_voting' => 'Persetujuan Usulan Pembukaan Program Studi Magister Terapan Rekayasa Keamanan Siber',
            ],
            [
                'deskripsi' => 'Pengambilan keputusan kelayakan akademis, sarana prasarana, dan persetujuan pengusulan prodi baru S2 Terapan ke kementerian.',
                'status' => 'ditutup',
                'waktu_mulai' => now()->subDays(35)->setTime(10, 0),
                'waktu_selesai' => now()->subDays(35)->setTime(11, 45),
            ]
        );

        $v3Setuju = OpsiVoting::firstOrCreate(['id_voting' => $voting3->id_voting, 'nama_opsi' => 'Setuju']);
        $v3Tolak = OpsiVoting::firstOrCreate(['id_voting' => $voting3->id_voting, 'nama_opsi' => 'Tidak Setuju']);
        $v3Abstain = OpsiVoting::firstOrCreate(['id_voting' => $voting3->id_voting, 'nama_opsi' => 'Abstain']);

        foreach ($users as $idx => $u) {
            $opt = ($idx % 9 === 0) ? $v3Tolak->id_opsi : (($idx % 13 === 0) ? $v3Abstain->id_opsi : $v3Setuju->id_opsi);
            SuaraVoting::firstOrCreate(
                [
                    'id_voting' => $voting3->id_voting,
                    'id_user' => $u->id_user,
                ],
                [
                    'id_opsi' => $opt,
                    'waktu_vote' => now()->subDays(35)->setTime(10, 30),
                ]
            );
        }
    }
}
