<?php

namespace Database\Seeders;

use App\Models\Aspirasi;
use App\Models\Enrollment;
use App\Models\JenisRapat;
use App\Models\KategoriAspirasi;
use App\Models\Komisi;
use App\Models\PeriodeSenat;
use App\Models\StrukturOrganisasi;
use App\Models\TanggapanAspirasi;
use App\Models\User;
use Illuminate\Database\Seeder;

class MasterSeeder extends Seeder
{
    public function run(): void
    {
        $periode = PeriodeSenat::updateOrCreate(
            ['nama_periode' => '2026–2030'],
            ['tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2030-08-31']
        );

        $komisiDefs = [
            1 => [
                'kode' => 'KOM-I',
                'nama_komisi' => 'Komisi Pendidikan dan Pengajaran',
                'deskripsi' => 'Kebijakan & norma akademik, penjaminan mutu pembelajaran, kebebasan akademik, pembukaan/penutupan prodi, serta gelar & penghargaan akademik.',
            ],
            2 => [
                'kode' => 'KOM-II',
                'nama_komisi' => 'Komisi Penelitian dan Pengabdian kepada Masyarakat',
                'deskripsi' => 'Ketentuan tata cara penyelenggaraan penelitian, pelaksanaan pengabdian kepada masyarakat, dan pengawasannya.',
            ],
            3 => [
                'kode' => 'KOM-III',
                'nama_komisi' => 'Komisi Kelembagaan dan Sumber Daya Manusia',
                'deskripsi' => 'Pengembangan organisasi jurusan/prodi, karier & mutu dosen/tendik, pedoman kenaikan jabatan akademik, kode etik, dan rancangan OTK Senat.',
            ],
            4 => [
                'kode' => 'KOM-IV',
                'nama_komisi' => 'Komisi Pengembangan dan Kerja Sama',
                'deskripsi' => 'Kerja sama akademik & nonakademik dalam/luar negeri (DUDI, PT lain, lembaga sertifikasi), evaluasi hasil, serta kepatuhan MoU/PKS.',
            ],
            5 => [
                'kode' => 'KOM-V',
                'nama_komisi' => 'Komisi Etik',
                'deskripsi' => 'Ketentuan kode etik dosen/mahasiswa/akademik, pengawasan norma akademik, dan rekomendasi sanksi kepada Direktur.',
            ],
            6 => [
                'kode' => 'BP',
                'nama_komisi' => 'BP (Badan Pekerja)',
                'deskripsi' => 'Penetapan agenda & materi rapat pleno, pembahasan hasil rapat komisi/panja, serta penampungan aspirasi.',
            ],
        ];
        collect($komisiDefs)
            ->each(fn ($def, $id) => Komisi::updateOrCreate(['id_komisi' => $id], $def));

        foreach (['Akademik', 'Fasilitas', 'Kemahasiswaan', 'Lainnya', 'Etik', 'Badan Pekerja (BP)'] as $k) {
            KategoriAspirasi::firstOrCreate(['nama_kategori' => $k]);
        }

        foreach (['Rapat Pleno', 'Rapat Komisi', 'Rapat Koordinasi', 'Rapat Pimpinan', 'Rapat BP (Badan Pekerja)'] as $j) {
            JenisRapat::firstOrCreate(['nama_jenis' => $j]);
        }

        $this->call(OrganisasiSenatSeeder::class);

        $kategoriFasilitas = KategoriAspirasi::where('nama_kategori', 'Fasilitas')->first();
        $kategoriAkademik = KategoriAspirasi::where('nama_kategori', 'Akademik')->first();
        $kategoriKemahasiswaan = KategoriAspirasi::where('nama_kategori', 'Kemahasiswaan')->first();

        $adminUser = User::where('role', 'admin')->first() ?? User::first();
        $mhsUser = User::where('nim_nip', '2021004')->first() ?? $adminUser;

        if ($kategoriFasilitas && $adminUser && $mhsUser) {
            // 1. Aspirasi status pending
            Aspirasi::firstOrCreate(
                ['isi_aspirasi' => 'AC di Lab Jaringan Gedung Serba Guna lantai 2 sering mati saat praktikum berlangsung, mohon perbaikan atau pembersihan filter.'],
                [
                    'id_user' => $mhsUser->id_user,
                    'id_kategori' => $kategoriFasilitas->id_kategori,
                    'status' => 'pending',
                    'anonim' => false,
                    'tanggal_dibuat' => now()->subDays(2),
                ]
            );

            // 2. Aspirasi status diproses dengan tanggapan resmi
            $asp2 = Aspirasi::firstOrCreate(
                ['isi_aspirasi' => 'Permohonan peninjauan jadwal ujian susulan bagi mahasiswa yang sedang mengikuti kompetisi nasional (KMHE / LKS).'],
                [
                    'id_user' => $mhsUser->id_user,
                    'id_kategori' => $kategoriAkademik ? $kategoriAkademik->id_kategori : $kategoriFasilitas->id_kategori,
                    'status' => 'diproses',
                    'anonim' => false,
                    'tanggal_dibuat' => now()->subDays(5),
                ]
            );

            TanggapanAspirasi::firstOrCreate(
                ['id_aspirasi' => $asp2->id_aspirasi],
                [
                    'id_user' => $adminUser->id_user,
                    'pesan' => 'Aspirasi telah diterima Komisi I. Saat ini sedang dikoordinasikan dengan bagian akademik perihal mekanisme surat izin dispensasi.',
                    'tanggal_kirim' => now()->subDays(3),
                ]
            );

            // 3. Aspirasi status selesai dengan tanggapan resmi
            $asp3 = Aspirasi::firstOrCreate(
                ['isi_aspirasi' => 'Usulan perpanjangan jam operasional perpustakaan pusat hingga pukul 18.30 WIB selama masa persiapan pekan ujian akhir semester.'],
                [
                    'id_user' => $mhsUser->id_user,
                    'id_kategori' => $kategoriKemahasiswaan ? $kategoriKemahasiswaan->id_kategori : $kategoriFasilitas->id_kategori,
                    'status' => 'selesai',
                    'anonim' => true,
                    'tanggal_dibuat' => now()->subDays(10),
                ]
            );

            TanggapanAspirasi::firstOrCreate(
                ['id_aspirasi' => $asp3->id_aspirasi],
                [
                    'id_user' => $adminUser->id_user,
                    'pesan' => 'Usulan disetujui. Pihak UPT Perpustakaan telah mengonfirmasi perpanjangan jam layanan hingga 18.30 WIB selama periode persiapan ujian.',
                    'tanggal_kirim' => now()->subDays(7),
                ]
            );
        }
    }
}
