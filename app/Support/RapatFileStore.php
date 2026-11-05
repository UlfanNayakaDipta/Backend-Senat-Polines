<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Penyimpanan berkas rapat ke public/uploads/rapat/{id_rapat}.
 *
 * Sengaja memakai public_path() (bukan storage disk) agar URL hasilnya
 * langsung dapat diakses tanpa `php artisan storage:link`.
 */
class RapatFileStore
{
    public static function store(UploadedFile $file, int $idRapat): array
    {
        $nama = $file->getClientOriginalName();
        $ukuran = (int) $file->getSize();
        $mime = $file->getClientMimeType();

        $dir = public_path('uploads/rapat/' . $idRapat);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $aman = preg_replace('/[^A-Za-z0-9._-]/', '_', $nama);
        $final = $file->move($dir, uniqid('lmp-', true) . '-' . $aman);

        return [
            'nama' => $nama,
            'url' => url('uploads/rapat/' . $idRapat . '/' . $final->getFilename()),
            'ukuran' => $ukuran,
            'mime' => $mime,
        ];
    }
}
