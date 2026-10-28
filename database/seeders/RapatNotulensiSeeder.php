<?php

namespace Database\Seeders;

use App\Models\JenisRapat;
use App\Models\Notulensi;
use App\Models\Rapat;
use App\Models\User;
use Illuminate\Database\Seeder;

class RapatNotulensiSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first() ?? User::create([
            'nama' => 'Administrator Senat',
            'email' => 'admin@senat.test',
            'nim_nip' => '198001012005011001',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $pleno = JenisRapat::firstOrCreate(
            ['nama_jenis' => 'Rapat Pleno']
        );
        $komisi = JenisRapat::firstOrCreate(
            ['nama_jenis' => 'Rapat Komisi']
        );
        $pimpinan = JenisRapat::firstOrCreate(
            ['nama_jenis' => 'Rapat Pimpinan']
        );

        // 1. Pleno Khusus OTK & Perdir Pendidikan 2026
        $rapat1 = Rapat::updateOrCreate(
            ['nama_agenda' => 'Sidang Pleno Khusus Penetapan Draft OTK Senat & Perdir Pendidikan 2026'],
            [
                'id_user' => $admin->id_user,
                'id_jenis_rapat' => $pleno->id_jenis_rapat,
                'tanggal' => '2026-10-09',
                'waktu' => '09:00',
                'tempat' => 'Ruang Sidang Utama Lantai 2 Gedung Direktorat Polines',
                'status' => 'selesai',
            ]
        );

        Notulensi::updateOrCreate(
            ['id_rapat' => $rapat1->id_rapat],
            [
                'id_user' => $admin->id_user,
                'isi_notulensi' => "Jalannya Sidang Pleno Khusus Penetapan Draft OTK Senat 2026 dan Penyelenggaraan Pendidikan Polines 2026:\n1. Pembahasan Struktur Komisi Senat 2026-2029 (5 Komisi: Pendidikan & Pengajaran; Penelitian & Pengabdian; Kelembagaan & SDM; Pengembangan & Kerja Sama; Komisi Etik).\n2. Harmonisasi Peraturan Direktur Penyelenggaraan Pendidikan: Beban Magister Terapan 54-72 SKS, 1 SKS setara 45 jam/semester, penegasan blok vokasi Pasal 25 ayat (7).\n3. Standar operasional prosedur penguji Tugas Akhir melalui SK Direktur serta penetapan SOP pemanfaatan AI dan anti-plagiarisme.\nKeputusan: Naskah disahkan dengan kuorum 88% kehadiran anggota senat.",
                'file_risalah' => 'risalah-pleno-otk-pendidikan-2026.pdf',
                'tanggal_dibuat' => '2026-10-09 13:30:00',
            ]
        );

        // 2. Rapat Koordinasi Komisi I: Penyelarasan Kurikulum MBKM
        $rapat2 = Rapat::updateOrCreate(
            ['nama_agenda' => 'Rapat Koordinasi Komisi I: Penyelarasan Kurikulum MBKM Vokasi 2026/2027'],
            [
                'id_user' => $admin->id_user,
                'id_jenis_rapat' => $komisi->id_jenis_rapat,
                'tanggal' => '2026-10-16',
                'waktu' => '13:30',
                'tempat' => 'Ruang Rapat Senat Gedung Kerjasama',
                'status' => 'selesai',
            ]
        );

        Notulensi::updateOrCreate(
            ['id_rapat' => $rapat2->id_rapat],
            [
                'id_user' => $admin->id_user,
                'isi_notulensi' => "Agenda: Pembahasan implementasi modul ajar kemitraan DUDI dan penyesuaian SKS magang industri bersertifikat.\nCatatan Pembahasan:\n- Evaluasi masukan industri terhadap kompetensi lulusan D4 Teknik Informatika dan Teknik Mesin.\n- Penyesuaian silabus dan rubrik penilaian OBE (Outcome-Based Education).\nAction Item: Tim Pokja Komisi I menyelesaikan finalisasi lampiran RPS dalam 7 hari kerja.",
                'file_risalah' => 'notulen-komisi-1-mbkm-okt2026.pdf',
                'tanggal_dibuat' => '2026-10-16 16:15:00',
            ]
        );

        // 3. Sidang Pleno Evaluasi Akreditasi
        $rapat3 = Rapat::updateOrCreate(
            ['nama_agenda' => 'Sidang Pleno Evaluasi Akreditasi Program Studi Jurusan Teknik Sipil & Elektro'],
            [
                'id_user' => $admin->id_user,
                'id_jenis_rapat' => $pleno->id_jenis_rapat,
                'tanggal' => '2026-10-24',
                'waktu' => '09:00',
                'tempat' => 'Ruang Sidang Utama Lantai 2 Gedung Direktorat Polines',
                'status' => 'selesai',
            ]
        );

        Notulensi::updateOrCreate(
            ['id_rapat' => $rapat3->id_rapat],
            [
                'id_user' => $admin->id_user,
                'isi_notulensi' => "Hasil evaluasi dokumen LED dan LKPS akreditasi:\n1. Kelengkapan instrumen 9 kriteria terpenuhi 94%.\n2. Rekomendasi penambahan peralatan lab otomasi industri sebelum visitasi asesor BAN-PT.\n3. Rapat menyetujui rekomendasi pengalokasian dana pendampingan reakreditasi.",
                'file_risalah' => 'risalah-evaluasi-akreditasi-2026.pdf',
                'tanggal_dibuat' => '2026-10-24 12:00:00',
            ]
        );

        // 4. Sidang Terjadwal Mendatang
        Rapat::updateOrCreate(
            ['nama_agenda' => 'Sidang Pleno Terbuka Pembahasan Kebijakan Anggaran & Sarana Prasarana 2027'],
            [
                'id_user' => $admin->id_user,
                'id_jenis_rapat' => $pleno->id_jenis_rapat,
                'tanggal' => '2026-11-05',
                'waktu' => '08:30',
                'tempat' => 'Auditorium Polines',
                'status' => 'dijadwalkan',
            ]
        );
    }
}
