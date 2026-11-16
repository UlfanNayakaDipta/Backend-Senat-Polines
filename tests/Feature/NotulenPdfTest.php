<?php

namespace Tests\Feature;

use App\Models\AnggotaRapat;
use App\Models\Enrollment;
use App\Models\JenisRapat;
use App\Models\Notulensi;
use App\Models\PeriodeSenat;
use App\Models\Rapat;
use App\Models\StrukturOrganisasi;
use App\Models\User;
use App\Services\NotulenPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class NotulenPdfTest extends TestCase
{
    use RefreshDatabase;

    private const CONTOH = <<<'MD'
## Identitas
Pimpinan Rapat: Dr. Sukamto, S.Kom., M.T. (Ketua Senat)
Peserta Rapat: Para anggota Senat, Direktur, dan para Wakil Direktur

## Agenda Rapat
1. Peraturan Direktur tentang Penyelenggaraan Pendidikan 2026.
2. Lain-lain.

## Hasil Pembahasan
### A. Bab IV Tata Cara Penyelenggaraan Perkuliahan
- Pasal 15 Magister Terapan ditetapkan minimum 36 SKS.
- Dr. Garup Lambang Goro, S.T., M.T. mengoreksi penulisan rujukan.
#### Kesepakatan
- Pasal 15 minimum 36 SKS dan masa studi minimum 3 semester.

### B. Bab VII Penilaian Proses dan Hasil Belajar
- Iwan Budiyono, S.E., M.Si.Ak. meminta penilaian sumatif dilaporkan ke PD Dikti.
#### Kesepakatan
- Penambahan redaksi Pasal 31 mengenai penguji tugas akhir.

## Kesimpulan Rapat
1. Beban belajar Magister Terapan ditetapkan 36 SKS.
2. Tata cara penyelenggaraan perkuliahan diselaraskan dengan Statuta Polines.

## Penutup Rapat
- Ketua Senat menyampaikan apresiasi kepada seluruh anggota.
- Rapat ditutup pada pukul 15.38 WIB.
MD;

    private function buatRapat(): Rapat
    {
        $user = User::factory()->create([
            'nama' => 'Dr. Sukamto, S.Kom., M.T.',
            'nim_nip' => '197101172003121001',
            'role' => 'ketua_senat',
        ]);

        $jenis = JenisRapat::create(['nama_jenis' => 'Rapat Pleno']);

        $rapat = Rapat::create([
            'id_user' => $user->id_user,
            'id_jenis_rapat' => $jenis->id_jenis_rapat,
            'nama_agenda' => 'RAPAT PLENO KHUSUS',
            'tanggal' => '2026-10-09',
            'waktu' => '13:00',
            'tempat' => 'AD.II.16',
            'status' => 'selesai',
        ]);

        $hadir = User::factory()->count(3)->create();
        foreach ($hadir as $anggota) {
            AnggotaRapat::create([
                'id_rapat' => $rapat->id_rapat,
                'id_user' => $anggota->id_user,
                'status_kehadiran' => 'hadir',
            ]);
        }

        AnggotaRapat::create([
            'id_rapat' => $rapat->id_rapat,
            'id_user' => User::factory()->create()->id_user,
            'status_kehadiran' => 'izin',
        ]);

        $periode = PeriodeSenat::create([
            'nama_periode' => '2024-2028',
            'tanggal_mulai' => '2024-01-01',
            'tanggal_selesai' => '2028-12-31',
        ]);

        $komisi = \App\Models\Komisi::create(['nama_komisi' => 'Komisi I']);

        $enrollment = Enrollment::create([
            'id_user' => $user->id_user,
            'id_komisi' => $komisi->id_komisi,
            'id_periode_senat' => $periode->id_periode_senat,
            'jabatan_komisi' => 'Ketua Senat',
        ]);

        StrukturOrganisasi::create([
            'id_enrollment' => $enrollment->id_enrollment,
            'jabatan' => 'Ketua Senat',
            'periode' => '2024-2028',
            'urutan_bagan' => 1,
        ]);

        return $rapat;
    }

    private function buatNotulensi(): Notulensi
    {
        $rapat = $this->buatRapat();

        return Notulensi::create([
            'id_rapat' => $rapat->id_rapat,
            'id_user' => $rapat->id_user,
            'isi_notulensi' => self::CONTOH,
            'status' => 'draf',
        ]);
    }

    public function test_urai_memecah_markdown_menjadi_bagian_notulen(): void
    {
        $isi = (new NotulenPdfService())->urai(self::CONTOH);

        $this->assertSame('Dr. Sukamto, S.Kom., M.T. (Ketua Senat)', $isi['identitas']['Pimpinan Rapat']);
        $this->assertCount(2, $isi['agenda']);
        $this->assertSame('Peraturan Direktur tentang Penyelenggaraan Pendidikan 2026.', $isi['agenda'][0]['teks']);
        $this->assertCount(2, $isi['pembahasan']);
        $this->assertSame('A. Bab IV Tata Cara Penyelenggaraan Perkuliahan', $isi['pembahasan'][0]['judul']);
        $this->assertCount(2, $isi['pembahasan'][0]['poin']);
        $this->assertCount(1, $isi['pembahasan'][0]['kesepakatan']);
        $this->assertCount(2, $isi['kesimpulan']);
        $this->assertCount(2, $isi['penutup']);
    }

    public function test_urai_tidak_membuang_draf_lama_tanpa_heading(): void
    {
        $isi = (new NotulenPdfService())->urai(
            "Hasil evaluasi dokumen LED dan LKPS akreditasi:\n1. Kelengkapan instrumen 9 kriteria terpenuhi 94%.\n2. Rekomendasi penambahan peralatan lab."
        );

        $this->assertCount(1, $isi['pembahasan']);
        $this->assertSame('Hasil Rapat', $isi['pembahasan'][0]['judul']);
        $this->assertCount(3, $isi['pembahasan'][0]['poin']); // kalimat pembuka + 2 butir
        $this->assertStringContainsString('Kelengkapan instrumen', $isi['pembahasan'][0]['poin'][1]['teks']);
        $this->assertSame([], $isi['identitas']);
    }

    public function test_buat_menghasilkan_berkas_pdf_dan_menyimpan_path(): void
    {
        $notulensi = $this->buatNotulensi();

        $path = (new NotulenPdfService())->buat($notulensi);

        $absolut = public_path($path);
        $this->assertFileExists($absolut);
        $this->assertStringStartsWith('%PDF-', (string) file_get_contents($absolut, false, null, 0, 5));
        $this->assertSame($path, $notulensi->fresh()->berkas_pdf);
        $this->assertNotNull($notulensi->fresh()->pdf_dibuat);

        File::delete($absolut);
    }

    public function test_pdf_berisi_identitas_dan_hasil_rapat(): void
    {
        $notulensi = $this->buatNotulensi();
        $path = public_path((new NotulenPdfService())->buat($notulensi));

        $teks = $this->teksPdf($path);

        $this->assertStringContainsString('NOTULEN RAPAT', $teks);
        $this->assertStringContainsString('No. FPM', $teks);
        $this->assertStringContainsString('RAPAT PLENO KHUSUS', $teks);
        $this->assertStringContainsString('Jumat, 09 Oktober 2026', $teks);
        $this->assertStringContainsString('AD.II.16', $teks);
        $this->assertStringContainsString('4 Orang', $teks);   // diundang
        $this->assertStringContainsString('3 Orang', $teks);   // hadir
        $this->assertStringContainsString('Bab IV Tata Cara Penyelenggaraan Perkuliahan', $teks);
        $this->assertStringContainsString('Kesepakatan', $teks);
        $this->assertStringContainsString('Kesimpulan Rapat', $teks);
        $this->assertStringContainsString('Penutup Rapat', $teks);
        $this->assertStringContainsString('Dr. Sukamto, S.Kom., M.T.', $teks);
        $this->assertStringContainsString('197101172003121001', $teks);

        // Lampiran & daftar hadir bukan bagian dokumen ini.
        $this->assertStringNotContainsString('LAMPIRAN', $teks);
        $this->assertStringNotContainsString('Daftar Hadir', $teks);

        File::delete($path);
    }

    public function test_nomor_halaman_tiap_lembar_berurutan(): void
    {
        $notulensi = $this->buatNotulensi();

        $notulensi->update([
            'isi_notulensi' => self::CONTOH . "\n\n## Hasil Pembahasan\n### Z. Butir Panjang\n"
                . implode("\n", array_fill(0, 60, '- Butir pembahasan panjang untuk menguji pembagian halaman notulen rapat senat.')),
        ]);

        $path = public_path((new NotulenPdfService())->buat($notulensi));
        $teks = $this->teksPdf($path);

        preg_match_all('/Halaman\s+(\d+)\/(\d+)/', $teks, $m);

        $this->assertGreaterThan(1, count($m[1]), 'notulen uji harus lebih dari satu halaman');

        $total = (int) $m[2][0];
        foreach ($m[1] as $i => $nomor) {
            // Setiap lembar mencetak nomornya sendiri, bukan nomor halaman terakhir.
            $this->assertSame($i + 1, (int) $nomor, "kop halaman ke-" . ($i + 1) . " salah");
            $this->assertSame($total, (int) $m[2][$i]);
        }

        File::delete($path);
    }

    public function test_endpoint_pdf_mengunduh_berkas(): void
    {
        $notulensi = $this->buatNotulensi();

        $res = $this->get("/api/notulensi/{$notulensi->id_notulensi}/pdf");

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));

        File::delete(public_path((string) $notulensi->fresh()->berkas_pdf));
    }

    public function test_endpoint_pdf_404_untuk_notulensi_tidak_ada(): void
    {
        $this->get('/api/notulensi/999/pdf')->assertNotFound();
    }

    /** Tebakan teks PDF: baca stream sesuai /Length lalu dekompres zlib. */
    private function teksPdf(string $path): string
    {
        $mentah = (string) file_get_contents($path);
        $hasil = '';

        // Regex "stream.*?endstream" tidak aman: akhiran kata "endstream" juga cocok,
        // dan data terkompresi bisa memuat urutan byte serupa. Jadi pakai /Length.
        $pos = 0;
        while (($p = strpos($mentah, 'stream', $pos)) !== false) {
            $pos = $p + 6;

            if (substr($mentah, max(0, $p - 3), 3) === 'end') {
                continue;
            }

            $awal = $pos;
            if (substr($mentah, $awal, 2) === "\r\n") {
                $awal += 2;
            } elseif (substr($mentah, $awal, 1) === "\n" || substr($mentah, $awal, 1) === "\r") {
                $awal += 1;
            } else {
                continue;
            }

            $kepala = substr($mentah, max(0, $p - 300), 300);
            if (! preg_match('~/Length\s+(\d+)~', $kepala, $m)) {
                continue;
            }

            $data = substr($mentah, $awal, (int) $m[1]);
            $teks = @gzuncompress($data);
            if ($teks === false && str_starts_with($data, "\x78")) {
                $teks = @gzinflate(substr($data, 2));
            }

            if ($teks !== false) {
                $hasil .= $teks;
            }
        }

        // Ambil literal string dalam operator teks "(...)".
        $teks = '';
        if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)/s', $hasil, $mm)) {
            foreach ($mm[0] as $literal) {
                $teks .= stripcslashes(substr($literal, 1, -1)) . ' ';
            }
        }

        return $teks;
    }
}
