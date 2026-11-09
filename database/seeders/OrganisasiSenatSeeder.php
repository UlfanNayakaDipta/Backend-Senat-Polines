<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\Komisi;
use App\Models\PeriodeSenat;
use App\Models\StrukturOrganisasi;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrganisasiSenatSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Periode Senat 2026-2029
        $periode = PeriodeSenat::updateOrCreate(
            ['nama_periode' => '2026–2029'],
            ['tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2029-08-31']
        );

        // 2. 5 Komisi Tetap Resmi
        $komisiDefinitions = [
            1 => [
                'nama_komisi' => 'Komisi I',
                'deskripsi' => 'Komisi Pendidikan dan Pengajaran',
            ],
            2 => [
                'nama_komisi' => 'Komisi II',
                'deskripsi' => 'Komisi Penelitian dan Pengabdian kepada Masyarakat',
            ],
            3 => [
                'nama_komisi' => 'Komisi III',
                'deskripsi' => 'Komisi Kelembagaan dan Sumber Daya Manusia (Pengembangan SDM)',
            ],
            4 => [
                'nama_komisi' => 'Komisi IV',
                'deskripsi' => 'Komisi Pengembangan dan Kerja Sama',
            ],
            5 => [
                'nama_komisi' => 'Komisi V',
                'deskripsi' => 'Komisi Etik',
            ],
        ];

        $komisis = [];
        foreach ($komisiDefinitions as $id => $def) {
            $komisis[$id] = Komisi::updateOrCreate(['id_komisi' => $id], $def);
        }

        // 3. Akun Administrator & Sekretariat
        User::updateOrCreate(
            ['email' => 'admin@senat.test'],
            [
                'nim_nip' => 'admin',
                'nama' => 'Administrator Senat',
                'role' => 'admin',
                'password' => 'password',
            ]
        );

        User::updateOrCreate(
            ['email' => 'sekretariat@senat.test'],
            [
                'nim_nip' => 'sekretariat',
                'nama' => 'Sekretariat Senat',
                'role' => 'admin',
                'password' => 'password',
            ]
        );

        // 4. Daftar Seluruh 30 Tokoh Anggota Senat
        $anggotaList = [
            // Pimpinan Senat
            [
                'nama' => 'Dr. Sukamto, S.Kom., M.T.',
                'nim_nip' => '197101172003121001',
                'email' => 'ketua@senat.test',
                'komisi_id' => 5, // Juga anggota Komisi V
                'jabatan_komisi' => 'Ketua Senat',
                'jabatan_struktur' => 'Ketua Senat',
                'urutan' => 1,
            ],
            [
                'nama' => 'Andi Setiawan, S.E., M.M.',
                'nim_nip' => '197607202008011011',
                'email' => 'sekretaris@senat.test',
                'komisi_id' => 5, // Juga anggota Komisi V & Ketua BPH
                'jabatan_komisi' => 'Sekretaris Senat',
                'jabatan_struktur' => 'Sekretaris Senat',
                'urutan' => 2,
            ],

            // Komisi I — Pendidikan dan Pengajaran
            [
                'nama' => 'Tri Yudantoro, S.Kom., M.Kom.',
                'nim_nip' => '197205141999031002',
                'email' => 'tri.yudantoro@senat.test',
                'komisi_id' => 1,
                'jabatan_komisi' => 'Ketua',
                'jabatan_struktur' => 'Ketua Komisi I',
                'urutan' => 3,
            ],
            [
                'nama' => 'Anis Roihatin, S.T., M.T.',
                'nim_nip' => '197508212002122001',
                'email' => 'anggota1@senat.test', // Mempertahankan akun awal
                'komisi_id' => 1,
                'jabatan_komisi' => 'Sekretaris',
                'jabatan_struktur' => 'Sekretaris Komisi I',
                'urutan' => 4,
            ],
            [
                'nama' => 'Prof. Dr. Ir. Dwiana Hendrawati, S.T., M.T.',
                'nim_nip' => '196702111992032001',
                'email' => 'dwiana.hendrawati@senat.test',
                'komisi_id' => 1,
                'jabatan_komisi' => 'Anggota',
                'jabatan_struktur' => 'Anggota Komisi I',
                'urutan' => 5,
            ],
            [
                'nama' => 'Yusmar Ardhi Hidayat, S.E., M.Si., Ph.D.',
                'nim_nip' => '197410082001121001',
                'email' => 'yusmar.ardhi@senat.test',
                'komisi_id' => 1,
                'jabatan_komisi' => 'Anggota',
                'jabatan_struktur' => 'Anggota Komisi I',
                'urutan' => 6,
            ],
            [
                'nama' => 'Resi Yudaningsih, S.E., M.Si., Akt.',
                'nim_nip' => '197306152000032001',
                'email' => 'resi.yudaningsih@senat.test',
                'komisi_id' => 1,
                'jabatan_komisi' => 'Anggota',
                'jabatan_struktur' => 'Anggota Komisi I',
                'urutan' => 7,
            ],
            [
                'nama' => 'Dr. Bagus Yunianto W., S.E., S.Kom., M.M.',
                'nim_nip' => '197506222003121002',
                'email' => 'bagus.yunianto@senat.test',
                'komisi_id' => 1,
                'jabatan_komisi' => 'Anggota',
                'jabatan_struktur' => 'Anggota Komisi I',
                'urutan' => 8,
            ],
            [
                'nama' => 'Nanang Adie Setyawan, S.E., M.M.',
                'nim_nip' => '197904122005011002',
                'email' => 'nanang.adie@senat.test',
                'komisi_id' => 1,
                'jabatan_komisi' => 'Anggota',
                'jabatan_struktur' => 'Anggota Komisi I',
                'urutan' => 9,
            ],

            // Komisi II — Penelitian dan Pengabdian kepada Masyarakat
            [
                'nama' => 'Dr. Jumi, S.Kom., M.Kom.',
                'nim_nip' => '197403162001122001',
                'email' => 'jumi@senat.test',
                'komisi_id' => 2,
                'jabatan_komisi' => 'Ketua',
                'jabatan_struktur' => 'Ketua Komisi II',
                'urutan' => 10,
            ],
            [
                'nama' => 'Fajar Surya Herlambang, S.T., M.T.',
                'nim_nip' => '197808142003121001',
                'email' => 'anggota2@senat.test', // Mempertahankan akun awal
                'komisi_id' => 2,
                'jabatan_komisi' => 'Sekretaris',
                'jabatan_struktur' => 'Sekretaris Komisi II',
                'urutan' => 11,
            ],
            [
                'nama' => 'Yusnan Badruzzaman, S.T., M.Eng.',
                'nim_nip' => '197511192002121001',
                'email' => 'yusnan.badruzzaman@senat.test',
                'komisi_id' => 2,
                'jabatan_komisi' => 'Anggota',
                'jabatan_struktur' => 'Anggota Komisi II',
                'urutan' => 12,
            ],
            [
                'nama' => 'Mohammad Hasanudin, S.E., M.Si.',
                'nim_nip' => '197204202001121002',
                'email' => 'mohammad.hasanudin@senat.test',
                'komisi_id' => 2,
                'jabatan_komisi' => 'Anggota',
                'jabatan_struktur' => 'Anggota Komisi II',
                'urutan' => 13,
            ],
            [
                'nama' => 'Vinda Setya Kartika, S.T., M.T.',
                'nim_nip' => '198207182008122002',
                'email' => 'vinda.setya@senat.test',
                'komisi_id' => 2,
                'jabatan_komisi' => 'Sekretaris BPH / Anggota',
                'jabatan_struktur' => 'Sekretaris BPH / Anggota Komisi II',
                'urutan' => 14,
            ],
            [
                'nama' => 'Ir. Teguh Mulyo Wicaksono, S.Pd., M.Eng.',
                'nim_nip' => '196909241995121001',
                'email' => 'teguh.mulyo@senat.test',
                'komisi_id' => 2,
                'jabatan_komisi' => 'Anggota',
                'jabatan_struktur' => 'Anggota Komisi II',
                'urutan' => 15,
            ],

            // Komisi III — Kelembagaan dan Sumber Daya Manusia (Pengembangan SDM)
            [
                'nama' => 'Ir. Riles Melvy Wattimena, M.T., IPP.',
                'nim_nip' => '196504281992031002',
                'email' => 'riles.wattimena@senat.test',
                'komisi_id' => 3,
                'jabatan_komisi' => 'Ketua',
                'jabatan_struktur' => 'Ketua Komisi III',
                'urutan' => 16,
            ],
            [
                'nama' => 'Iwan Budiyono, S.E., M.Si.Ak',
                'nim_nip' => '197302192000031001',
                'email' => 'iwan.budiyono@senat.test',
                'komisi_id' => 3,
                'jabatan_komisi' => 'Sekretaris',
                'jabatan_struktur' => 'Sekretaris Komisi III',
                'urutan' => 17,
            ],
            [
                'nama' => 'Sahid, S.T., M.T.',
                'nim_nip' => '197103251998021001',
                'email' => 'sahid@senat.test',
                'komisi_id' => 3,
                'jabatan_komisi' => 'Anggota',
                'jabatan_struktur' => 'Anggota Komisi III',
                'urutan' => 18,
            ],
            [
                'nama' => 'Dedi Budi Setyawan, S.T., M.T.',
                'nim_nip' => '197609122002121001',
                'email' => 'dedi.budi@senat.test',
                'komisi_id' => 3,
                'jabatan_komisi' => 'Anggota',
                'jabatan_struktur' => 'Anggota Komisi III',
                'urutan' => 19,
            ],
            [
                'nama' => 'Dr. Muhammad Anif, S.T., M.Eng.',
                'nim_nip' => '197008121997021001',
                'email' => 'muhammad.anif@senat.test',
                'komisi_id' => 3,
                'jabatan_komisi' => 'Anggota',
                'jabatan_struktur' => 'Anggota Komisi III',
                'urutan' => 20,
            ],
            [
                'nama' => 'Septian Yudha Kusuma, S.E., M.Si.Ak',
                'nim_nip' => '198509172010121003',
                'email' => 'septian.yudha@senat.test',
                'komisi_id' => 3,
                'jabatan_komisi' => 'Anggota',
                'jabatan_struktur' => 'Anggota Komisi III',
                'urutan' => 21,
            ],

            // Komisi IV — Pengembangan dan Kerja Sama
            [
                'nama' => 'Dr. Ir. FX Arif Wahyudianto, S.T., M.T., IPM.',
                'nim_nip' => '196605151992031002',
                'email' => 'arif.wahyudianto@senat.test',
                'komisi_id' => 4,
                'jabatan_komisi' => 'Ketua',
                'jabatan_struktur' => 'Ketua Komisi IV',
                'urutan' => 22,
            ],
            [
                'nama' => 'Alvianita Gunawan Putri, S.E., M.Acc., Ak., CA., CPA., ASEAN CPA.',
                'nim_nip' => '198701142014042001',
                'email' => 'alvianita.putri@senat.test',
                'komisi_id' => 4,
                'jabatan_komisi' => 'Sekretaris',
                'jabatan_struktur' => 'Sekretaris Komisi IV',
                'urutan' => 23,
            ],
            [
                'nama' => 'Prof. Dr. Ir. Kurnianingsih, S.T., M.T.',
                'nim_nip' => '197708022002122001',
                'email' => 'kurnianingsih@senat.test',
                'komisi_id' => 4,
                'jabatan_komisi' => 'Anggota',
                'jabatan_struktur' => 'Anggota Komisi IV',
                'urutan' => 24,
            ],
            [
                'nama' => 'Ir. Abdul Syukur Fauzi, S.T., M.T.',
                'nim_nip' => '196803201994031001',
                'email' => 'abdul.syukur@senat.test',
                'komisi_id' => 4,
                'jabatan_komisi' => 'Anggota',
                'jabatan_struktur' => 'Anggota Komisi IV',
                'urutan' => 25,
            ],
            [
                'nama' => 'Leily Fatmawati, S.T., M.T.',
                'nim_nip' => '197805122005012002',
                'email' => 'leily.fatmawati@senat.test',
                'komisi_id' => 4,
                'jabatan_komisi' => 'Anggota',
                'jabatan_struktur' => 'Anggota Komisi IV',
                'urutan' => 26,
            ],
            [
                'nama' => 'Iman Mujiarto, S.T., M.T.',
                'nim_nip' => '197003181997021002',
                'email' => 'iman.mujiarto@senat.test',
                'komisi_id' => 4,
                'jabatan_komisi' => 'Anggota',
                'jabatan_struktur' => 'Anggota Komisi IV',
                'urutan' => 27,
            ],

            // Komisi V — Komisi Etik (Etika)
            [
                'nama' => 'Drs. Suroso, M.Sc.',
                'nim_nip' => '196308121990031001',
                'email' => 'suroso@senat.test',
                'komisi_id' => 5,
                'jabatan_komisi' => 'Ketua',
                'jabatan_struktur' => 'Ketua Komisi V',
                'urutan' => 28,
            ],
            [
                'nama' => 'Achmad Zaenuddin, S.E., M.Si.',
                'nim_nip' => '197109251999031001',
                'email' => 'achmad.zaenuddin@senat.test',
                'komisi_id' => 5,
                'jabatan_komisi' => 'Sekretaris',
                'jabatan_struktur' => 'Sekretaris Komisi V',
                'urutan' => 29,
            ],
            [
                'nama' => 'Dr. Garup Lambang Goro, S.T., M.T.',
                'nim_nip' => '197604152002121002',
                'email' => 'garup.lambang@senat.test',
                'komisi_id' => 5,
                'jabatan_komisi' => 'Anggota',
                'jabatan_struktur' => 'Anggota Komisi V',
                'urutan' => 30,
            ],
        ];

        // 5. Eksekusi Simpan User, Enrollment & Struktur Organisasi
        foreach ($anggotaList as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'nim_nip' => $data['nim_nip'],
                    'nama' => $data['nama'],
                    'role' => 'anggota',
                    'password' => 'password',
                ]
            );

            // Buat / Update Enrollment
            $enrollment = Enrollment::updateOrCreate(
                [
                    'id_user' => $user->id_user,
                    'id_komisi' => $data['komisi_id'],
                    'id_periode_senat' => $periode->id_periode_senat,
                ],
                [
                    'jabatan_komisi' => $data['jabatan_komisi'],
                ]
            );

            // Buat / Update Struktur Organisasi
            StrukturOrganisasi::updateOrCreate(
                [
                    'id_enrollment' => $enrollment->id_enrollment,
                ],
                [
                    'jabatan' => $data['jabatan_struktur'],
                    'periode' => $periode->nama_periode,
                    'urutan_bagan' => $data['urutan'],
                ]
            );
        }
    }
}

