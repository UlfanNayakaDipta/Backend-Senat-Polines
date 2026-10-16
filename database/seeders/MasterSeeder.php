<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\JenisRapat;
use App\Models\KategoriAspirasi;
use App\Models\Komisi;
use App\Models\PeriodeSenat;
use App\Models\StrukturOrganisasi;
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
    }
}
