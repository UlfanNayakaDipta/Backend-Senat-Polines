# Senat POLINES API

Laravel 11 REST API dan schema database untuk Portal Senat Akademik POLINES. Arsip ini dipisahkan dari repository frontend `senat-polines` pada commit sumber `a4d12cd`.

## Cakupan

- 20 Eloquent model, migration, factory, dan seeder.
- 20 REST resource di bawah prefix `/api`, mencakup periode senat, komisi, anggota, aspirasi, kegiatan, rapat, notulensi, voting, peraturan, berita, dan user.
- Endpoint belum memakai authentication, authorization, atau policy. Tambahkan sebelum API diakses dari jaringan publik.

## Prasyarat

- PHP >= 8.2 beserta ekstensi database MySQL/PDO.
- Composer.
- MySQL/MariaDB.

## Menjalankan

```bash
composer install
cp .env.example .env
```

Atur `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` pada `.env`. Lalu:

```bash
php artisan key:generate
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
php artisan migrate --seed
php artisan serve
```

API tersedia pada `http://127.0.0.1:8000/api`.

## Handoff

Frontend Blade, asset, controller portal, route web, repository mock JSON, Tailwind, Alpine, dan tooling Vite tidak termasuk dalam arsip ini.
