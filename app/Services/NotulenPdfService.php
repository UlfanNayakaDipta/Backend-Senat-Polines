<?php

namespace App\Services;

use App\Models\AnggotaRapat;
use App\Models\Notulensi;
use App\Models\Rapat;
use App\Models\StrukturOrganisasi;
use App\Support\NotulenKop;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * isi_notulensi (markdown) -> PDF notulen resmi format FPM 5.5.01/L2.
 *
 * Tata letak mengikuti contoh dokumen sekretariat: halaman 612x1008 pt,
 * kop dengan logo + nomor formulir, tabel NOMOR/AGENDA RAPAT/HASIL RAPAT
 * yang rangkanya dicetak di setiap halaman, dan blok tanda tangan di akhir.
 * Lampiran & daftar hadir sengaja tidak dirender.
 */
class NotulenPdfService
{
    public const KOP_NOMOR = 'FPM 5.5.01/L2';
    public const KOP_REVISI = '2';
    public const KOP_TANGGAL = '1 Juli 2010';

    private const BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    private const HARI = [
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu',
    ];

    private const FONT_DIR = 'resources/fonts/';

    /**
     * Render notulen menjadi PDF dan simpan ke public/uploads/notulen/{id_rapat}/.
     *
     * @return string path relatif terhadap public/
     */
    public function buat(Notulensi $notulensi): string
    {
        $rapat = $notulensi->rapat()->with('jenisRapat')->firstOrFail();

        $data = [
            'meta' => $this->meta($rapat),
            'isi' => $this->urai((string) $notulensi->isi_notulensi),
        ];

        $pdf = Pdf::loadView('notulen.pdf', $data)
            ->setPaper([0, 0, 612, 1008], 'portrait')
            ->setOption('isRemoteEnabled', false)
            ->setOption('defaultFont', 'RapatBody')
            ->setOption('isFontSubsettingEnabled', true)
            ->setOption('dpi', 96);

        $this->daftarkanFont($pdf->getDomPDF()->getFontMetrics());

        $pdf->render();

        // Rangka tabel + kop digambar setelah render: page_script baru tahu
        // nomor dan total halaman final.
        $pdf->getDomPDF()->getCanvas()->page_script(
            function (int $nomor, int $total, $canvas, $fontMetrics): void {
                NotulenKop::gambar($canvas, $fontMetrics, $nomor, $total);
            }
        );

        $relatif = 'uploads/notulen/' . (int) $rapat->id_rapat . '/notulen-' . (int) $notulensi->id_notulensi . '.pdf';
        $tujuan = public_path($relatif);

        File::ensureDirectoryExists(dirname($tujuan), 0775);
        File::put($tujuan, $pdf->output());

        $notulensi->forceFill([
            'berkas_pdf' => $relatif,
            'pdf_dibuat' => now(),
        ])->save();

        return $relatif;
    }

    /** Path absolut berkas PDF; null bila belum pernah dibuat atau hilang dari disk. */
    public function path(Notulensi $notulensi): ?string
    {
        $relatif = (string) $notulensi->berkas_pdf;

        if ($relatif === '') {
            return null;
        }

        $absolut = public_path($relatif);

        return is_file($absolut) ? $absolut : null;
    }

    /** Daftarkan font lokal: Liberation Sans (metrik Arial) + DejaVu Sans untuk kop. */
    private function daftarkanFont($fontMetrics): void
    {
        foreach (array_unique(array_filter([
            storage_path('fonts'),
            config('dompdf.options.font_dir'),
            config('dompdf.options.font_cache'),
        ])) as $fontDir) {
            File::ensureDirectoryExists($fontDir, 0775);
        }

        $dir = base_path(self::FONT_DIR);
        foreach ([
            ['RapatBody', 'normal', 'LiberationSans-Regular.ttf'],
            ['RapatBody', 'bold', 'LiberationSans-Bold.ttf'],
            ['RapatBody', 'italic', 'LiberationSans-Italic.ttf'],
            ['RapatKop', 'normal', 'DejaVuSans.ttf'],
            ['RapatKop', 'bold', 'DejaVuSans-Bold.ttf'],
        ] as [$keluarga, $bobot, $berkas]) {
            $fontMetrics->registerFont([
                'family' => $keluarga,
                'weight' => $bobot,
                'style' => 'normal',
            ], $dir . $berkas);
        }
    }

