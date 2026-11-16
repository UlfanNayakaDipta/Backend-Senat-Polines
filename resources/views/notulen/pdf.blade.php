<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Notulen Rapat</title>
<style>
    @page { margin: 116.5pt 45pt 27.5pt 51pt; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: RapatBody, sans-serif; font-size: 10pt; line-height: 10.6pt; color: #000; }

    /* kop digambar di area margin halaman, berulang tiap halaman */
    .kop { position: fixed; top: -116.5pt; left: -51pt; width: 612pt; height: 116.5pt; font-family: RapatKop, sans-serif; }
    .kop .logo { position: absolute; left: 63pt; top: 24pt; width: 76pt; height: 76pt; }
    .kop .judul { position: absolute; left: 38.5pt; width: 516pt; top: 52pt; text-align: center; font-size: 17.4pt; font-weight: bold; }
    /* identitas rapat */
    table.ident { border-collapse: collapse; table-layout: fixed; }
    table.ident { margin-top: 0.6pt; }
    table.ident td { padding: 0; font-size: 10pt; line-height: 10.6pt; vertical-align: top; }
    table.ident td.lbl { width: 149pt; font-weight: bold; }
    table.ident td.b { font-weight: bold; }
    table.ident td.titik { width: 5.5pt; }
    table.ident tr.kosong td { height: 13.4pt; }

    /* kolom tabel: rangka & sekat digambar page_script, teks ditata manual
       karena dompdf 3.1.6 mengabaikan lebar kolom saat table-layout fixed */
    .kol-nomor { position: absolute; left: 5.6pt; top: 149.3pt; width: 51pt; }
    .kol-agenda { position: absolute; left: 62.5pt; top: 149.3pt; width: 90pt; }
    .hasil { margin-left: 160pt; width: 350.5pt; }
    .bab { font-weight: bold; margin: 13.4pt 0 0 2.8pt; }
    .bab.pertama { margin-top: 0; }
    .bab.sub1 { margin: 0 0 0 17pt; font-weight: bold; }
    .bab.sub2 { margin: 0 0 0 23.5pt; font-weight: bold; }
    .sub { font-weight: bold; margin: 0 0 0 23.5pt; }
    .butir { margin: 0 0 0 18pt; }
    .butir .tk { padding-right: 14.5pt; font-size: 11pt; }
    .butir.lv1 { margin-left: 30pt; }
    .kosong { font-style: italic; }
</style>
</head>
<body>
<div class="kop">
    <img class="logo" src="{{ public_path('assets/notulen-logo.jpg') }}" alt="">
    <div class="judul">NOTULEN RAPAT</div>

</div>
@php
    // Tabel identitas selalu diambil dari data rapat, bukan dari teks AI.
    $baris = [
        ['kunci' => 'Nama Rapat', 'nilai' => $meta['nama_rapat'], 'tebal' => true],
        ['kunci' => 'Hari / tanggal', 'nilai' => $meta['hari_tanggal']],
        ['kunci' => 'Pukul', 'nilai' => $meta['pukul']],
        ['kunci' => 'Tempat', 'nilai' => $meta['tempat']],
        ['kunci' => 'Jumlah yang Diundang', 'nilai' => $meta['jumlah_diundang'] . ' Orang', 'tebal' => true],
        ['kunci' => 'Jumlah yang hadir', 'nilai' => $meta['jumlah_hadir'] . ' Orang', 'tebal' => true],
        ['kunci' => 'Tidak Hadir', 'nilai' => $meta['jumlah_tidak_hadir'] . ' Orang', 'tebal' => true],
    ];
@endphp

<div style="position: absolute; top: 0pt; left: 5.5pt; width: 516pt;">
<table class="ident">
    @foreach ($baris as $b)
        <tr>
            <td class="lbl">{{ $b['kunci'] }}</td>
            <td class="titik">:</td>
            <td class="{{ ($b['tebal'] ?? false) ? 'b' : '' }}">{{ $b['nilai'] }}</td>
        </tr>
        @if ($loop->index === 1)
            <tr><td colspan="3" style="height: 13.4pt;"></td></tr>
        @endif
    @endforeach
</table>
</div>
<div style="height: 135.9pt;"></div>

<div class="kol-nomor">1</div>
<div class="kol-agenda">
    @foreach ($isi['agenda'] as $a)
        <div class="{{ $a['level'] ? 'lv1' : '' }}">{{ $a['teks'] }}</div>
    @endforeach
</div>

<div class="hasil">
            <div class="bab pertama">I. Informasi Rapat</div>
            @forelse ($isi['identitas'] as $b)
                <div>{{ $b['kunci'] }}: {{ $b['nilai'] }}</div>
                @foreach (($b['butir'] ?? []) as $u)
                    <div class="butir {{ $u['level'] ? 'lv1' : '' }}"><span class="tk">•</span>{{ $u['teks'] }}</div>
                @endforeach
            @empty
                <div class="kosong">Tidak dibahas dalam rekaman.</div>
            @endforelse

            <div class="bab">II. Agenda Rapat</div>
            @forelse ($isi['agenda'] as $a)
                <div class="butir {{ $a['level'] ? 'lv1' : '' }}"><span class="tk">•</span>{{ $a['teks'] }}</div>
            @empty
                <div class="kosong">Tidak dibahas dalam rekaman.</div>
            @endforelse
            <div class="bab">III. Jalannya Rapat</div>
    @foreach ($isi['jalan'] as $blok)
                @if ($blok['jenis'] === 'judul')
                    <div class="bab sub1">{{ $blok['teks'] }}</div>
                @elseif ($blok['jenis'] === 'subjudul')
                    <div class="bab sub2">{{ $blok['teks'] }}</div>
                @elseif ($blok['jenis'] === 'kesepakatan')
                    <div class="bab sub2">Kesepakatan</div>
                @else
                    <div class="butir {{ $blok['level'] ? 'lv1' : '' }}"><span class="tk">•</span>{{ $blok['teks'] }}</div>
                @endif
    @endforeach
            <div class="bab">IV. Kesimpulan Rapat</div>
    @forelse ($isi['kesimpulan'] as $i => $k)
                <div class="butir"><span class="tk">{{ $i + 1 }}.</span>{{ $k['teks'] }}</div>
    @empty
    @endforelse
            <div class="bab">V. Penutup Rapat</div>
    @foreach ($isi['penutup'] as $p)
                <div class="butir"><span class="tk">•</span>{{ $p['teks'] }}</div>
    @endforeach

    <div style="margin-top: 26pt; page-break-inside: avoid;">
    <div style="width: 350.5pt; text-align: right;">{{ $meta['kota'] }}, {{ $meta['tanggal_ttd'] }}</div>

    <table style="width: 350.5pt; border-collapse: collapse;">
        <tr>
            <td style="width: 169.5pt; vertical-align: top; padding: 0;">
                <div style="margin-top: 13pt;">Ketua Senat,</div>
                <div style="margin-top: 44pt; font-weight: bold; border-bottom: 0.6pt solid #000;">{{ $meta['ketua']['nama'] !== '' ? $meta['ketua']['nama'] : '.........................................' }}</div>
                <div>{{ $meta['ketua']['nip'] !== '' ? 'NIP ' . $meta['ketua']['nip'] : 'NIP .................................' }}</div>
            </td>
            <td style="vertical-align: top; padding: 0;">
                <div style="margin-top: 13pt;">Sekretaris Senat,</div>
                <div style="margin-top: 44pt; font-weight: bold; border-bottom: 0.6pt solid #000;">{{ $meta['sekretaris']['nama'] !== '' ? $meta['sekretaris']['nama'] : '.........................................' }}</div>
                <div>{{ $meta['sekretaris']['nip'] !== '' ? 'NIP ' . $meta['sekretaris']['nip'] : 'NIP .................................' }}</div>
            </td>
        </tr>
    </table>
    </div>
</div>
</body>
</html>
