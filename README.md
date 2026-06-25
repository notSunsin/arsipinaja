# 📚 ArsipIn - Sistem Manajemen Arsip Digital

[![Laravel](https://img.shields.io/badge/Laravel-12.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3+-blue.svg)](https://php.net)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.x-38B2AC.svg)](https://tailwindcss.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

**ArsipIn** adalah sistem informasi pengelolaan arsip berbasis web untuk membantu instansi mengelola siklus hidup arsip — mulai dari pencatatan, klasifikasi, retensi, hingga penyusutan (musnah/permanen) — secara digital dan terstruktur sesuai Jadwal Retensi Arsip (JRA).

Versi saat ini: **v0.3** (lihat `config/version.php`)

## Fitur

### Manajemen Arsip
- CRUD arsip lengkap dengan klasifikasi, kategori, dan kurun waktu
- Pengelompokan status arsip: **Aktif**, **Inaktif**, **Musnah**, **Permanen**
- Penomoran definitif otomatis per klasifikasi & tahun
- Generate nomor berkas otomatis
- Pencarian arsip (quick search & advanced search) dengan autocomplete
- Upload file arsip (PDF, PNG, JPG) pada form tambah/edit arsip, lengkap dengan tombol preview file
- Tembusan arsip (dropdown Ada/Tidak Ada Tembusan dengan daftar penerima dinamis)
- Export data arsip ke Excel (per status, custom filter, maupun arsip terpilih), termasuk kolom Tembusan & link preview file arsip
- Export & cetak label

### Operasi Massal (Bulk Operations)
- Ubah status banyak arsip sekaligus
- Assign kategori/klasifikasi secara massal
- Hapus arsip secara massal
- Export arsip terpilih ke Excel

### Manajemen Retensi
- Deteksi otomatis arsip yang mendekati masa transisi aktif → inaktif → musnah/permanen
- Dashboard laporan retensi (arsip yang akan/sudah jatuh tempo)
- Override status manual dengan pencatatan jejak audit (siapa & kapan)

### Master Data
- Manajemen Kategori
- Manajemen Klasifikasi (kode, retensi aktif/inaktif, nasib akhir sesuai JRA)

### Manajemen Pengguna & Role
- Role berbasis Spatie Permission: **Admin** dan **Intern** (Mahasiswa Magang)
- Admin: akses penuh ke seluruh fitur termasuk manajemen role & user
- Intern: akses terbatas pada operasi arsip dasar (lihat, tambah, edit, export, cetak label)

### Dashboard & Analitik
- Dashboard khusus per role (Admin Portal & Portal Magang)
- Halaman analitik (distribusi kategori, status, tren tahunan)

## Tech Stack

- **Backend**: Laravel 12 (PHP 8.2+)
- **Frontend**: Blade, Alpine.js, TailwindCSS, Vue 3 (sebagian komponen), Vite
- **Database**: MySQL
- **Autentikasi & Role**: Laravel Breeze + Spatie Laravel Permission
- **Export**: Maatwebsite Excel (PhpSpreadsheet), barryvdh/laravel-dompdf, PhpWord

## Spesifikasi Versi

Versi yang digunakan pada environment development project ini:

| Komponen | Versi |
|---|---|
| PHP | 8.3.30 |
| Laravel Framework | 12.20.0 |
| Composer | 2.9.5 |
| MySQL | 8.4.3 |
| Node.js | 20.17.0 |
| npm | 11.11.0 |
| Vite | 6.4.3 |
| Tailwind CSS | 3.4.19 |
| Vue | 3.5.35 |
| Alpine.js | 3.15.12 |
| Element Plus | 2.14.1 |
| Laravel Breeze | 2.3.7 |
| Laravel Sanctum | 4.2.0 |
| Spatie Laravel Permission | 6.20.0 |
| Maatwebsite Excel | 3.1.64 |
| PhpSpreadsheet | 1.29.12 |
| barryvdh/laravel-dompdf | 3.1.1 |
| PhpWord | 1.4.0 |

> Batas minimum yang disyaratkan `composer.json`: **PHP ^8.2** dan **Laravel ^12.0**. Versi di atas adalah versi aktual yang terpasang saat ini — selama memenuhi batas minimum tersebut, versi patch/minor yang sedikit berbeda tetap kompatibel.

## Instalasi & Menjalankan di Laragon

### 1. Prasyarat
Pastikan sudah terinstall di Laragon:
- PHP **8.2+**
- MySQL (gunakan satu versi MySQL secara konsisten)
- Composer
- Node.js + npm

### 2. Letakkan project
Pastikan folder project berada di `C:\laragon\www\arsipinaja`.

### 3. Install dependencies
```bash
composer install
npm install
```

### 4. Setup file `.env`
```bash
copy .env.example .env
php artisan key:generate
```
Sesuaikan konfigurasi database di `.env`:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=arsipinaja
DB_USERNAME=root
DB_PASSWORD=
```

### 5. Buat database MySQL
Buat database baru bernama `arsipinaja` (harus sama dengan `DB_DATABASE` di `.env`) melalui HeidiSQL/phpMyAdmin bawaan Laragon, atau via terminal:
```bash
mysql -u root -e "CREATE DATABASE arsipinaja;"
```

### 6. Jalankan migration
```bash
php artisan migrate
```

### 7. Import data master (kategori & klasifikasi)
File `db category.sql` dan `db klasifikasi.sql` di root project berisi data master kategori & klasifikasi sesuai JRA. Jalankan **setelah** migration:
```bash
mysql -u root arsipinaja < "db category.sql"
mysql -u root arsipinaja < "db klasifikasi.sql"
```

### 8. Jalankan seeder (role & user demo)
```bash
php artisan db:seed
```
Akun demo yang dibuat:

| Role | Email | Password |
|---|---|---|
| Admin | admin@arsipin.id | password |
| Intern | intern@arsipin.id | password |
| Intern | intern1@arsipin.id | password |

### 9. Build asset frontend
```bash
npm run dev    # mode development
npm run build  # mode production
```

### 10. Link storage (untuk upload file/PDF)
```bash
php artisan storage:link
```

### 11. Jalankan aplikasi
Akses melalui salah satu cara berikut:
- `http://arsipinaja.test` (jika virtual host Laragon aktif)
- `http://localhost/arsipinaja/public`
- atau jalankan `php artisan serve` lalu buka `http://127.0.0.1:8000`

## Changelog

### v0.3
- Tambah fitur upload file arsip (PDF, PNG, JPG) pada form tambah & edit arsip (Admin dan Intern), dengan tombol preview file
- Tambah fitur tembusan arsip: dropdown Ada Tembusan/Tidak Ada Tembusan, dengan daftar penerima tembusan yang bisa ditambah dinamis
- Tambah kolom Tembusan dan link preview file arsip pada seluruh export Excel (arsip Aktif, Inaktif/Permanen, Musnah, dan Semua Status)

### v0.2
- Hapus fitur Manajemen Storage (lokasi rak/baris/boks) dari Admin dan Intern
- Hapus integrasi Telegram Bot
- Hapus kolom `box_number`, `file_number`, `rack_number`, `row_number`, `re_evaluation` yang sudah tidak terpakai

## Lisensi

Proyek ini dikembangkan untuk keperluan internal **DPMPTSP**.

---

Copyright © 2026 Intern DPMPTSP

1. Muhammad Rizky H — 1462300237
2. Dhimas Valentino A Z — 1462300242
