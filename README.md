# Senat POLINES Backend API (Laravel 13)

Headless REST API dan arsitektur basis data relasional untuk **Portal Senat Akademik Politeknik Negeri Semarang (POLINES)**. Repositori ini berfungsi sebagai penyedia data terpusat (*Central Data & Business Logic Provider*) yang melayani antarmuka frontend reactive `senat-polines` (Laravel 13 + Livewire 4).

## 🚀 Cakupan Sistem

- **Framework:** Laravel 13.x (PHP ^8.3).
- **Arsitektur:** Headless Decoupled RESTful API (JSON output agnostik).
- **Skema Database:** 22 Migration & Model Eloquent terindeks (User, Role, Rapat, Anggota Rapat, Presensi QR, Notulensi, Rekomendasi Keputusan, Voting, Opsi Voting, Suara Voting, Aspirasi, Tanggapan, Peraturan, Berita, dll).
- **Keamanan & Autentikasi:** API Auth dengan auto-hash password, integrasi token sesi, dan transaksi database ACID pada pencatatan suara (*voting integrity*).
- **Dokumentasi API:** Terintegrasi interaktif OpenAPI / Swagger UI via `darkaonline/l5-swagger`.

## 📋 Prasyarat Sistem

- **PHP:** >= 8.3 (Direkomendasikan PHP 8.3 - 8.5) beserta ekstensi: `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`.
- **Composer:** >= 2.2
- **Basis Data:** MySQL 8.0+ / MariaDB 10.4+ (XAMPP / Standalone)

## 🛠️ Panduan Instalasi & Menjalankan

### 1. Kloning & Pemasangan Dependensi
```bash
git clone https://github.com/UlfanNayakaDipta/Backend-Senat-Polines.git
cd Backend-Senat-Polines
composer install
```

### 2. Konfigurasi Environment
Salin berkas konfigurasi `.env.example` ke `.env`:
```bash
cp .env.example .env
```
Sesuaikan kredensial basis data pada `.env`:
```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=senat_polines
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Generate App Key & Migrasi Basis Data
```bash
php artisan key:generate
php artisan migrate --seed
```

### 4. Menjalankan Server API
Jalankan server pada **Port 8001** (standar integrasi frontend):
```bash
php artisan serve --port=8001
```
Layanan REST API dapat diakses pada: `http://127.0.0.1:8001/api`

---

## 📖 Dokumentasi API Interaktif (OpenAPI / Swagger)

Dokumentasi endpoint lengkap, skema parameter request, dan format respons JSON dapat diuji langsung melalui antarmuka Swagger:
- **Swagger UI Interactive:** [http://127.0.0.1:8001/api/documentation](http://127.0.0.1:8001/api/documentation)
- **OpenAPI JSON Spec:** [http://127.0.0.1:8001/docs](http://127.0.0.1:8001/docs)

Untuk meregenerasi berkas OpenAPI spec secara manual:
```bash
php artisan l5-swagger:generate
```

---

## 🧪 Pengujian Otomatis (Automated Testing)

Jalankan rangkaian unit & feature test menggunakan PHPUnit:
```bash
./vendor/bin/phpunit
```

---

## 👥 Tim Pengembang

- **Ketua & Koordinasi:** Senat Akademik POLINES IT Team
- **Frontend Repository:** [xrodwell/senat-polines](https://github.com/xrodwell/senat-polines)
