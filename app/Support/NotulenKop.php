<?php

namespace App\Support;

use Dompdf\Adapter\CPDF;
use Dompdf\FontMetrics;

/**
 * Rangka tabel notulen + nomor halaman, digambar pada setiap halaman lewat
 * dompdf page_script setelah render (nomor & total halaman sudah final).
 *
 * Kop (logo, judul, kotak formulir) dirender sebagai elemen HTML fixed di
 * template agar gambar & garisnya ditata oleh mesin layout dompdf.
 */
final class NotulenKop
{
    public const KIRI = 51.0;
    public const KANAN = 567.0;
    public const KOL_NOMOR = 108.0;
    public const KOL_AGENDA = 205.5;
    public const BAWAH = 980.5;
    public const ATAS_AWAL = 237.5;
    public const ATAS_LANJUT = 116.5;
    public const KEPALA = 251.5;

    // Kop formulir (kotak tertutup 56.5..552.5, sekat logo/judul di 146.5)
    public const KOP_KIRI = 56.5;
    public const KOP_SEKAT = 146.5;
    public const KOP_KANAN = 552.5;
    public const KOP_ATAS = 21.5;
    public const KOP_ATAS2 = 22.4;
    public const KOP_BAWAH = 101.6;

    public static function gambar(CPDF $pdf, FontMetrics $fontMetrics, int $nomor, int $total): void
    {
        $tinta = [0, 0, 0];
        $tebal = 0.5;      // garis tabel: hairline seperti dokumen asli
        $tebalKop = 1.5;   // garis kop lebih tebal dari garis tabel
        $atas = $nomor === 1 ? self::ATAS_AWAL : self::ATAS_LANJUT;

        // Rangka luar tabel
        $pdf->line(self::KIRI, $atas, self::KANAN, $atas, $tinta, $tebal);
        $pdf->line(self::KIRI, self::BAWAH, self::KANAN, self::BAWAH, $tinta, $tebal);
        $pdf->line(self::KIRI, $atas, self::KIRI, self::BAWAH, $tinta, $tebal);
        $pdf->line(self::KANAN, $atas, self::KANAN, self::BAWAH, $tinta, $tebal);

        // Sekat kolom, dan garis bawah baris kepala (halaman pertama saja)
        $pdf->line(self::KOL_NOMOR, $atas, self::KOL_NOMOR, self::BAWAH, $tinta, $tebal);
        $pdf->line(self::KOL_AGENDA, $atas, self::KOL_AGENDA, self::BAWAH, $tinta, $tebal);

        // Kop: kotak tertutup + sekat logo/judul + kotak formulir
        self::kop($pdf, $tinta, $tebalKop);
        self::formulir($pdf, $fontMetrics, $tinta, 1.5);

        if ($nomor === 1) {
            $pdf->line(self::KIRI, self::KEPALA, self::KANAN, self::KEPALA, $tinta, $tebal);

            // Label baris kepala tabel (hanya halaman pertama)
            $isi = $fontMetrics->getFont('RapatBody', 'normal');
            $pdf->text(56.6, 237.65, 'NOMOR', $isi, 10, $tinta);
            $pdf->text(113.5, 237.65, 'AGENDA RAPAT', $isi, 10, $tinta);
            $pdf->text(211.0, 237.65, 'HASIL RAPAT', $isi, 10, $tinta);
        }

        // Nomor halaman pada sel "Halaman" di kotak formulir.
        // Sengaja memakai font inti Helvetica: angka tetap bisa diekstrak teks
        // (font CID menyandikan glif sebagai hex sehingga tidak terbaca).
        $angka = $fontMetrics->getFont('helvetica', 'bold');
        $pdf->text(508.8, 87.41, $nomor . '/' . max(1, $total), $angka, 9.33, $tinta);
    }

    /** Kotak kop: dua garis atas, garis bawah, dan tiga garis tegak. */
    private static function kop(CPDF $pdf, array $tinta, float $tebal): void
    {
        // Garis atas kop satu garis tebal 1,5 pt (bukan dua garis tipis).
        $pdf->line(self::KOP_KIRI, self::KOP_ATAS, self::KOP_KANAN, self::KOP_ATAS, $tinta, $tebal);
        $pdf->line(self::KOP_KIRI, self::KOP_BAWAH, self::KOP_KANAN, self::KOP_BAWAH, $tinta, 1.0);

        foreach ([self::KOP_KIRI, self::KOP_SEKAT, self::KOP_KANAN] as $x) {
            $pdf->line($x, self::KOP_ATAS, $x, self::KOP_BAWAH, $tinta, $tebal);
        }
    }

    /** Kotak formulir kanan atas: No. FPM, Revisi, Tanggal, Halaman. */
    private static function formulir(CPDF $pdf, FontMetrics $fontMetrics, array $tinta, float $tebal): void
    {
        $kiri = 446.5;
        $tengah = 503.0;
        $kanan = self::KOP_KANAN;

        $pdf->line($kiri, 21.5, $kiri, 101.6, $tinta, $tebal);
        $pdf->line($tengah, 21.5, $tengah, 101.6, $tinta, $tebal);
        $pdf->line($kanan, 21.5, $kanan, 101.6, $tinta, $tebal);

        foreach ([44.0, 65.0, 87.5] as $y) {
            $pdf->line($kiri, $y, $kanan, $y, $tinta, $tebal);
        }

        $tebalFont = $fontMetrics->getFont('RapatKop', 'bold');
        $biasa = $fontMetrics->getFont('RapatBody', 'normal');

        $pdf->text(452.0, 25.73, 'No. FPM', $tebalFont, 9.33, $tinta);
        $pdf->text(508.8, 25.73, '5.5.01/L2', $biasa, 9.33, $tinta);
        $pdf->text(452.0, 47.09, 'Revisi', $tebalFont, 9.33, $tinta);
        $pdf->text(508.8, 47.09, '2', $biasa, 9.33, $tinta);
        $pdf->text(452.0, 68.81, 'Tanggal', $tebalFont, 9.33, $tinta);
        $pdf->text(508.8, 63.27, '1 Juli', $biasa, 9.33, $tinta);
        $pdf->text(508.8, 74.21, '2010', $biasa, 9.33, $tinta);
        $pdf->text(452.0, 87.41, 'Halaman', $tebalFont, 9.33, $tinta);
    }
}
