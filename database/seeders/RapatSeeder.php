<?php

namespace Database\Seeders;

use App\Models\AnggotaRapat;
use App\Models\JenisRapat;
use App\Models\Rapat;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RapatSeeder extends Seeder
{
    public function run(): void
    {
        $jenisPleno = JenisRapat::firstOrCreate(['nama_jenis' => 'Rapat Pleno']);
        $jenisKomisi = JenisRapat::firstOrCreate(['nama_jenis' => 'Rapat Komisi']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@senat.test'],
            ['nim_nip' => 'admin', 'nama' => 'Administrator', 'role' => 'admin', 'password' => 'password']
        );

        $daftarNama = [
            'Dr. Sukamto., S.Kom., M.T',
            'Andi Setyawan., S.E., M.M',
            'Tri Yudantoro, S.Kom., M.Kom.',
            'Anis Roihatin, S.T., M.T.',
            'Prof. Dr. Ir. Dwiana Hendrawati, S.T., M.T.',
            'Yusmar Ardhi Hidayat, S.E., M.Si., Ph.D.',
            'Resi Yudaningsih, S.E., M.Si., Akt.',
            'Dr. Bagus Yunianto W., S.E., S.Kom., M.M.',
            'Nanang Adie Setyawan, S.E., M.M.',
            'Dr. Jumi, S.Kom., M.Kom.',
            'Fajar Surya Herlambang, S.T., M.T.',
            'Yusnan Badruzzaman, S.T., M.Eng.',
            'Mohammad Hasanudin, S.E., M.Si.',
            'Vinda Setya Kartika, S.T., M.T.',
            'Ir. Teguh Mulyo Wicaksono, S.Pd., M.Eng.',
            'Ir. Riles Melvy Wattimena, M.T., IPP.',
            'Iwan Budiyono, S.E., M.Si.Ak',
            'Sahid, S.T., M.T.',
            'Dedi Budi Setyawan, S.T., M.T.',
            'Dr. Muhammad Anif, S.T., M.Eng.',
            'Septian Yudha Kusuma, S.E., M.Si.Ak',
            'Dr. Ir. FX Arif Wahyudianto, S.T., M.T., IPM.',
            'Alvianita Gunawan Putri, S.E., M.Acc., Ak., CA., CPA.',
            'Prof. Dr. Ir. Kurnianingsih, S.T., M.T.',
            'Ir. Abdul Syukur Fauzi, S.T., M.T.',
            'Dr. Samuel Beta Kuntardjo, M.Sc.',
            'Agus Suryanto, S.T., M.T.',
            'Siti Rohana, S.E., M.Si.',
            'Kurnianingsih, S.T., M.T.',
            'Bambang Sri Waluyo, S.T., M.T.',
            'Drs. Subandi, M.Kom.',
            'Purnomo, S.T., M.Eng.',
            'Drs. Suharsono, M.Pd.',
            'Dra. Nurul Hidayati, M.Hum.',
        ];

        $users = [];
        foreach ($daftarNama as $index => $nama) {
            $nip = '197' . str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT);
            $email = 'senat' . ($index + 1) . '@senat.polines.ac.id';
            
            $u = User::updateOrCreate(
                ['nim_nip' => $nip],
                [
                    'nama' => $nama,
                    'email' => $email,
                    'role' => 'anggota',
                    'password' => 'password',
                ]
            );
            $users[] = $u;
        }

        // 1. Rapat Aktif Hari Ini (Sidang Pleno)
        $rapatAktif = Rapat::updateOrCreate(
            ['nama_agenda' => 'Sidang Pleno Evaluasi Akreditasi Program Studi Jurusan Teknik Sipil & Elektro'],
            [
                'id_user' => $admin->id_user,
                'id_jenis_rapat' => $jenisPleno->id_jenis_rapat,
                'tanggal' => now()->toDateString(),
                'waktu' => '09:00',
                'tempat' => 'Ruang Sidang Utama Lantai 2 Gedung Direktorat Polines',
                'status' => 'Sedang Berlangsung',
                'token_qr' => 'token-polines-pln-892-live',
                'kode_sesi' => 'PLN-892',
                'is_presensi_open' => true,
                'qr_expires_at' => now()->addHours(3),
                'kuorum_min_persen' => 67, // 2/3 anggota
                'is_kuorum_locked' => false,
            ]
        );

        // Isi undangan peserta sidang
        foreach ($users as $index => $u) {
            // Skenario awal:
            // 0 - 22 (23 orang) -> hadir
            // 23 - 25 (3 orang) -> izin
            // 26 - 33 (8 orang) -> menunggu
            if ($index < 23) {
                $status = 'hadir';
                $waktu = now()->subMinutes(rand(5, 45));
                $metode = 'qr_scan';
            } elseif ($index < 26) {
                $status = 'izin';
                $waktu = null;
                $metode = null;
            } else {
                $status = 'menunggu';
                $waktu = null;
                $metode = null;
            }

            AnggotaRapat::updateOrCreate(
                [
                    'id_rapat' => $rapatAktif->id_rapat,
                    'id_user' => $u->id_user,
                ],
                [
                    'status_kehadiran' => $status,
                    'waktu_presensi' => $waktu,
                    'metode_presensi' => $metode,
                    'is_edited_by_admin' => false,
                ]
            );
        }

        // 2. Rapat Terjadwal Mendatang
        Rapat::updateOrCreate(
            ['nama_agenda' => 'Rapat Koordinasi Komisi I: Penyelarasan Kurikulum MBKM Vokasi 2026/2027'],
            [
                'id_user' => $admin->id_user,
                'id_jenis_rapat' => $jenisKomisi->id_jenis_rapat,
                'tanggal' => now()->addDays(2)->toDateString(),
                'waktu' => '13:30',
                'tempat' => 'Ruang Rapat Senat Gedung Kerjasama',
                'status' => 'Terjadwal',
                'token_qr' => 'token-polines-kms-104-sched',
                'kode_sesi' => 'KMS-104',
                'is_presensi_open' => false,
                'qr_expires_at' => null,
                'kuorum_min_persen' => 50,
                'is_kuorum_locked' => false,
            ]
        );
    }
}
