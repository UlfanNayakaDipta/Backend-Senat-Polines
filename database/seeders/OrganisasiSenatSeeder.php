<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\Komisi;
use App\Models\PeriodeSenat;
use App\Models\StrukturOrganisasi;
use App\Models\TugasKomisi;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrganisasiSenatSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Periode Senat 2026–2030 (4 tahun sesuai Pasal 4 & Pasal 12 OTK Senat)
        $periode = PeriodeSenat::where('nama_periode', '2026–2030')->first()
            ?? PeriodeSenat::where('nama_periode', '2026–2029')->first();

        if ($periode) {
            $periode->update([
                'nama_periode' => '2026–2030',
                'tanggal_mulai' => '2026-09-01',
                'tanggal_selesai' => '2030-08-31',
            ]);
        } else {
            $periode = PeriodeSenat::create([
                'nama_periode' => '2026–2030',
                'tanggal_mulai' => '2026-09-01',
                'tanggal_selesai' => '2030-08-31',
            ]);
        }

        // 2. 5 Komisi Tetap Resmi + 1 Badan Pekerja (BP) beserta Tugas Pokok (Pasal 14 & 15 OTK Senat 2026)
        $komisiDefinitions = [
            1 => [
                'kode' => 'KOM-I',
                'nama_komisi' => 'Komisi Pendidikan dan Pengajaran',
                'deskripsi' => 'Kebijakan & norma akademik, penjaminan mutu pembelajaran, kebebasan akademik, pembukaan/penutupan prodi, serta gelar & penghargaan akademik.',
                'tugas' => [
                    'Merumuskan dan memberi pertimbangan kebijakan serta norma akademik dalam penyelenggaraan pendidikan dan pembelajaran.',
                    'Mengawasi penerapan kebijakan akademik dan pelaksanaan penjaminan mutu pendidikan.',
                    'Memberi pertimbangan dan rekomendasi penyelenggaraan pembelajaran, kebebasan akademik, mimbar akademik, dan otonomi keilmuan.',
                    'Memberi pertimbangan kepada Direktur terkait pembukaan/penutupan jurusan dan program studi serta pemberian/pencabutan gelar dan penghargaan akademik.',
                ],
            ],
            2 => [
                'kode' => 'KOM-II',
                'nama_komisi' => 'Komisi Penelitian dan Pengabdian kepada Masyarakat',
                'deskripsi' => 'Ketentuan tata cara penyelenggaraan penelitian, pelaksanaan pengabdian kepada masyarakat, dan pengawasannya.',
                'tugas' => [
                    'Memberi pertimbangan dalam penyusunan ketentuan tata cara penyelenggaraan penelitian.',
                    'Memberi pertimbangan dalam penyusunan ketentuan tata cara pelaksanaan pengabdian kepada masyarakat.',
                    'Melakukan pengawasan terhadap penyelenggaraan penelitian dan pengabdian kepada masyarakat.',
                ],
            ],
            3 => [
                'kode' => 'KOM-III',
                'nama_komisi' => 'Komisi Kelembagaan dan Sumber Daya Manusia',
                'deskripsi' => 'Pengembangan organisasi jurusan/prodi, karier & mutu dosen/tendik, pedoman kenaikan jabatan akademik, kode etik, dan rancangan OTK Senat.',
                'tugas' => [
                    'Memberi pertimbangan pengembangan organisasi jurusan/prodi serta pengembangan karier, penilaian prestasi, dan mutu dosen maupun tenaga kependidikan.',
                    'Menyusun rekomendasi kualifikasi jabatan tugas tambahan, alih tugas tendik ke dosen, serta persyaratan dosen tidak tetap dan dosen praktisi/industri.',
                    'Menyusun pedoman pertimbangan kenaikan jabatan akademik (Asisten Ahli hingga Profesor) dan penilaian dosen berprestasi.',
                    'Menyusun norma/kode etik sivitas akademika, pedoman benturan kepentingan anggota Senat, serta mengoordinasikan rancangan perubahan OTK Senat.',
                ],
            ],
            4 => [
                'kode' => 'KOM-IV',
                'nama_komisi' => 'Komisi Pengembangan dan Kerja Sama',
                'deskripsi' => 'Kerja sama akademik & nonakademik dalam/luar negeri (DUDI, PT lain, lembaga sertifikasi), evaluasi hasil, serta kepatuhan MoU/PKS.',
                'tugas' => [
                    'Memberi pertimbangan perencanaan, pengembangan, dan pelaksanaan kerja sama akademik maupun nonakademik dengan mitra dalam dan luar negeri (DUDI, perguruan tinggi lain, lembaga pelatihan/sertifikasi).',
                    'Memberi rekomendasi penataan, pemantauan, dan penilaian hasil kerja sama sesuai prinsip Statuta.',
                    'Memastikan setiap kerja sama dituangkan dalam nota kesepahaman (MoU) dan/atau perjanjian kerja sama (PKS) sesuai ketentuan.',
                ],
            ],
            5 => [
                'kode' => 'KOM-V',
                'nama_komisi' => 'Komisi Etik',
                'deskripsi' => 'Ketentuan kode etik dosen/mahasiswa/akademik, pengawasan norma akademik, dan rekomendasi sanksi kepada Direktur.',
                'tugas' => [
                    'Memberi pertimbangan penyusunan ketentuan kode etik dosen, kode etik mahasiswa, etika akademik, beserta sanksinya.',
                    'Memberi pertimbangan dan rekomendasi dalam pengawasan penerapan norma akademik dan kode etik sivitas akademika.',
                    'Memberi pertimbangan dan rekomendasi penjatuhan sanksi atas pelanggaran norma, etika, dan peraturan akademik kepada Direktur.',
                ],
            ],
            6 => [
                'kode' => 'BP',
                'nama_komisi' => 'BP (Badan Pekerja)',
                'deskripsi' => 'Penetapan agenda & materi rapat pleno, pembahasan hasil rapat komisi/panja, serta penampungan aspirasi.',
                'tugas' => [
                    'Menetapkan agenda rapat pleno dan menyiapkan materi pembahasan rapat pleno.',
                    'Menerima dan membahas hasil kerja rapat komisi, rapat khusus, dan rapat panitia kerja.',
                    'Menerima dan membahas aspirasi yang berkembang.',
                ],
            ],
        ];

        foreach ($komisiDefinitions as $id => $def) {
            $tugasList = $def['tugas'];
            unset($def['tugas']);

            $komisi = Komisi::updateOrCreate(['id_komisi' => $id], $def);

            TugasKomisi::where('id_komisi', $komisi->id_komisi)->delete();
            foreach ($tugasList as $urutan => $uraian) {
                TugasKomisi::create([
                    'id_komisi' => $komisi->id_komisi,
                    'uraian_tugas' => $uraian,
                    'urutan' => $urutan + 1,
                ]);
            }
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

        // 4. Daftar Unik Seluruh Anggota Senat (30 Tokoh)
        $userProfiles = [
            '197101172003121001' => ['nama' => 'Dr. Sukamto, S.Kom., M.T.', 'email' => 'ketua@senat.test'],
            '197607202008011011' => ['nama' => 'Andi Setiawan, S.E., M.M.', 'email' => 'sekretaris@senat.test'],

            // Komisi I — Pendidikan & Pengajaran
            '196810252000121001' => ['nama' => 'Tri Raharjo Yudantoro, S.Kom., M.Kom.', 'email' => 'tri.yudantoro@senat.test'],
            '198410192010121003' => ['nama' => 'Iwan Budiyono, S.E., M.Si., Ak.', 'email' => 'iwan.budiyono@senat.test'],
            '196908141998022001' => ['nama' => 'Prof. Dr. Ir. Dwiana Hendrawati, S.T., M.T.', 'email' => 'dwiana.hendrawati@senat.test'],
            '198101312006041003' => ['nama' => 'Yusmar Ardhi Hidayat, S.E., M.Si., Ph.D.', 'email' => 'yusmar.ardhi@senat.test'],
            '197211021998022001' => ['nama' => 'Resi Yudaningsih, S.E., M.Si., Akt.', 'email' => 'resi.yudaningsih@senat.test'],
            '199106262019031017' => ['nama' => 'Dr. Bagus Yunianto W., S.E., S.Kom., M.M.', 'email' => 'bagus.yunianto@senat.test'],
            '196511051994031002' => ['nama' => 'Drs. Suroso, M.Sc.', 'email' => 'suroso@senat.test'],

            // Komisi II — Penelitian & Pengabdian
            '197006142005012001' => ['nama' => 'Dr. Jumi, S.Kom., M.Kom.', 'email' => 'jumi@senat.test'],
            '199009032019031016' => ['nama' => 'Septian Yudha Kusuma, S.E., M.Si., Ak.', 'email' => 'septian.yudha@senat.test'],
            '197503132006041001' => ['nama' => 'Yusnan Badruzzaman, S.T., M.Eng.', 'email' => 'yusnan.badruzzaman@senat.test'],
            '197608012000121001' => ['nama' => 'Mohammad Hasanudin, S.E., M.Si.', 'email' => 'mohammad.hasanudin@senat.test'],
            '199108112019032024' => ['nama' => 'Vinda Setya Kartika, S.T., M.T.', 'email' => 'vinda.setya@senat.test'],
            '199012022019031014' => ['nama' => 'Ir. Teguh Mulyo Wicaksono, S.Pd., M.Eng.', 'email' => 'teguh.mulyo@senat.test'],

            // Komisi III — Pengembangan SDM
            '196709031994031001' => ['nama' => 'Ir. Riles Melvy Wattimena, M.T., IPP.', 'email' => 'riles.wattimena@senat.test'],
            '198701252014042001' => ['nama' => 'Anis Roihatin, S.T., M.T.', 'email' => 'anggota1@senat.test'],
            '197005121996011001' => ['nama' => 'Sahid, S.T., M.T.', 'email' => 'sahid@senat.test'],
            '197712062001121001' => ['nama' => 'Dedi Budi Setyawan, S.T., M.T.', 'email' => 'dedi.budi@senat.test'],
            '199107102019031017' => ['nama' => 'Nanang Adie Setyawan, S.E., M.M.', 'email' => 'nanang.adie@senat.test'],
            '197710092005011001' => ['nama' => 'Dr. Muhammad Anif, S.T., M.Eng.', 'email' => 'muhammad.anif@senat.test'],

            // Komisi IV — Pengembangan & Kerja Sama
            '197412111994031001' => ['nama' => 'Dr. Ir. FX Arif Wahyudianto, S.T., M.T., IPM.', 'email' => 'arif.wahyudianto@senat.test'],
            '199201072019032024' => ['nama' => 'Alvianita Gunawan Putri, S.E., M.Acc., Ak., CA., CPA., Asean CPA.', 'email' => 'alvianita.putri@senat.test'],
            '197904262003122002' => ['nama' => 'Prof. Dr. Ir. Kurnianingsih, S.T., M.T.', 'email' => 'kurnianingsih@senat.test'],
            '197005051999031002' => ['nama' => 'Ir. Abdul Syukur Fauzi, S.T., M.T.', 'email' => 'abdul.syukur@senat.test'],
            '197406122005012001' => ['nama' => 'Leily Fatmawati, S.T., M.T.', 'email' => 'leily.fatmawati@senat.test'],
            '197009172005011003' => ['nama' => 'Iman Mujiarto, S.T., M.T.', 'email' => 'iman.mujiarto@senat.test'],

            // Komisi V — Etika
            '197206291999031001' => ['nama' => 'Fajar Surya Herlambang, S.T., M.T.', 'email' => 'anggota2@senat.test'],
            '197305271999031002' => ['nama' => 'Achmad Zaenuddin, S.E., M.Si.', 'email' => 'achmad.zaenuddin@senat.test'],
            '196708121990031003' => ['nama' => 'Dr. Garup Lambang Goro, S.T., M.T.', 'email' => 'garup.lambang@senat.test'],
        ];

        $usersByNip = [];
        foreach ($userProfiles as $nip => $profile) {
            $user = User::where('nim_nip', $nip)->first()
                ?? User::where('email', $profile['email'])->first();

            $fotoProfil = 'images/members/' . $nip . '.jpg';

            if ($user) {
                $nimTakenByOther = User::where('nim_nip', $nip)
                    ->where('id_user', '!=', $user->id_user)
                    ->exists();

                $updates = [
                    'nama' => $profile['nama'],
                    'foto_profil' => $fotoProfil,
                ];
                if (! $nimTakenByOther) {
                    $updates['nim_nip'] = $nip;
                }
                $user->update($updates);
            } else {
                $user = User::create([
                    'nim_nip' => $nip,
                    'nama' => $profile['nama'],
                    'email' => $profile['email'],
                    'role' => 'anggota',
                    'password' => 'password',
                    'foto_profil' => $fotoProfil,
                ]);
            }

            $usersByNip[$nip] = $user;
        }

        // 5. Daftar Penugasan Jabatan (User dapat merangkap di beberapa unit/komisi)
        $assignments = [
            // Pimpinan Senat
            ['nip' => '197101172003121001', 'komisi_id' => 5, 'jabatan_komisi' => 'Ketua Senat', 'jabatan_struktur' => 'Ketua Senat'],
            ['nip' => '197607202008011011', 'komisi_id' => 5, 'jabatan_komisi' => 'Sekretaris Senat', 'jabatan_struktur' => 'Sekretaris Senat'],

            // Komisi I — Pendidikan & Pengajaran
            ['nip' => '196810252000121001', 'komisi_id' => 1, 'jabatan_komisi' => 'Ketua', 'jabatan_struktur' => 'Ketua Komisi I'],
            ['nip' => '198410192010121003', 'komisi_id' => 1, 'jabatan_komisi' => 'Sekretaris', 'jabatan_struktur' => 'Sekretaris Komisi I'],
            ['nip' => '196908141998022001', 'komisi_id' => 1, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota Komisi I'],
            ['nip' => '198101312006041003', 'komisi_id' => 1, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota Komisi I'],
            ['nip' => '197211021998022001', 'komisi_id' => 1, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota Komisi I'],
            ['nip' => '199106262019031017', 'komisi_id' => 1, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota Komisi I'],
            ['nip' => '196511051994031002', 'komisi_id' => 1, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota Komisi I'],

            // Komisi II — Penelitian & Pengabdian
            ['nip' => '197006142005012001', 'komisi_id' => 2, 'jabatan_komisi' => 'Ketua', 'jabatan_struktur' => 'Ketua Komisi II'],
            ['nip' => '199009032019031016', 'komisi_id' => 2, 'jabatan_komisi' => 'Sekretaris', 'jabatan_struktur' => 'Sekretaris Komisi II'],
            ['nip' => '197503132006041001', 'komisi_id' => 2, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota Komisi II'],
            ['nip' => '197608012000121001', 'komisi_id' => 2, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota Komisi II'],
            ['nip' => '199108112019032024', 'komisi_id' => 2, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota Komisi II'],
            ['nip' => '199012022019031014', 'komisi_id' => 2, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota Komisi II'],

            // Komisi III — Pengembangan SDM
            ['nip' => '196709031994031001', 'komisi_id' => 3, 'jabatan_komisi' => 'Ketua', 'jabatan_struktur' => 'Ketua Komisi III'],
            ['nip' => '198701252014042001', 'komisi_id' => 3, 'jabatan_komisi' => 'Sekretaris', 'jabatan_struktur' => 'Sekretaris Komisi III'],
            ['nip' => '197005121996011001', 'komisi_id' => 3, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota Komisi III'],
            ['nip' => '197712062001121001', 'komisi_id' => 3, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota Komisi III'],
            ['nip' => '199107102019031017', 'komisi_id' => 3, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota Komisi III'],
            ['nip' => '197710092005011001', 'komisi_id' => 3, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota Komisi III'],

            // BP (Badan Pekerja)
            ['nip' => '197607202008011011', 'komisi_id' => 6, 'jabatan_komisi' => 'Ketua', 'jabatan_struktur' => 'Ketua BP'],
            ['nip' => '199108112019032024', 'komisi_id' => 6, 'jabatan_komisi' => 'Sekretaris', 'jabatan_struktur' => 'Sekretaris BP'],
            ['nip' => '196810252000121001', 'komisi_id' => 6, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota BP'],
            ['nip' => '198410192010121003', 'komisi_id' => 6, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota BP'],
            ['nip' => '197006142005012001', 'komisi_id' => 6, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota BP'],
            ['nip' => '199009032019031016', 'komisi_id' => 6, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota BP'],
            ['nip' => '196709031994031001', 'komisi_id' => 6, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota BP'],
            ['nip' => '198701252014042001', 'komisi_id' => 6, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota BP'],
            ['nip' => '197412111994031001', 'komisi_id' => 6, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota BP'],
            ['nip' => '199201072019032024', 'komisi_id' => 6, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota BP'],
            ['nip' => '197206291999031001', 'komisi_id' => 6, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota BP'],
            ['nip' => '197305271999031002', 'komisi_id' => 6, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota BP'],
            ['nip' => '197101172003121001', 'komisi_id' => 6, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota BP'],

            // Komisi IV — Pengembangan & Kerja Sama
            ['nip' => '197412111994031001', 'komisi_id' => 4, 'jabatan_komisi' => 'Ketua', 'jabatan_struktur' => 'Ketua Komisi IV'],
            ['nip' => '199201072019032024', 'komisi_id' => 4, 'jabatan_komisi' => 'Sekretaris', 'jabatan_struktur' => 'Sekretaris Komisi IV'],
            ['nip' => '197904262003122002', 'komisi_id' => 4, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota Komisi IV'],
            ['nip' => '197005051999031002', 'komisi_id' => 4, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota Komisi IV'],
            ['nip' => '197406122005012001', 'komisi_id' => 4, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota Komisi IV'],
            ['nip' => '197009172005011003', 'komisi_id' => 4, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota Komisi IV'],

            // Komisi V — Etika
            ['nip' => '197206291999031001', 'komisi_id' => 5, 'jabatan_komisi' => 'Ketua', 'jabatan_struktur' => 'Ketua Komisi V'],
            ['nip' => '197305271999031002', 'komisi_id' => 5, 'jabatan_komisi' => 'Sekretaris', 'jabatan_struktur' => 'Sekretaris Komisi V'],
            ['nip' => '196708121990031003', 'komisi_id' => 5, 'jabatan_komisi' => 'Anggota (Direktur Polines)', 'jabatan_struktur' => 'Anggota Komisi V (Direktur Polines)'],
            ['nip' => '197607202008011011', 'komisi_id' => 5, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota Komisi V'],
            ['nip' => '197101172003121001', 'komisi_id' => 5, 'jabatan_komisi' => 'Anggota', 'jabatan_struktur' => 'Anggota Komisi V'],
        ];

        // Bersihkan enrollment contoh lama berbasis NIM placeholder 2021*
        $mockUserIds = User::where('nim_nip', 'like', '2021%')->pluck('id_user');
        if ($mockUserIds->isNotEmpty()) {
            $oldEnrollmentIds = Enrollment::whereIn('id_user', $mockUserIds)->pluck('id_enrollment');
            if ($oldEnrollmentIds->isNotEmpty()) {
                StrukturOrganisasi::whereIn('id_enrollment', $oldEnrollmentIds)->delete();
                Enrollment::whereIn('id_enrollment', $oldEnrollmentIds)->delete();
            }
        }

        // 6. Eksekusi Simpan Enrollment & Struktur Organisasi
        $activeEnrollmentIds = [];
        foreach ($assignments as $index => $item) {
            $user = $usersByNip[$item['nip']];

            $enrollment = Enrollment::updateOrCreate(
                [
                    'id_user' => $user->id_user,
                    'id_komisi' => $item['komisi_id'],
                    'id_periode_senat' => $periode->id_periode_senat,
                    'jabatan_komisi' => $item['jabatan_komisi'],
                ]
            );

            $activeEnrollmentIds[] = $enrollment->id_enrollment;

            StrukturOrganisasi::updateOrCreate(
                [
                    'id_enrollment' => $enrollment->id_enrollment,
                ],
                [
                    'jabatan' => $item['jabatan_struktur'],
                    'periode' => $periode->nama_periode,
                    'urutan_bagan' => $index + 1,
                ]
            );
        }

        // Hapus enrollment usang pada periode ini agar seeder tetap idempotent
        $staleEnrollmentIds = Enrollment::where('id_periode_senat', $periode->id_periode_senat)
            ->whereNotIn('id_enrollment', $activeEnrollmentIds)
            ->pluck('id_enrollment');

        if ($staleEnrollmentIds->isNotEmpty()) {
            StrukturOrganisasi::whereIn('id_enrollment', $staleEnrollmentIds)->delete();
            Enrollment::whereIn('id_enrollment', $staleEnrollmentIds)->delete();
        }
    }
}
