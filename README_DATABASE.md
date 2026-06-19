# Dokumentasi Database — ARSIPIN

> Sistem Manajemen Arsip — Dinas Penanaman Modal dan PTSP Provinsi Jawa Timur
> Framework: Laravel 11 | Database: MySQL | Auth & RBAC: Spatie Permission

---

## Daftar Isi

1. [Ringkasan Tabel](#ringkasan-tabel)
2. [Diagram Relasi Antar Tabel (Teks)](#diagram-relasi-antar-tabel-teks)
3. [Detail Setiap Tabel](#detail-setiap-tabel)
   - [users](#1-users)
   - [categories](#2-categories)
   - [classifications](#3-classifications)
   - [archives](#4-archives)
   - [storage\_racks](#5-storage_racks)
   - [storage\_rows](#6-storage_rows)
   - [storage\_boxes](#7-storage_boxes)
   - [storage\_capacity\_settings](#8-storage_capacity_settings)
   - [roles](#9-roles)
   - [permissions](#10-permissions)
   - [model\_has\_roles](#11-model_has_roles)
   - [model\_has\_permissions](#12-model_has_permissions)
   - [role\_has\_permissions](#13-role_has_permissions)
   - [personal\_access\_tokens](#14-personal_access_tokens)
   - [sessions](#15-sessions)
   - [password\_reset\_tokens](#16-password_reset_tokens)
   - [cache & cache\_locks](#17-cache--cache_locks)
   - [jobs](#18-jobs)
4. [Relasi Lengkap (Foreign Key)](#relasi-lengkap-foreign-key)
5. [Alur Bisnis Database](#alur-bisnis-database)
6. [Nilai Enum Per Kolom](#nilai-enum-per-kolom)

---

## Ringkasan Tabel

| No | Nama Tabel                  | Fungsi                                              | Kelompok        |
|----|-----------------------------|-----------------------------------------------------|-----------------|
| 1  | `users`                     | Data pengguna sistem (admin & intern)               | Auth            |
| 2  | `categories`                | Kategori arsip (master data)                        | Master Data     |
| 3  | `classifications`           | Klasifikasi/sub-kategori arsip (master data)        | Master Data     |
| 4  | `archives`                  | Data arsip utama                                    | Arsip           |
| 5  | `storage_racks`             | Rak penyimpanan fisik                               | Penyimpanan     |
| 6  | `storage_rows`              | Baris/shelf di dalam rak                            | Penyimpanan     |
| 7  | `storage_boxes`             | Box/kotak di dalam baris                            | Penyimpanan     |
| 8  | `storage_capacity_settings` | Pengaturan kapasitas per rak                        | Penyimpanan     |
| 9  | `roles`                     | Peran pengguna (admin, intern)                      | RBAC (Spatie)   |
| 10 | `permissions`               | Hak akses/izin                                      | RBAC (Spatie)   |
| 11 | `model_has_roles`           | Pivot: user ↔ role                                  | RBAC (Spatie)   |
| 12 | `model_has_permissions`     | Pivot: user ↔ permission langsung                   | RBAC (Spatie)   |
| 13 | `role_has_permissions`      | Pivot: role ↔ permission                            | RBAC (Spatie)   |
| 14 | `personal_access_tokens`    | Token API (Laravel Sanctum)                         | API             |
| 15 | `sessions`                  | Sesi login pengguna                                 | Auth            |
| 16 | `password_reset_tokens`     | Token reset password                                | Auth            |
| 17 | `cache` / `cache_locks`     | Cache aplikasi                                      | Sistem          |
| 18 | `jobs`                      | Antrian pekerjaan background (queue)                | Sistem          |

---

## Diagram Relasi Antar Tabel (Teks)

```
users ──────────────────────────────────────────────────────────────┐
  │  (id)                                                            │
  │                                                                  │
  ├─1──────N── model_has_roles ──N──1── roles ──1──N── role_has_permissions ──N──1── permissions
  │                                                                  │
  ├─ created_by ──N──1── archives ──N──1── categories ──1──N── classifications
  ├─ updated_by ──────────┘                                          │
  └─ manual_override_by ──┘             └────────────────────────────┘
                                                   (classifications.category_id)

archives
  ├─ category_id        → categories.id
  ├─ classification_id  → classifications.id
  ├─ rack_number        → storage_racks.id  (soft reference)
  ├─ created_by         → users.id
  ├─ updated_by         → users.id
  └─ manual_override_by → users.id

storage_racks ──1──N── storage_rows ──1──N── storage_boxes
storage_racks ──1──1── storage_capacity_settings
storage_racks ──1──N── storage_boxes (langsung, via rack_id)
```

---

## Detail Setiap Tabel

### 1. `users`

Menyimpan data seluruh pengguna sistem. Terdapat dua tipe role: **admin** dan **intern**.

| Kolom               | Tipe                          | Null | Default | Keterangan                        |
|---------------------|-------------------------------|------|---------|-----------------------------------|
| `id`                | bigint unsigned (PK, AI)      | No   | —       | Primary key                       |
| `name`              | varchar(255)                  | No   | —       | Nama lengkap pengguna             |
| `username`          | varchar(255), UNIQUE          | Yes  | NULL    | Username login (opsional)         |
| `email`             | varchar(255), UNIQUE          | No   | —       | Email login                       |
| `email_verified_at` | timestamp                     | Yes  | NULL    | Waktu verifikasi email            |
| `role_type`         | enum('admin','intern')        | No   | 'admin' | Tipe role untuk akses menu        |
| `password`          | varchar(255)                  | No   | —       | Password ter-hash (bcrypt)        |
| `remember_token`    | varchar(100)                  | Yes  | NULL    | Token "remember me"               |
| `created_at`        | timestamp                     | Yes  | NULL    |                                   |
| `updated_at`        | timestamp                     | Yes  | NULL    |                                   |

**Catatan:** `role_type` digunakan untuk routing dan tampilan menu. Pengelolaan izin detail menggunakan Spatie Permission melalui tabel `roles` dan `model_has_roles`.

---

### 2. `categories`

Master data kategori arsip. Setiap kategori dapat memiliki banyak klasifikasi.

| Kolom           | Tipe                     | Null | Default | Keterangan                  |
|-----------------|--------------------------|------|---------|-----------------------------|
| `id`            | bigint unsigned (PK, AI) | No   | —       | Primary key                 |
| `nama_kategori` | varchar(255)             | No   | —       | Nama kategori arsip         |
| `created_at`    | timestamp                | Yes  | NULL    |                             |
| `updated_at`    | timestamp                | Yes  | NULL    |                             |

**Relasi:**
- `categories` 1 → N `classifications`
- `categories` 1 → N `archives`

---

### 3. `classifications`

Master data klasifikasi arsip (sub-kategori). Setiap klasifikasi merujuk ke satu kategori dan menyimpan aturan retensi.

| Kolom              | Tipe                          | Null | Default    | Keterangan                          |
|--------------------|-------------------------------|------|------------|-------------------------------------|
| `id`               | bigint unsigned (PK, AI)      | No   | —          | Primary key                         |
| `category_id`      | bigint unsigned (FK)          | No   | —          | → `categories.id` (cascade delete)  |
| `code`             | varchar(50), UNIQUE           | No   | —          | Kode klasifikasi (contoh: "HK.01")  |
| `nama_klasifikasi` | varchar(1000)                 | No   | —          | Nama/deskripsi klasifikasi          |
| `retention_aktif`  | int                           | No   | 0          | Masa retensi arsip aktif (tahun)    |
| `retention_inaktif`| int                           | No   | 0          | Masa retensi arsip inaktif (tahun)  |
| `nasib_akhir`      | enum('Musnah','Permanen')     | No   | 'Permanen' | Nasib akhir arsip setelah retensi   |
| `created_at`       | timestamp                     | Yes  | NULL       |                                     |
| `updated_at`       | timestamp                     | Yes  | NULL       |                                     |

**Relasi:**
- `classifications` N → 1 `categories`
- `classifications` 1 → N `archives`

---

### 4. `archives`

Tabel utama sistem. Menyimpan seluruh data arsip beserta metadata lokasi penyimpanan fisik, status retensi, dan audit trail.

| Kolom                   | Tipe                                                              | Null | Default          | Keterangan                                    |
|-------------------------|-------------------------------------------------------------------|------|------------------|-----------------------------------------------|
| `id`                    | bigint unsigned (PK, AI)                                          | No   | —                | Primary key                                   |
| `category_id`           | bigint unsigned (FK)                                              | No   | —                | → `categories.id` (cascade delete)            |
| `classification_id`     | bigint unsigned (FK)                                              | No   | —                | → `classifications.id` (cascade delete)       |
| `index_number`          | varchar(255), UNIQUE                                              | No   | —                | Nomor arsip (unik)                            |
| `description`           | text                                                              | No   | —                | Uraian/deskripsi arsip                        |
| `lampiran_surat`        | text                                                              | Yes  | NULL             | Lampiran surat (teks paragraf)                |
| `kurun_waktu_start`     | date                                                              | No   | —                | Tanggal/tahun arsip                           |
| `tingkat_perkembangan`  | varchar (was enum: Asli/Salinan/Tembusan)                        | No   | 'Asli'           | Tingkat perkembangan dokumen                  |
| `skkad`                 | enum('SANGAT RAHASIA','TERBATAS','RAHASIA','BIASA/TERBUKA')      | No   | 'BIASA/TERBUKA'  | Sifat kerahasiaan dokumen                     |
| `box_number`            | int unsigned                                                      | Yes  | NULL             | Nomor box penyimpanan (global)                |
| `file_number`           | int unsigned                                                      | Yes  | NULL             | Nomor file dalam box                          |
| `rack_number`           | smallint unsigned                                                 | Yes  | NULL             | ID rak penyimpanan → `storage_racks.id`       |
| `row_number`            | smallint unsigned                                                 | Yes  | NULL             | Nomor baris/shelf dalam rak                   |
| `is_manual_input`       | tinyint(1)                                                        | No   | 0                | Flag input manual (non-JRA)                   |
| `definitive_number`     | int                                                               | Yes  | NULL             | Nomor definitif otomatis                      |
| `year_detected`         | int                                                               | Yes  | NULL             | Tahun terdeteksi otomatis                     |
| `sort_order`            | int                                                               | Yes  | NULL             | Urutan tampilan                               |
| `manual_retention_aktif`| int                                                               | Yes  | NULL             | Retensi aktif manual (override)               |
| `manual_retention_inaktif`| int                                                             | Yes  | NULL             | Retensi inaktif manual (override)             |
| `manual_nasib_akhir`    | enum('Musnah','Permanen','Dinilai Kembali')                       | Yes  | NULL             | Nasib akhir manual (override)                 |
| `jumlah_berkas`         | int                                                               | No   | —                | Jumlah berkas/dokumen                         |
| `ket`                   | varchar(255)                                                      | Yes  | NULL             | Keterangan tambahan                           |
| `retention_aktif`       | int                                                               | No   | —                | Masa retensi aktif (dari klasifikasi)         |
| `retention_inaktif`     | int                                                               | No   | —                | Masa retensi inaktif (dari klasifikasi)       |
| `transition_active_due` | date                                                              | No   | —                | Tanggal jatuh tempo transisi ke Inaktif       |
| `transition_inactive_due`| date                                                             | No   | —                | Tanggal jatuh tempo transisi ke Musnah        |
| `status`                | varchar/enum('Aktif','Inaktif','Musnah')                          | No   | 'Aktif'          | Status arsip saat ini                         |
| `evaluation_notes`      | text                                                              | Yes  | NULL             | Catatan evaluasi/penilaian kembali            |
| `manual_status_override`| tinyint(1)                                                        | No   | 0                | Flag: status di-override secara manual        |
| `manual_override_at`    | timestamp                                                         | Yes  | NULL             | Waktu override manual                         |
| `manual_override_by`    | bigint unsigned (FK)                                              | Yes  | NULL             | → `users.id` (siapa yang override)            |
| `created_by`            | bigint unsigned (FK)                                              | No   | —                | → `users.id` (pembuat arsip)                  |
| `updated_by`            | bigint unsigned (FK)                                              | Yes  | NULL             | → `users.id` (editor terakhir)                |
| `created_at`            | timestamp                                                         | Yes  | NULL             |                                               |
| `updated_at`            | timestamp                                                         | Yes  | NULL             |                                               |

**Relasi:**
- `archives` N → 1 `categories`
- `archives` N → 1 `classifications`
- `archives` N → 1 `users` (created_by)
- `archives` N → 1 `users` (updated_by)
- `archives` N → 1 `users` (manual_override_by)
- `archives` N → 1 `storage_racks` (melalui rack_number → storage_racks.id, soft reference)

---

### 5. `storage_racks`

Data master rak penyimpanan fisik arsip.

| Kolom                | Tipe                                       | Null | Default    | Keterangan                        |
|----------------------|--------------------------------------------|------|------------|-----------------------------------|
| `id`                 | bigint unsigned (PK, AI)                   | No   | —          | Primary key                       |
| `name`               | varchar(100)                               | No   | —          | Nama rak (contoh: "Rak 1")        |
| `description`        | text                                       | Yes  | NULL       | Deskripsi rak                     |
| `total_rows`         | int                                        | No   | 0          | Jumlah baris/shelf dalam rak      |
| `total_boxes`        | int                                        | No   | 0          | Total box dalam rak               |
| `capacity_per_box`   | int                                        | No   | 50         | Kapasitas default per box         |
| `status`             | enum('active','inactive','maintenance')    | No   | 'active'   | Status rak                        |
| `year_start`         | int                                        | Yes  | NULL       | Tahun awal arsip dalam rak        |
| `year_end`           | int                                        | Yes  | NULL       | Tahun akhir arsip dalam rak       |
| `created_at`         | timestamp                                  | Yes  | NULL       |                                   |
| `updated_at`         | timestamp                                  | Yes  | NULL       |                                   |

**Relasi:**
- `storage_racks` 1 → N `storage_rows`
- `storage_racks` 1 → N `storage_boxes`
- `storage_racks` 1 → 1 `storage_capacity_settings`

---

### 6. `storage_rows`

Baris/shelf di dalam sebuah rak penyimpanan.

| Kolom             | Tipe                                          | Null | Default     | Keterangan                          |
|-------------------|-----------------------------------------------|------|-------------|-------------------------------------|
| `id`              | bigint unsigned (PK, AI)                      | No   | —           | Primary key                         |
| `rack_id`         | bigint unsigned (FK)                          | No   | —           | → `storage_racks.id` (cascade)      |
| `row_number`      | int                                           | No   | —           | Nomor baris dalam rak (1, 2, 3, …)  |
| `total_boxes`     | int                                           | No   | 0           | Total box dalam baris ini           |
| `available_boxes` | int                                           | No   | 0           | Box yang masih tersedia             |
| `status`          | enum('available','full','maintenance')        | No   | 'available' | Status baris                        |
| `created_at`      | timestamp                                     | Yes  | NULL        |                                     |
| `updated_at`      | timestamp                                     | Yes  | NULL        |                                     |

**Unique constraint:** `(rack_id, row_number)` — nomor baris unik per rak.

---

### 7. `storage_boxes`

Box/kotak fisik di dalam sebuah baris rak.

| Kolom           | Tipe                                                     | Null | Default     | Keterangan                              |
|-----------------|----------------------------------------------------------|------|-------------|-----------------------------------------|
| `id`            | bigint unsigned (PK, AI)                                 | No   | —           | Primary key                             |
| `rack_id`       | bigint unsigned (FK)                                     | No   | —           | → `storage_racks.id` (cascade)          |
| `row_id`        | bigint unsigned (FK)                                     | No   | —           | → `storage_rows.id` (cascade)           |
| `box_number`    | int, UNIQUE                                              | No   | —           | Nomor box global (unik di seluruh sistem)|
| `archive_count` | int                                                      | No   | 0           | Jumlah arsip saat ini dalam box         |
| `capacity`      | int                                                      | No   | 50          | Kapasitas maksimum box                  |
| `status`        | enum('available','partially_full','full','reserved')     | No   | 'available' | Status box                              |
| `created_at`    | timestamp                                                | Yes  | NULL        |                                         |
| `updated_at`    | timestamp                                                | Yes  | NULL        |                                         |

**Unique constraints:**
- `(rack_id, row_id, box_number)` — kombinasi unik per rak+baris+box
- `box_number` — nomor box unik secara global

---

### 8. `storage_capacity_settings`

Pengaturan kapasitas dan ambang peringatan untuk setiap rak.

| Kolom                      | Tipe                     | Null | Default | Keterangan                               |
|----------------------------|--------------------------|------|---------|------------------------------------------|
| `id`                       | bigint unsigned (PK, AI) | No   | —       | Primary key                              |
| `rack_id`                  | bigint unsigned (FK)     | No   | —       | → `storage_racks.id` (cascade)           |
| `default_capacity_per_box` | int                      | No   | 50      | Kapasitas default per box dalam rak ini  |
| `warning_threshold`        | int                      | No   | 40      | Batas peringatan (80% kapasitas)         |
| `auto_assign`              | tinyint(1)               | No   | 1       | Penempatan lokasi otomatis               |
| `created_at`               | timestamp                | Yes  | NULL    |                                          |
| `updated_at`               | timestamp                | Yes  | NULL    |                                          |

---

### 9. `roles`

Tabel peran pengguna (dikelola oleh Spatie Laravel Permission).

| Kolom        | Tipe                     | Null | Default | Keterangan                        |
|--------------|--------------------------|------|---------|-----------------------------------|
| `id`         | bigint unsigned (PK, AI) | No   | —       | Primary key                       |
| `name`       | varchar(255)             | No   | —       | Nama role (contoh: admin, intern) |
| `guard_name` | varchar(255)             | No   | —       | Guard Laravel (contoh: web)       |
| `created_at` | timestamp                | Yes  | NULL    |                                   |
| `updated_at` | timestamp                | Yes  | NULL    |                                   |

**Unique constraint:** `(name, guard_name)`

**Data di sistem:**
| name    | Akses                                           |
|---------|-------------------------------------------------|
| `admin` | Full akses: CRUD arsip, master data, laporan    |
| `intern`| Terbatas: input & edit arsip milik sendiri saja |

---

### 10. `permissions`

Hak akses granular (dikelola Spatie).

| Kolom        | Tipe                     | Null | Default | Keterangan              |
|--------------|--------------------------|------|---------|-------------------------|
| `id`         | bigint unsigned (PK, AI) | No   | —       | Primary key             |
| `name`       | varchar(255)             | No   | —       | Nama permission         |
| `guard_name` | varchar(255)             | No   | —       | Guard Laravel           |
| `created_at` | timestamp                | Yes  | NULL    |                         |
| `updated_at` | timestamp                | Yes  | NULL    |                         |

---

### 11. `model_has_roles`

Tabel pivot yang menghubungkan model (User) dengan role.

| Kolom        | Tipe             | Null | Keterangan                        |
|--------------|------------------|------|-----------------------------------|
| `role_id`    | bigint unsigned  | No   | → `roles.id` (FK)                 |
| `model_type` | varchar(255)     | No   | Nama class model (App\Models\User)|
| `model_id`   | bigint unsigned  | No   | ID dari model yang bersangkutan   |

**Primary key:** `(role_id, model_id, model_type)`

---

### 12. `model_has_permissions`

Tabel pivot untuk permission langsung pada model/user.

| Kolom           | Tipe             | Null | Keterangan                         |
|-----------------|------------------|------|------------------------------------|
| `permission_id` | bigint unsigned  | No   | → `permissions.id` (FK)            |
| `model_type`    | varchar(255)     | No   | Nama class model                   |
| `model_id`      | bigint unsigned  | No   | ID dari model yang bersangkutan    |

---

### 13. `role_has_permissions`

Tabel pivot yang menghubungkan role dengan permission.

| Kolom           | Tipe             | Null | Keterangan              |
|-----------------|------------------|------|-------------------------|
| `permission_id` | bigint unsigned  | No   | → `permissions.id` (FK) |
| `role_id`       | bigint unsigned  | No   | → `roles.id` (FK)       |

**Primary key:** `(permission_id, role_id)`

---

### 14. `personal_access_tokens`

Token API untuk Laravel Sanctum.

| Kolom            | Tipe                     | Null | Keterangan                              |
|------------------|--------------------------|------|-----------------------------------------|
| `id`             | bigint unsigned (PK, AI) | No   | Primary key                             |
| `tokenable_type` | varchar(255)             | No   | Nama class model pemilik token          |
| `tokenable_id`   | bigint unsigned          | No   | ID model pemilik token                  |
| `name`           | text                     | No   | Nama token                              |
| `token`          | varchar(64), UNIQUE      | No   | Hash token                              |
| `abilities`      | text                     | Yes  | Kemampuan/scope token (JSON)            |
| `last_used_at`   | timestamp                | Yes  | Terakhir digunakan                      |
| `expires_at`     | timestamp (indexed)      | Yes  | Waktu kedaluwarsa                       |
| `created_at`     | timestamp                | Yes  |                                         |
| `updated_at`     | timestamp                | Yes  |                                         |

---

### 15. `sessions`

Menyimpan sesi login pengguna (database session driver).

| Kolom           | Tipe             | Null | Keterangan                          |
|-----------------|------------------|------|-------------------------------------|
| `id`            | varchar(255) PK  | No   | Session ID                          |
| `user_id`       | bigint unsigned  | Yes  | ID pengguna yang login (indexed)    |
| `ip_address`    | varchar(45)      | Yes  | Alamat IP pengguna                  |
| `user_agent`    | text             | Yes  | Browser/user agent                  |
| `payload`       | longtext         | No   | Data sesi (serialized)              |
| `last_activity` | int (indexed)    | No   | Unix timestamp aktivitas terakhir   |

---

### 16. `password_reset_tokens`

Token untuk proses lupa/reset password.

| Kolom        | Tipe             | Null | Keterangan          |
|--------------|------------------|------|---------------------|
| `email`      | varchar(255) PK  | No   | Email pengguna      |
| `token`      | varchar(255)     | No   | Token reset         |
| `created_at` | timestamp        | Yes  | Waktu dibuat        |

---

### 17. `cache` & `cache_locks`

Tabel untuk Laravel Cache driver (database).

**`cache`**

| Kolom        | Tipe          | Keterangan             |
|--------------|---------------|------------------------|
| `key`        | varchar PK    | Cache key              |
| `value`      | mediumtext    | Nilai cache            |
| `expiration` | int           | Unix timestamp expired |

**`cache_locks`**

| Kolom        | Tipe       | Keterangan             |
|--------------|------------|------------------------|
| `key`        | varchar PK | Lock key               |
| `owner`      | varchar    | Pemilik lock           |
| `expiration` | int        | Unix timestamp expired |

---

### 18. `jobs`

Tabel antrian pekerjaan background (Laravel Queue).

| Kolom          | Tipe             | Keterangan                   |
|----------------|------------------|------------------------------|
| `id`           | bigint PK AI     | Primary key                  |
| `queue`        | varchar (indexed)| Nama queue                   |
| `payload`      | longtext         | Data pekerjaan (JSON)        |
| `attempts`     | tinyint unsigned | Jumlah percobaan             |
| `reserved_at`  | int unsigned     | Waktu diklaim worker         |
| `available_at` | int unsigned     | Waktu tersedia               |
| `created_at`   | int unsigned     | Waktu dibuat                 |

---

## Relasi Lengkap (Foreign Key)

| Tabel (FK)               | Kolom                 | Referensi                    | On Delete     |
|--------------------------|-----------------------|------------------------------|---------------|
| `classifications`        | `category_id`         | `categories.id`              | CASCADE       |
| `archives`               | `category_id`         | `categories.id`              | CASCADE       |
| `archives`               | `classification_id`   | `classifications.id`         | CASCADE       |
| `archives`               | `created_by`          | `users.id`                   | RESTRICT      |
| `archives`               | `updated_by`          | `users.id`                   | RESTRICT      |
| `archives`               | `manual_override_by`  | `users.id`                   | RESTRICT      |
| `archives`               | `rack_number`         | `storage_racks.id` (soft*)   | —             |
| `storage_rows`           | `rack_id`             | `storage_racks.id`           | CASCADE       |
| `storage_boxes`          | `rack_id`             | `storage_racks.id`           | CASCADE       |
| `storage_boxes`          | `row_id`              | `storage_rows.id`            | CASCADE       |
| `storage_capacity_settings` | `rack_id`          | `storage_racks.id`           | CASCADE       |
| `model_has_roles`        | `role_id`             | `roles.id`                   | CASCADE       |
| `model_has_permissions`  | `permission_id`       | `permissions.id`             | CASCADE       |
| `role_has_permissions`   | `permission_id`       | `permissions.id`             | CASCADE       |
| `role_has_permissions`   | `role_id`             | `roles.id`                   | CASCADE       |

> \* `archives.rack_number` menyimpan nilai `storage_racks.id` secara logis (bukan FK constraint formal), sehingga query ke rak dilakukan melalui `WHERE rack_number = storage_racks.id`.

---

## Alur Bisnis Database

### 1. Pendaftaran & Login Pengguna

```
[Admin membuat akun] → INSERT users
                     → INSERT model_has_roles (role: admin/intern)
[Login] → SELECT users WHERE email = ? AND password = ?
        → INSERT sessions (session aktif)
```

### 2. Input Arsip Baru

```
[Pilih Kategori]     → SELECT categories
[Pilih Klasifikasi]  → SELECT classifications WHERE category_id = ?
                       (ambil retention_aktif, retention_inaktif, nasib_akhir)
[Isi Form Arsip]     → Hitung transition_active_due  = kurun_waktu_start + retention_aktif (tahun)
                     → Hitung transition_inactive_due = transition_active_due + retention_inaktif (tahun)
[Simpan]             → INSERT archives (status = 'Aktif', created_by = auth()->id())
```

### 3. Penempatan Lokasi Penyimpanan Fisik

```
[Pilih Rak]   → SELECT storage_racks WHERE status = 'active'
[Pilih Baris] → SELECT storage_rows WHERE rack_id = ?
[Pilih Box]   → SELECT storage_boxes WHERE rack_id = ? AND row_id = ?
[Simpan]      → UPDATE archives SET rack_number=?, row_number=?, box_number=?, file_number=?
              → UPDATE storage_boxes SET archive_count = archive_count + 1
              → UPDATE storage_boxes SET status = (cek kapasitas)
```

### 4. Perubahan Status Arsip

```
Status Otomatis (berdasarkan tanggal):
  Aktif → Inaktif  : ketika TODAY >= transition_active_due
  Inaktif → Musnah : ketika TODAY >= transition_inactive_due

Status Manual (oleh Admin):
  UPDATE archives SET
    status = ?,
    manual_status_override = true,
    manual_override_at = NOW(),
    manual_override_by = auth()->id(),
    evaluation_notes = ?
```

### 5. Alur Retensi Arsip

```
Arsip dibuat (Aktif)
     │
     ├── retention_aktif (tahun) ──→ transition_active_due
     │                                      │
     │                              Arsip → Inaktif
     │                                      │
     └── retention_inaktif (tahun) ──→ transition_inactive_due
                                               │
                                   nasib_akhir = 'Musnah'  → Arsip dimusnahkan
                                   nasib_akhir = 'Permanen' → Arsip disimpan permanen
```

### 6. Akses Berbasis Peran (RBAC)

```
Admin:
  - CRUD semua arsip
  - Kelola kategori & klasifikasi
  - Kelola storage (rak, baris, box)
  - Override status arsip
  - Lihat laporan retensi
  - Operasi massal (bulk)

Intern:
  - Input arsip baru (created_by = dirinya)
  - Edit arsip yang ia buat sendiri
  - Hapus arsip yang ia buat sendiri
  - Lihat semua arsip (read-only untuk arsip milik orang lain)
  - Export Excel
```

---

## Nilai Enum Per Kolom

| Tabel             | Kolom                   | Nilai yang Valid                                        |
|-------------------|-------------------------|---------------------------------------------------------|
| `users`           | `role_type`             | `admin`, `intern`                                       |
| `classifications` | `nasib_akhir`           | `Musnah`, `Permanen`                                    |
| `archives`        | `tingkat_perkembangan`  | `Asli`, `Salinan`, `Tembusan`                           |
| `archives`        | `skkad`                 | `SANGAT RAHASIA`, `TERBATAS`, `RAHASIA`, `BIASA/TERBUKA`|
| `archives`        | `status`                | `Aktif`, `Inaktif`, `Musnah`                            |
| `archives`        | `manual_nasib_akhir`    | `Musnah`, `Permanen`, `Dinilai Kembali`                 |
| `storage_racks`   | `status`                | `active`, `inactive`, `maintenance`                     |
| `storage_rows`    | `status`                | `available`, `full`, `maintenance`                      |
| `storage_boxes`   | `status`                | `available`, `partially_full`, `full`, `reserved`       |

---

*Dokumen ini di-generate dari migration files Laravel — ARSIPIN v1.0*
