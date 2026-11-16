<?php

namespace App\Support;

use Dompdf\Adapter\CPDF;
use Dompdf\FontMetrics;

/**
 * Kop formulir notulen (No. FPM 5.5.01/L2) yang dicetak di setiap halaman.
 *
 * Digambar lewat dompdf page_script setelah render selesai supaya nomor dan
 * total halaman sudah final. Di dalam callback WAJIB memakai text()/line(),
 * bukan page_text()/page_line(): metode page_* mengulang gambar ke SELURUH
 * halaman sehingga semua lembar akan mencetak nomor halaman yang sama.
 */
final class NotulenKop
{
    public static function gambar(CPDF $pdf, FontMetrics $fontMetrics, int $nomor, int $total): void
    {
        $tinta = [0, 0, 0];
        $tebal = 0.8;

        // Koordinat dibulatkan ke setengah poin agar garis tidak ter-render 2 px.
        $x1 = 30.5;
        $x2 = floor($pdf->get_width() - 30) + 0.5;
        $y1 = 26.5;
        $y2 = 101.5;                 // tiga baris @25 pt
        $xr = $x2 - 118;             // panel kanan
        $xC = 168.5;                 // sekat panel kiri / judul
        $xLabel = $xr + 42;          // sekat label / nilai

        // Kotak luar
        $pdf->line($x1, $y1, $x2, $y1, $tinta, $tebal);
        $pdf->line($x1, $y2, $x2, $y2, $tinta, $tebal);
        $pdf->line($x1, $y1, $x1, $y2, $tinta, $tebal);
        $pdf->line($x2, $y1, $x2, $y2, $tinta, $tebal);

        // Panel kiri, panel kanan, dan garis baris panel kanan
        $pdf->line($xC, $y1, $xC, $y2, $tinta, $tebal);
        $pdf->line($xr, $y1, $xr, $y2, $tinta, $tebal);
        $pdf->line($xLabel, $y1, $xLabel, $y2, $tinta, $tebal);
        $pdf->line($xr, $y1 + 25, $x2, $y1 + 25, $tinta, $tebal);
        $pdf->line($xr, $y1 + 50, $x2, $y1 + 50, $tinta, $tebal);

        $normal = $fontMetrics->getFont('helvetica', 'normal');
        $bold = $fontMetrics->getFont('helvetica', 'bold');

        // Catatan: CPDF::text() memperlakukan $y sebagai TEPI ATAS teks, bukan baseline.
        // Tiap baris panel kanan dipusatkan pada tinggi baris 25 pt.
        $pdf->text($x1 + 7, $y1 + 26.5, 'No. FPM', $normal, 8.5, $tinta);
        $pdf->text($x1 + 7, $y1 + 39, '5.5.01/L2', $bold, 8.5, $tinta);

        $judul = 'NOTULEN RAPAT';
        $lebar = $fontMetrics->getTextWidth($judul, $bold, 12);
        $pdf->text((($xC + $xr) / 2) - ($lebar / 2), $y1 + 33, $judul, $bold, 12, $tinta);

        $pdf->text($xr + 5, $y1 + 9.5, 'Revisi', $normal, 8, $tinta);
        $pdf->text($xLabel + 5, $y1 + 9.5, '2', $bold, 8, $tinta);

        $pdf->text($xr + 5, $y1 + 34.5, 'Tanggal', $normal, 8, $tinta);
        $pdf->text($xLabel + 5, $y1 + 34.5, '1 Juli 2010', $bold, 8, $tinta);

        $pdf->text($xr + 5, $y1 + 59.5, 'Halaman', $normal, 8, $tinta);
        $pdf->text($xLabel + 5, $y1 + 59.5, $nomor . '/' . max(1, $total), $bold, 8, $tinta);
    }
}
