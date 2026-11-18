<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class HeroSettings
{
    public const DEFAULTS = [
        'eyebrow' => 'Organ Tertinggi Normatif Akademik',
        'title' => "Senat Akademik\nPoliteknik Negeri Semarang",
        'description' => 'Perumus kebijakan akademik, penjaga mutu pendidikan vokasi, dan penyalur aspirasi civitas akademika — memastikan tata kelola Polines berjalan transparan, akuntabel, dan berintegritas.',
        'background_image' => '/images/gedung-polines.jpg',
        'primary_label' => 'Profil & Komisi',
        'secondary_label' => 'Repositori Produk Hukum',
    ];

    public static function validateUpdate(array $data): array
    {
        $fields = array_flip(['eyebrow', 'title', 'description', 'background_image']);
        $errors = [];
        foreach (array_keys(array_diff_key($data, $fields)) as $field) {
            $errors[$field] = ['Field tidak dapat diubah.'];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        return array_intersect_key(self::validate($data + array_intersect_key(self::DEFAULTS, array_flip(['primary_label', 'secondary_label']))), $fields);
    }

    public static function validate(array $data): array
    {
        $errors = [];
        foreach (array_diff(array_keys($data), array_keys(self::DEFAULTS)) as $field) {
            $errors[$field] = ['Field tidak dikenal.'];
        }
        foreach (['eyebrow' => 160, 'title' => 160, 'description' => 500, 'background_image' => 2048, 'primary_label' => 80, 'secondary_label' => 80] as $field => $limit) {
            $value = $data[$field] ?? null;
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
                $errors[$field] = ['Wajib berupa teks UTF-8.'];
                continue;
            }
            $value = str_replace(["\r\n", "\r"], "\n", $value);
            $data[$field] = $value;
            if (!preg_match('/\S/u', $value) || mb_strlen($value, 'UTF-8') > $limit) {
                $errors[$field] = ["Teks wajib diisi, maksimal {$limit} karakter."];
            }
            if ($field === 'title' && (substr_count($value, "\n") > 1 || array_filter(explode("\n", $value), fn ($line) => !preg_match('/\S/u', $line)))) {
                $errors[$field] = ['Judul maksimal dua baris, tanpa baris kosong.'];
            }
            if (($field === 'eyebrow' || str_ends_with($field, '_label')) && str_contains($value, "\n")) {
                $errors[$field] = ['Teks harus satu baris.'];
            }
            if ($field === 'background_image' && !self::safeImageUrl($value)) {
                $errors[$field] = ['Gunakan path /images/... yang aman atau URL HTTP(S) yang valid.'];
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        return $data;
    }

    private static function safeImageUrl(string $value): bool
    {
        if (preg_match('/[\s\p{C}]/u', $value) || strpbrk($value, "\\\"'()<>`") !== false) {
            return false;
        }
        if (str_starts_with($value, '/')) {
            return preg_match('~\A/images/[A-Za-z0-9._/-]+\z~', $value)
                && !array_intersect(explode('/', substr($value, 8)), ['', '.', '..']);
        }
        if (preg_match('/%(?![a-f0-9]{2})|%(?:0[0-9a-f]|1[0-9a-f]|7f|5c)/i', $value) || !filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }
        $url = parse_url($value);
        return in_array(strtolower($url['scheme'] ?? ''), ['http', 'https'], true)
            && !isset($url['user']) && !isset($url['pass']);
    }
}
