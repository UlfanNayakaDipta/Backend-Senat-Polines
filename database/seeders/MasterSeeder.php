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
        $periode = PeriodeSenat::firstOrCreate(
            ['nama_periode' => '2026–2029'],
            ['tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2029-08-31']
        );

        $komisiDefs = [
            1 => ['nama_komisi' => 'Komisi I', 'deskripsi' => 'Komisi Pendidikan dan Pengajaran'],
            2 => ['nama_komisi' => 'Komisi II', 'deskripsi' => 'Komisi Penelitian dan Pengabdian kepada Masyarakat'],
            3 => ['nama_komisi' => 'Komisi III', 'deskripsi' => 'Komisi Kelembagaan dan Sumber Daya Manusia (Pengembangan SDM)'],
            4 => ['nama_komisi' => 'Komisi IV', 'deskripsi' => 'Komisi Pengembangan dan Kerja Sama'],
            5 => ['nama_komisi' => 'Komisi V', 'deskripsi' => 'Komisi Etik'],
            6 => ['nama_komisi' => 'BP (Badan Pekerja)', 'deskripsi' => 'Koordinasi kerja antar-komisi, penyusunan rancangan agenda sidang, dan penyiapan berkas persidangan.'],
        ];
        $komisis = collect($komisiDefs)
            ->map(fn ($desk, $id) => Komisi::updateOrCreate(['id_komisi' => $id], $desk))
            ->values();

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
