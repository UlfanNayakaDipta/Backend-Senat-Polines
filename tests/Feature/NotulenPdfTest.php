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
## I. Informasi Rapat
Pimpinan Rapat: Dr. Sukamto, S.Kom., M.T. (Ketua Senat)
Peserta Rapat: Para anggota Senat, Direktur, dan para Wakil Direktur

## II. Agenda Rapat
- Peraturan Direktur tentang Penyelenggaraan Pendidikan 2026.
- Lain-lain.

## III. Jalannya Rapat
### Pembukaan
- Rapat dibuka pukul 13.00 WIB oleh Ketua Senat.

### Poin-Poin Kesepakatan & Hasil Pembahasan
#### A. Bab IV Tata Cara Penyelenggaraan Perkuliahan
- Pasal 15 Magister Terapan ditetapkan minimum 36 SKS.
- Dr. Garup Lambang Goro, S.T., M.T. mengoreksi penulisan rujukan.
#### Kesepakatan
- Pasal 15 minimum 36 SKS dan masa studi minimum 3 semester.

#### B. Bab VII Penilaian Proses dan Hasil Belajar
- Iwan Budiyono, S.E., M.Si.Ak. meminta penilaian sumatif dilaporkan ke PD Dikti.
#### Kesepakatan
- Penambahan redaksi Pasal 31 mengenai penguji tugas akhir.

## IV. Kesimpulan Rapat
1. Beban belajar Magister Terapan ditetapkan 36 SKS.
2. Tata cara penyelenggaraan perkuliahan diselaraskan dengan Statuta Polines.

## V. Penutup Rapat
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

        $this->assertSame('Pimpinan Rapat', $isi['identitas'][0]['kunci']);
        $this->assertSame('Dr. Sukamto, S.Kom., M.T. (Ketua Senat)', $isi['identitas'][0]['nilai']);
        $this->assertSame('Peserta Rapat', $isi['identitas'][1]['kunci']);

        $this->assertCount(2, $isi['agenda']);
        $this->assertSame('Peraturan Direktur tentang Penyelenggaraan Pendidikan 2026.', $isi['agenda'][0]['teks']);

        $judul = array_values(array_filter($isi['jalan'], fn ($b) => $b['jenis'] === 'judul'));
        $sub = array_values(array_filter($isi['jalan'], fn ($b) => $b['jenis'] === 'subjudul'));
        $this->assertSame(['Pembukaan', 'Poin-Poin Kesepakatan & Hasil Pembahasan'], array_column($judul, 'teks'));
        $this->assertSame(
            ['A. Bab IV Tata Cara Penyelenggaraan Perkuliahan', 'Kesepakatan', 'B. Bab VII Penilaian Proses dan Hasil Belajar', 'Kesepakatan'],
            array_column($sub, 'teks')
        );

        $this->assertCount(2, $isi['kesimpulan']);
        $this->assertCount(2, $isi['penutup']);
    }

    public function test_urai_tidak_membuang_draf_lama_tanpa_heading(): void
    {
        $isi = (new NotulenPdfService())->urai(
            "Hasil evaluasi dokumen LED dan LKPS akreditasi:\n1. Kelengkapan instrumen 9 kriteria terpenuhi 94%.\n2. Rekomendasi penambahan peralatan lab."
        );

        $this->assertCount(3, $isi['jalan']); // kalimat pembuka + 2 butir
        $this->assertStringContainsString('Kelengkapan instrumen', $isi['jalan'][1]['teks']);
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

    public function test_render_dokumen_berisi_identitas_dan_hasil_rapat(): void
    {
        $notulensi = $this->buatNotulensi();
        $rapat = $notulensi->rapat()->with('jenisRapat')->firstOrFail();
        $svc = new NotulenPdfService();

        $html = view('notulen.pdf', [
            'meta' => $svc->meta($rapat),
            'isi' => $svc->urai((string) $notulensi->isi_notulensi),
        ])->render();

        $this->assertStringContainsString('NOTULEN RAPAT', $html);
        $this->assertStringContainsString('class="kop"', $html); // kop + kotak formulir dicetak page_script
        $this->assertStringContainsString('RAPAT PLENO KHUSUS', $html);
        $this->assertStringContainsString('Jumat, 09 Oktober 2026', $html);
        $this->assertStringContainsString('AD.II.16', $html);
        $this->assertStringContainsString('4 Orang', $html);   // diundang
        $this->assertStringContainsString('3 Orang', $html);   // hadir
        $this->assertStringContainsString('I. Informasi Rapat', $html);
        $this->assertStringContainsString('II. Agenda Rapat', $html);
        $this->assertStringContainsString('III. Jalannya Rapat', $html);
        $this->assertStringContainsString('Poin-Poin Kesepakatan &amp; Hasil Pembahasan', $html);
        $this->assertStringContainsString('Bab IV Tata Cara Penyelenggaraan Perkuliahan', $html);
        $this->assertStringContainsString('Kesepakatan', $html);
        $this->assertStringContainsString('IV. Kesimpulan Rapat', $html);
        $this->assertStringContainsString('V. Penutup Rapat', $html);
        $this->assertStringContainsString('Dr. Sukamto, S.Kom., M.T.', $html);
        $this->assertStringContainsString('197101172003121001', $html);

        // Lampiran & daftar hadir bukan bagian dokumen ini.
        $this->assertStringNotContainsString('LAMPIRAN', $html);
        $this->assertStringNotContainsString('Daftar Hadir', $html);
    }

    public function test_ukuran_halaman_dan_nomor_halaman_tiap_lembar(): void
    {
        $notulensi = $this->buatNotulensi();

        $notulensi->update([
            'isi_notulensi' => self::CONTOH . "\n\n#### Z. Butir Panjang\n"
                . implode("\n", array_fill(0, 40, '- Butir pembahasan panjang untuk menguji pembagian halaman notulen rapat senat.')),
        ]);

        $path = public_path((new NotulenPdfService())->buat($notulensi));
        $mentah = (string) file_get_contents($path);

        // Format sekretariat: 612 x 1008 pt (bukan A4).
        $this->assertMatchesRegularExpression(
            '/\/MediaBox\s*\[\s*0(?:\.\d+)?\s+0(?:\.\d+)?\s+612(?:\.\d+)?\s+1008(?:\.\d+)?\s*\]/',
            $mentah,
            'ukuran halaman harus 612 x 1008 pt seperti format sekretariat'
        );
        $jumlahHalaman = preg_match_all('~/Type\s*/Page[^s]~', $mentah);

        $teks = $this->teksPdf($path);
        preg_match_all('/(\d+)\/(\d+)/', $teks, $m);

        $this->assertNotEmpty($m[1], 'nomor halaman harus tercetak');
        $total = (int) $m[2][0];
        $this->assertGreaterThan(1, $jumlahHalaman);
        $this->assertSame($jumlahHalaman, $total, 'jumlah halaman di kop harus sama dengan jumlah lembar');

        // Hanya pasangan yang denominatornya sama dengan total yang dihitung
        // (angka lain di dokumen, mis. pasal, bisa ikut tertangkap regex).
        $nomor = array_map('intval', $m[1]);
        $denom = array_map('intval', $m[2]);
        $ditemukan = [];
        foreach ($nomor as $i => $n) {
            if ($denom[$i] === $total && $n >= 1 && $n <= $total) {
                $ditemukan[$n] = true;
            }
        }
        $ditemukan = array_keys($ditemukan);
        sort($ditemukan);

        $this->assertSame(range(1, $total), $ditemukan, 'setiap lembar harus mencetak nomor halamannya');

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
