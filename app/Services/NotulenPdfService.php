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
 * isi_notulensi (markdown) → PDF notulen resmi format FPM 5.5.01/L2.
 *
 * Fokus pada HASIL rapat (informasi, agenda, pembahasan + kesepakatan,
 * kesimpulan, penutup). Lampiran dan daftar hadir sengaja tidak dirender:
 * keduanya dokumen terpisah yang diurus sekretariat.
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

    /**
     * Render notulen menjadi PDF dan simpan ke public/uploads/notulen/{id_rapat}/.
     *
     * @return string path relatif terhadap public/
     */
    public function buat(Notulensi $notulensi): string
    {
        $rapat = $notulensi->rapat()->with('jenisRapat')->firstOrFail();

        $meta = $this->meta($rapat);
        $isi = $this->urai((string) $notulensi->isi_notulensi);

        $data = [
            'meta' => $meta,
            'isi' => $isi,
        ];

        $pdf = Pdf::loadView('notulen.pdf', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false)
            ->setOption('defaultFont', 'Helvetica')
            ->setOption('dpi', 96);

        $pdf->render();

        // Kop digambar setelah render: page_script baru tahu nomor & total halaman final.
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

    /**
     * Metadata identitas rapat + penandatangan.
     *
     * @return array<string, mixed>
     */
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

    /**
     * @return array{nama:string,nip:string}
     */
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
     * Markdown bebas → struktur bagian notulen.
     *
     * @return array{
     *     identitas: array<string,string>,
     *     agenda: list<array{level:int,teks:string}>,
     *     pembahasan: list<array{judul:string,poin:list<array{level:int,teks:string}>,kesepakatan:list<array{level:int,teks:string}>}>,
     *     kesimpulan: list<array{level:int,teks:string}>,
     *     penutup: list<array{level:int,teks:string}>
     * }
     */
    public function urai(string $markdown): array
    {
        $hasil = [
            'identitas' => [],
            'agenda' => [],
            'pembahasan' => [],
            'kesimpulan' => [],
            'penutup' => [],
        ];

        $bagian = 'pembahasan';
        $indeksPembahasan = -1;
        $keKesepakatan = false;

        foreach (preg_split('/\R/u', $markdown) ?: [] as $baris) {
            $baris = rtrim($baris);

            if (trim($baris) === '') {
                continue;
            }

            // Heading "## ..." memindahkan bagian aktif.
            if (preg_match('/^##\s+(.+)$/u', trim($baris), $m)) {
                $judul = $this->bersihkan($m[1]);
                $bagian = $this->tentukanBagian($judul);
                $indeksPembahasan = -1;
                $keKesepakatan = false;

                // Heading "## Hasil Pembahasan" boleh langsung diikuti isi;
                // heading lain yang tidak dikenali (format lama, mis. "## Keputusan")
                // tidak dibuang: dijadikan bab pembahasan tersendiri.
                if ($bagian === 'agenda' && ! $this->cocok($judul, ['agenda'])) {
                    $bagian = 'pembahasan';
                    $hasil['pembahasan'][] = ['judul' => $judul, 'poin' => [], 'kesepakatan' => []];
                    $indeksPembahasan = count($hasil['pembahasan']) - 1;
                }

                continue;
            }

            // Sub-heading "### A. ..." → bab pembahasan baru.
            if (preg_match('/^###\s+(.+)$/u', trim($baris), $m)) {
                $judul = $this->bersihkan($m[1]);

                if ($bagian === 'pembahasan') {
                    if ($this->cocok($judul, ['kesepakatan'])) {
                        $keKesepakatan = true;

                        continue;
                    }

                    $hasil['pembahasan'][] = ['judul' => $judul, 'poin' => [], 'kesepakatan' => []];
                    $indeksPembahasan = count($hasil['pembahasan']) - 1;
                    $keKesepakatan = false;

                    continue;
                }

                if ($bagian === 'agenda') {
                    $hasil['agenda'][] = ['level' => 0, 'teks' => $judul];

                    continue;
                }
            }

            // Sub-sub-heading "#### Kesepakatan" di dalam bab pembahasan.
            if (preg_match('/^#{4,}\s+(.+)$/u', trim($baris), $m)) {
                if ($bagian === 'pembahasan' && $this->cocok($this->bersihkan($m[1]), ['kesepakatan'])) {
                    $keKesepakatan = true;
                }

                continue;
            }

            if ($bagian === 'identitas') {
                if (preg_match('/^[-*•]?\s*\*{0,2}([^:]{2,60})\*{0,2}\s*:\s*(.+)$/u', trim($baris), $m)) {
                    $hasil['identitas'][trim($this->bersihkan($m[1]))] = $this->bersihkan($m[2]);
                } elseif ($hasil['identitas'] !== []) {
                    // Baris lanjutan milik nilai terakhir.
                    $kunci = array_key_last($hasil['identitas']);
                    $hasil['identitas'][$kunci] = trim($hasil['identitas'][$kunci] . ' ' . $this->bersihkan($baris));
                }

                continue;
            }

            // "Kesepakatan" tanpa heading, berdiri sendiri.
            if ($bagian === 'pembahasan' && $this->cocok($this->bersihkan(ltrim($baris, "-*•# \t")), ['kesepakatan'])) {
                $keKesepakatan = true;

                continue;
            }

            $item = $this->itemDaftar($baris);

            if ($bagian === 'agenda') {
                $hasil['agenda'][] = $item;
            } elseif ($bagian === 'kesimpulan') {
                $hasil['kesimpulan'][] = $item;
            } elseif ($bagian === 'penutup') {
                $hasil['penutup'][] = $item;
            } elseif ($bagian === 'pembahasan' || $indeksPembahasan >= 0) {
                // Isi tanpa sub-heading tetap dicetak: buat bab "Hasil Rapat" implisit.
                if ($indeksPembahasan < 0) {
                    $hasil['pembahasan'][] = ['judul' => 'Hasil Rapat', 'poin' => [], 'kesepakatan' => []];
                    $indeksPembahasan = count($hasil['pembahasan']) - 1;
                }

                $kunci = $keKesepakatan ? 'kesepakatan' : 'poin';
                $hasil['pembahasan'][$indeksPembahasan][$kunci][] = $item;
            }
        }

        return $hasil;
    }

    /**
     * @param  list<string>  $kataKunci
     */
    private function cocok(string $teks, array $kataKunci): bool
    {
        foreach ($kataKunci as $kunci) {
            if (Str::contains(Str::lower($teks), $kunci)) {
                return true;
            }
        }

        return false;
    }

    private function tentukanBagian(string $judul): string
    {
        $judul = Str::lower($judul);

        if (Str::contains($judul, ['identitas', 'informasi'])) {
            return 'identitas';
        }

        if (Str::contains($judul, ['agenda'])) {
            return 'agenda';
        }

        if (Str::contains($judul, ['pembahasan', 'jalannya', 'hasil'])) {
            return 'pembahasan';
        }

        if (Str::contains($judul, ['kesimpulan'])) {
            return 'kesimpulan';
        }

        if (Str::contains($judul, ['penutup'])) {
            return 'penutup';
        }

        // Heading tak dikenal (mis. action item) tidak dibuang: masuk agenda.
        return 'agenda';
    }

    /**
     * @return array{level:int,teks:string}
     */
    private function itemDaftar(string $baris): array
    {
        $level = 0;
        $teks = $baris;

        if (preg_match('/^(\s*)[-*•]\s+(.+)$/u', $baris, $m)) {
            $level = min(2, (int) floor(mb_strlen($m[1]) / 2));
            $teks = $m[2];
        } elseif (preg_match('/^(\s*)(\d+|[a-zA-Z])[.)]\s+(.+)$/u', $baris, $m)) {
            $level = min(2, (int) floor(mb_strlen($m[1]) / 2));
            $teks = $m[3];
        }

        return ['level' => $level, 'teks' => $this->bersihkan($teks)];
    }

    /** Buang penanda markdown sebaris yang tidak perlu di PDF. */
    private function bersihkan(string $teks): string
    {
        $teks = str_replace(['**', '__', '`', '\\'], '', $teks);
        $teks = preg_replace('/\*(\S[^*]*)\*/u', '$1', $teks) ?? $teks;

        return trim($teks);
    }
}
