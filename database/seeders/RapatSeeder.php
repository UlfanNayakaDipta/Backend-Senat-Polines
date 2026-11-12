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

        $daftarAnggota = [
            ['nim_nip' => '197101172003121001', 'nama' => 'Dr. Sukamto, S.Kom., M.T.'],
            ['nim_nip' => '197607202008011011', 'nama' => 'Andi Setiawan, S.E., M.M.'],
            ['nim_nip' => '196810252000121001', 'nama' => 'Tri Raharjo Yudantoro, S.Kom., M.Kom.'],
            ['nim_nip' => '198701252014042001', 'nama' => 'Anis Roihatin, S.T., M.T.'],
            ['nim_nip' => '196908141998022001', 'nama' => 'Prof. Dr. Ir. Dwiana Hendrawati, S.T., M.T.'],
            ['nim_nip' => '198101312006041003', 'nama' => 'Dr. Yusmar Ardhi Hidayat, S.E., M.Si., Ph.D.'],
            ['nim_nip' => '197211021998022001', 'nama' => 'Resi Yudhaningsih, S.E., M.Si., Akt.'],
            ['nim_nip' => '199106262019031017', 'nama' => 'Dr. Bagus Yunianto Wibowo, S.E., S.Kom., M.M.'],
            ['nim_nip' => '199107102019031017', 'nama' => 'Nanang Adie Setyawan, S.E., M.M.'],
            ['nim_nip' => '197006142005012001', 'nama' => 'Dr. Jumi, S.Kom., M.Kom.'],
            ['nim_nip' => '197206291999031001', 'nama' => 'Fajar Surya Herlambang, S.T., M.T.'],
            ['nim_nip' => '197503132006041001', 'nama' => 'Yusnan Badruzzaman, S.T., M.Eng.'],
            ['nim_nip' => '197608012000121001', 'nama' => 'Mohamad Hasanudin, S.E., M.Si., Akt.'],
            ['nim_nip' => '199108112019032024', 'nama' => 'Vinda Setya Kartika, S.T., M.T.'],
            ['nim_nip' => '199012022019031014', 'nama' => 'Ir. Teguh Mulyo Wicaksono, S.Pd., M.Eng.'],
            ['nim_nip' => '196709031994031001', 'nama' => 'Ir. Riles Melvy Wattimena, M.T., IPP.'],
            ['nim_nip' => '198410192010121003', 'nama' => 'Iwan Budiyono, S.E., M.Si.Ak'],
            ['nim_nip' => '197005121996011001', 'nama' => 'Sahid, S.T., M.T.'],
            ['nim_nip' => '197712062001121001', 'nama' => 'Dedi Budi Setiawan, S.T., M.T.'],
            ['nim_nip' => '197710092005011001', 'nama' => 'Dr. Muhammad Anif, S.T., M.Eng.'],
            ['nim_nip' => '199009032019031016', 'nama' => 'Septian Yudha Kusuma, S.E., M.Si.Ak'],
            ['nim_nip' => '197412111994031001', 'nama' => 'Dr. Ir. FX Arif Wahyudianto, S.T., M.T., IPM.'],
            ['nim_nip' => '199201072019032024', 'nama' => 'Alvianita Gunawan Putri, S.E., M.Acc., Ak., CA'],
            ['nim_nip' => '197904262003122002', 'nama' => 'Prof. Dr. Ir. Kurnianingsih, S.T., M.T.'],
            ['nim_nip' => '197005051999031002', 'nama' => 'Abdul Syukur Alfauzi, S.T., M.T.'],
            ['nim_nip' => '196404121996011001', 'nama' => 'Dr. Samuel Beta Kuntardjo, Ing.Tech., M.T.'],
            ['nim_nip' => '197304252005011001', 'nama' => 'Agus Suwondo, S.Kom., M.Kom.'],
            ['nim_nip' => '196412301990032002', 'nama' => 'Siti Arbainah, S.E., M.M.'],
            ['nim_nip' => '196511051994031002', 'nama' => 'Drs. Suroso, M.Sc.'],
            ['nim_nip' => '195903161987031002', 'nama' => 'Dr. Ir. Bambang Sri Waluyo, M.Si.'],
            ['nim_nip' => '196004151992031001', 'nama' => 'Drs. Subandi, M.M.Par.'],
            ['nim_nip' => '196210041988031003', 'nama' => 'Adhy Purnomo, S.T., M.T.'],
            ['nim_nip' => '196407071989021001', 'nama' => 'Suharno, S.H.'],
            ['nim_nip' => '197112042000032001', 'nama' => 'Nur Hidayati, S.H., M.H.'],
        ];

        $users = [];
        foreach ($daftarAnggota as $index => $item) {
            $email = 'senat' . ($index + 1) . '@senat.polines.ac.id';
            
            $u = User::updateOrCreate(
                ['email' => $email],
                [
                    'nim_nip' => $item['nim_nip'],
                    'nama' => $item['nama'],
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
