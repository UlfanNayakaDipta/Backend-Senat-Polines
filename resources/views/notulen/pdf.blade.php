<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Notulen Rapat</title>
    <style>
        @page {
            margin: 152px 40px 58px 40px;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #000;
            line-height: 1.36;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .identitas td {
            padding: 1px 0;
            vertical-align: top;
            font-size: 10px;
        }

        .identitas .label {
            width: 150px;
        }

        .identitas .titik {
            width: 12px;
        }

        .jarak {
            height: 10px;
        }

        table.isi {
            border: 1px solid #000;
            margin-top: 8px;
        }

        table.isi th {
            border: 1px solid #000;
            background: #eaeaea;
            font-size: 10px;
            text-transform: uppercase;
            padding: 4px 5px;
            letter-spacing: .3px;
        }

        table.isi td {
            border: 1px solid #000;
            padding: 4px 5px;
            vertical-align: top;
            font-size: 9.5px;
        }

        table.isi td.nomor {
            width: 34px;
            text-align: center;
        }

        table.isi td.agenda {
            width: 152px;
        }

        .bab {
            font-weight: bold;
            margin: 0 0 3px 0;
        }

        .subbab {
            font-weight: bold;
            margin: 6px 0 3px 0;
        }

        ul, ol {
            margin: 0 0 3px 0;
            padding-left: 17px;
        }

        li {
            margin-bottom: 1px;
        }

        .lv1 {
            margin-left: 14px;
        }

        .lv2 {
            margin-left: 28px;
        }

        .kesepakatan {
            margin-top: 6px;
        }

        .kesepakatan .judul {
            font-weight: bold;
            font-size: 9.5px;
            margin: 0 0 2px 13px;
        }

        .kosong {
            font-style: italic;
            color: #444;
        }

        .ttd {
            page-break-inside: avoid;
            margin-top: 10px;
        }

        .ttd .kota {
            text-align: right;
            margin-bottom: 2px;
        }

        .ttd table td {
            vertical-align: top;
            padding-top: 4px;
            font-size: 10px;
        }

        .ttd .kolom {
            width: 50%;
        }

        .ttd .kolom.kanan {
            padding-left: 42px;
        }

        .ttd .nama {
            font-weight: bold;
            text-decoration: underline;
            margin-top: 32px;
        }
    </style>
</head>
<body>


<table class="identitas">
    <tr>
        <td class="label">Nama Rapat</td>
        <td class="titik">:</td>
        <td>{{ $meta['nama_rapat'] }}</td>
    </tr>
    <tr>
        <td class="label">Hari / Tanggal</td>
        <td class="titik">:</td>
        <td>{{ $meta['hari_tanggal'] }}</td>
    </tr>
    <tr>
        <td class="label">Pukul</td>
        <td class="titik">:</td>
        <td>{{ $meta['pukul'] }}</td>
    </tr>
    <tr>
        <td class="label">Tempat</td>
        <td class="titik">:</td>
        <td>{{ $meta['tempat'] }}</td>
    </tr>
    <tr>
        <td class="label">Jumlah yang diundang</td>
        <td class="titik">:</td>
        <td>{{ $meta['jumlah_diundang'] }} Orang</td>
    </tr>
    <tr>
        <td class="label">Jumlah yang hadir</td>
        <td class="titik">:</td>
        <td>{{ $meta['jumlah_hadir'] }} Orang</td>
    </tr>
    <tr>
        <td class="label">Tidak hadir</td>
        <td class="titik">:</td>
        <td>{{ $meta['jumlah_tidak_hadir'] }} Orang</td>
    </tr>
</table>

<div class="jarak"></div>

@php
    $nomor = 0;
@endphp

<table class="isi">
    <thead>
        <tr>
            <th style="width: 34px;">Nomor</th>
            <th style="width: 152px;">Agenda Rapat</th>
            <th>Hasil Rapat</th>
        </tr>
    </thead>
    <tbody>
        {{-- I. Informasi Rapat --}}
        <tr>
            <td class="nomor">{{ ++$nomor }}</td>
            <td class="agenda"><span class="bab">I. Informasi Rapat</span></td>
            <td>
                @if ($isi['identitas'] !== [])
                    <ul>
                        @foreach ($isi['identitas'] as $kunci => $nilai)
                            <li><strong>{{ $kunci }}:</strong> {{ $nilai }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="kosong">Tidak dibahas dalam rekaman.</p>
                @endif
            </td>
        </tr>

        {{-- II. Agenda Rapat --}}
        <tr>
            <td class="nomor">{{ ++$nomor }}</td>
            <td class="agenda"><span class="bab">II. Agenda Rapat</span></td>
            <td>
                @if ($isi['agenda'] !== [])
                    <ol>
                        @foreach ($isi['agenda'] as $item)
                            <li class="{{ $item['level'] === 0 ? '' : 'lv' . $item['level'] }}">{{ $item['teks'] }}</li>
                        @endforeach
                    </ol>
                @else
                    <p class="kosong">Tidak dibahas dalam rekaman.</p>
                @endif
            </td>
        </tr>

        {{-- III. Jalannya Rapat / Hasil Pembahasan --}}
        @forelse ($isi['pembahasan'] as $bab)
            <tr>
                <td class="nomor">{{ ++$nomor }}</td>
                <td class="agenda"><span class="bab">III. {{ $bab['judul'] }}</span></td>
                <td>
                    @if ($bab['poin'] !== [])
                        <ul>
                            @foreach ($bab['poin'] as $item)
                                <li class="{{ $item['level'] === 0 ? '' : 'lv' . $item['level'] }}">{{ $item['teks'] }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="kosong">Tidak dibahas dalam rekaman.</p>
                    @endif

                    @if ($bab['kesepakatan'] !== [])
                        <div class="kesepakatan">
                            <div class="judul">Kesepakatan</div>
                            <ul>
                                @foreach ($bab['kesepakatan'] as $item)
                                    <li class="{{ $item['level'] === 0 ? '' : 'lv' . $item['level'] }}">{{ $item['teks'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td class="nomor">{{ ++$nomor }}</td>
                <td class="agenda"><span class="bab">III. Jalannya Rapat</span></td>
                <td><p class="kosong">Tidak dibahas dalam rekaman.</p></td>
            </tr>
        @endforelse

        {{-- IV. Kesimpulan Rapat --}}
        <tr>
            <td class="nomor">{{ ++$nomor }}</td>
            <td class="agenda"><span class="bab">IV. Kesimpulan Rapat</span></td>
            <td>
                @if ($isi['kesimpulan'] !== [])
                    <ol>
                        @foreach ($isi['kesimpulan'] as $item)
                            <li class="{{ $item['level'] === 0 ? '' : 'lv' . $item['level'] }}">{{ $item['teks'] }}</li>
                        @endforeach
                    </ol>
                @else
                    <p class="kosong">Tidak ada kesimpulan yang ditetapkan dalam rekaman.</p>
                @endif
            </td>
        </tr>

        {{-- V. Penutup Rapat --}}
        <tr>
            <td class="nomor">{{ ++$nomor }}</td>
            <td class="agenda"><span class="bab">V. Penutup Rapat</span></td>
            <td>
                @if ($isi['penutup'] !== [])
                    <ul>
                        @foreach ($isi['penutup'] as $item)
                            <li class="{{ $item['level'] === 0 ? '' : 'lv' . $item['level'] }}">{{ $item['teks'] }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="kosong">Rapat ditutup oleh pimpinan rapat.</p>
                @endif
            </td>
        </tr>
    </tbody>
</table>

<div class="ttd">
    <div class="kota">{{ $meta['kota'] }}, {{ $meta['tanggal_ttd'] }}</div>

    <table>
        <tr>
            <td class="kolom">
                <div>Ketua Senat,</div>
                <div class="nama">{{ $meta['ketua']['nama'] !== '' ? $meta['ketua']['nama'] : '.........................................' }}</div>
                <div>{{ $meta['ketua']['nip'] !== '' ? 'NIP ' . $meta['ketua']['nip'] : 'NIP .................................' }}</div>
            </td>
            <td class="kolom kanan">
                <div>Sekretaris Senat,</div>
                <div class="nama">{{ $meta['sekretaris']['nama'] !== '' ? $meta['sekretaris']['nama'] : '.........................................' }}</div>
                <div>{{ $meta['sekretaris']['nip'] !== '' ? 'NIP ' . $meta['sekretaris']['nip'] : 'NIP .................................' }}</div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
