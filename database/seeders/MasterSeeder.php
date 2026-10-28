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
            ['nama_periode' => '2025/2026'],
            ['tanggal_mulai' => '2025-09-01', 'tanggal_selesai' => '2026-08-31']
        );

        $komisis = collect(['Komisi A' => 'Bidang Akademik', 'Komisi B' => 'Bidang Kemahasiswaan', 'Komisi C' => 'Bidang Sarana dan Prasarana'])
            ->map(fn ($desk, $nama) => Komisi::firstOrCreate(['nama_komisi' => $nama], ['deskripsi' => $desk]))
            ->values();

        foreach (['Akademik', 'Fasilitas', 'Kemahasiswaan', 'Lainnya'] as $k) {
            KategoriAspirasi::firstOrCreate(['nama_kategori' => $k]);
        }

        foreach (['Rapat Pleno', 'Rapat Komisi', 'Rapat Koordinasi'] as $j) {
            JenisRapat::firstOrCreate(['nama_jenis' => $j]);
        }

        $jabatan = ['2021001' => 'Ketua', '2021002' => 'Sekretaris', '2021003' => 'Anggota', '2021004' => 'Anggota'];

        foreach (array_keys($jabatan) as $i => $nim) {
            $user = User::where('nim_nip', $nim)->first();
            if (! $user) {
                continue;
            }

            $enrollment = Enrollment::firstOrCreate([
                'id_user' => $user->id_user,
                'id_komisi' => $komisis[$i % $komisis->count()]->id_komisi,
                'id_periode_senat' => $periode->id_periode_senat,
            ], ['jabatan_komisi' => $jabatan[$nim]]);

            StrukturOrganisasi::firstOrCreate(
                ['id_enrollment' => $enrollment->id_enrollment],
                ['jabatan' => $jabatan[$nim], 'periode' => $periode->nama_periode, 'urutan_bagan' => $i + 1]
            );
        }

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
                    'pesan' => 'Aspirasi telah diterima Komisi A. Saat ini sedang dikoordinasikan dengan bagian akademik perihal mekanisme surat izin dispensasi.',
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
