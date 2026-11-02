# Dewala — Mini HRIS dengan Sistem Promosi Berbasis Merit

Dewala memiliki mesin validasi promosi otomatis. Sistem memblokir pengajuan
promosi — baik di level UI (tombol ter-disable) maupun di level API/server —
apabila seorang karyawan belum memenuhi kriteria career path, masa kerja,
skor kompetensi, dan rekam jejak (keterlambatan & SP) yang disyaratkan.

## Tech Stack
- **Backend**: PHP Native (tanpa framework)
- **Database**: MySQL (PDO, prepared statements)
- **Styling**: Tailwind CSS (via CDN)
- **Interaktivitas**: Alpine.js (via CDN)

## Struktur Folder

```
dewala-hris/
├── api/
│   └── promotion_check.php     → Endpoint JSON: cek kelayakan promosi 1 karyawan
├── config/
│   ├── config.php              → base_url() — auto-detect subfolder/document root
│   └── database.php            → Koneksi PDO MySQL
├── database/
│   ├── schema.sql               → DDL 8 tabel
│   └── seed.php                 → Data contoh (akun demo, 4 skenario kelayakan)
├── includes/
│   ├── bootstrap.php            → Satu pintu include untuk semua modul
│   ├── auth.php                 → Session, requireLogin(), requireRole()
│   ├── functions.php            → Helper umum (flash, csrf, format tanggal, dst.)
│   ├── PromotionEngine.php      → Core engine validasi 4 matriks promosi
│   ├── header.php / sidebar.php / footer.php  → Layout bersama
├── modules/
│   ├── auth/                    → login.php, logout.php
│   ├── master/                  → CRUD departments.php, positions.php, employees.php
│   ├── career-path/             → index.php — Career Path Configurator (HR)
│   ├── track-record/            → index.php — catat keterlambatan/SP
│   ├── competency/              → index.php — penilaian teknikal & soft-skill
│   └── promotion/                → dashboard.php, request.php, decide.php
└── index.php                     → Redirect ke dashboard atau login
```

## Instalasi Lokal (XAMPP / Laragon)

1. Salin folder `dewala-hris` ke dalam `htdocs` (XAMPP) atau `www` (Laragon).
2. Import skema database:
   ```
   mysql -u root -p < database/schema.sql
   ```
   (atau lewat phpMyAdmin: buat database baru lalu import file tersebut)
3. Sesuaikan kredensial di `config/database.php` bila perlu (default: host
   `localhost`, user `root`, password kosong).
4. Isi data contoh:
   ```
   php database/seed.php
   ```
   atau buka `http://localhost/dewala-hris/database/seed.php` di browser.
5. Akses aplikasi di `http://localhost/dewala-hris/`.

### Menjalankan tanpa Apache (PHP built-in server)
```
cd dewala-hris
php -S localhost:8000
```
Buka `http://localhost:8000/`.

## Akun Demo
Semua akun memakai password: `password123`

| Username        | Role     | Skenario                                     |
|-----------------|----------|-----------------------------------------------|
| hr_admin        | HR       | Akses penuh — data master, career path, finalisasi promosi |
| budi_manager    | Manajer  | Atasan dari 4 staff di bawah ini              |
| andi_staff      | Staff    | ✅ Memenuhi seluruh syarat promosi             |
| citra_staff     | Staff    | ❌ Skor kompetensi di bawah standar (75 vs 80) |
| dedi_staff      | Staff    | ❌ Memiliki SP2 aktif dalam 1 tahun terakhir   |
| eka_staff       | Staff    | ❌ Masa kerja & skor belum cukup               |

Login sebagai `budi_manager` untuk melihat tombol **"Ajukan Promosi"** aktif
pada Andi dan ter-disable pada Citra/Dedi/Eka beserta alasan gagalnya.

## Cara Kerja Mesin Validasi Promosi

`includes/PromotionEngine.php` mengevaluasi 4 matriks berikut untuk setiap
karyawan, diekspos lewat `api/promotion_check.php` sebagai JSON. Tombol
**"Ajukan Promosi"** di Dasbor Merit (Alpine.js `x-init="load()"` memanggil
endpoint tersebut) otomatis ter-disable jika salah satu kriteria gagal:

1. **Career Path** — harus ada jalur karir terdaftar dari posisi saat ini
   (dikonfigurasi HR lewat Career Path Configurator).
2. **Tenur** — masa kerja di posisi saat ini ≥ minimum jalur tersebut.
3. **Kompetensi** — rata-rata skor teknikal & soft-skill terbaru ≥ minimum
   posisi tujuan.
4. **Rekam Jejak** — keterlambatan 6 bulan terakhir ≤ kuota, DAN tidak ada
   SP (SP1/SP2/SP3) aktif dalam periode lockout (default 12 bulan).

Validasi yang sama dijalankan ulang di server (`modules/promotion/request.php`)
saat form disubmit — status "ineligible" tidak bisa dilewati hanya dengan
memanipulasi atribut `disabled` di sisi klien.

## Alur Promosi
1. Manajer (atau HR) membuka Dasbor Merit, melihat status kelayakan tiap
   anggota tim beserta progress bar dan indikator merah pada kriteria yang
   gagal (mis. "Gagal: Skor Kompetensi saat ini 75, Butuh 80").
2. Jika eligible, tombol "Ajukan Promosi" aktif → membuat baris `promotions`
   berstatus `pending`.
3. HR memfinalisasi (`Setujui` / `Tolak`) dari Dasbor Merit. Jika disetujui,
   `position_id` & `position_start_date` karyawan otomatis diperbarui.

## Catatan Keamanan
- Password disimpan ter-hash (`password_hash` / `password_verify`, bcrypt).
- Semua form POST dilindungi CSRF token (`csrf_field()` / `verify_csrf()`).
- Seluruh query database memakai prepared statements (PDO).
- RBAC diterapkan di level server (`requireRole()`) pada setiap file modul —
  bukan hanya menyembunyikan menu di sisi UI.
- Cakupan data (scoping) manajer dibatasi ke anak buah langsungnya di level
  query SQL (`WHERE manager_id = ?`), bukan hanya di tampilan.