    public function meta(Rapat $rapat): array
    {
        $tanggal = $rapat->tanggal instanceof Carbon
            ? $rapat->tanggal
            : Carbon::parse((string) $rapat->tanggal);

        $total = AnggotaRapat::where('id_rapat', $rapat->id_rapat)->count();
        $hadir = AnggotaRapat::where('id_rapat', $rapat->id_rapat)
            ->whereRaw('LOWER(status_kehadiran) = ?', ['hadir'])
            ->count();

        $pukul = trim((string) $rapat->waktu);

        return [
            'nama_rapat' => (string) ($rapat->nama_agenda ?? 'Rapat Senat'),
            'jenis_rapat' => (string) ($rapat->jenisRapat->nama_jenis ?? 'Rapat'),
            'hari_tanggal' => self::HARI[$tanggal->format('l')] . ', ' . $this->tanggalPanjang($tanggal),
            'pukul' => $pukul === '' ? '-' : str_replace(':', '.', substr($pukul, 0, 5)) . ' WIB s.d selesai',
            'tempat' => (string) ($rapat->tempat ?: '-'),
            'jumlah_diundang' => $total,
            'jumlah_hadir' => $hadir,
            'jumlah_tidak_hadir' => max(0, $total - $hadir),
            'kota' => 'Semarang',
            'tanggal_ttd' => $this->tanggalPanjang($tanggal),
            'ketua' => $this->penandatangan('Ketua Senat'),
            'sekretaris' => $this->penandatangan('Sekretaris Senat'),
        ];
    }

    public function tanggalPanjang(Carbon $tanggal): string
    {
        return $tanggal->format('d') . ' ' . self::BULAN[(int) $tanggal->format('n')] . ' ' . $tanggal->format('Y');
    }

    /** @return array{nama:string,nip:string} */
    private function penandatangan(string $jabatan): array
    {
        $row = StrukturOrganisasi::query()
            ->with('enrollment.user:id_user,nama,nim_nip')
            ->where('jabatan', 'like', '%' . $jabatan . '%')
            ->orderByDesc('id_struktur')
            ->first();

        return [
            'nama' => (string) ($row->enrollment->user->nama ?? ''),
            'nip' => (string) ($row->enrollment->user->nim_nip ?? ''),
        ];
    }

    /**
     * Urai markdown notulen menjadi blok siap render.
     *
     * @return array{identitas:array,agenda:array,jalan:array,kesimpulan:array,penutup:array}
     */
    public function urai(string $teks): array
    {
        $hasil = [
            'identitas' => [],
            'agenda' => [],
            'jalan' => [],
            'kesimpulan' => [],
            'penutup' => [],
        ];

        $bagian = 'jalan';
        $kunciTerakhir = null;

        foreach (preg_split('/\R/', $teks) ?: [] as $baris) {
            $rapat = trim($baris);

            if ($rapat === '') {
                continue;
            }

            if (str_starts_with($rapat, '#### ')) {
                $hasil[$bagian][] = ['jenis' => 'subjudul', 'teks' => trim(substr($rapat, 5)), 'level' => 0];
                continue;
            }

            if (str_starts_with($rapat, '### ')) {
                $hasil[$bagian][] = ['jenis' => 'judul', 'teks' => trim(substr($rapat, 4)), 'level' => 0];
                continue;
            }

            if (str_starts_with($rapat, '## ')) {
                $bagian = $this->klasifikasi(trim(substr($rapat, 3)));
                $kunciTerakhir = null;
                continue;
            }

            $level = preg_match('/^(\s+)/', $baris, $m) === 1 && strlen($m[1]) >= 2 ? 1 : 0;
            $teks = preg_replace('/^([-•*]|\d+[.)])\s+/u', '', $rapat) ?? $rapat;

            if ($bagian === 'identitas') {
                if (preg_match('/^([^:]{2,60}):\s*(.*)$/u', $teks, $cocok) === 1) {
                    $kunciTerakhir = trim($cocok[1]);
                    $hasil['identitas'][] = ['kunci' => $kunciTerakhir, 'nilai' => trim($cocok[2]), 'butir' => []];
                } elseif ($kunciTerakhir !== null && $hasil['identitas'] !== []) {
                    $akhir = count($hasil['identitas']) - 1;
                    $hasil['identitas'][$akhir]['butir'][] = ['jenis' => 'butir', 'level' => $level, 'teks' => $teks];
                }
                continue;
            }

            if (in_array($bagian, ['agenda', 'kesimpulan', 'penutup'], true)) {
                $hasil[$bagian][] = ['jenis' => 'butir', 'level' => $level, 'teks' => $teks];
                continue;
            }

            $jenis = preg_match('/^kesepakatan\b/i', $teks) === 1 ? 'kesepakatan' : 'butir';
            $hasil['jalan'][] = ['jenis' => $jenis, 'level' => $level, 'teks' => $teks];
        }

        return $hasil;
    }

    private function klasifikasi(string $judul): string
    {
        $j = Str::lower($judul);

        return match (true) {
            str_contains($j, 'informasi'), str_contains($j, 'identitas') => 'identitas',
            str_contains($j, 'agenda') => 'agenda',
            str_contains($j, 'kesimpulan') => 'kesimpulan',
            str_contains($j, 'penutup') => 'penutup',
            default => 'jalan',
        };
    }
}
