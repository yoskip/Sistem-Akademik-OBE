# Audit Keamanan Autentikasi dan Session – Sistem Akademik Berbasis OBE

Dokumen ini memuat hasil audit mendalam terhadap struktur kode, mekanisme autentikasi, serta pengelolaan sesi pada aplikasi Sistem Informasi Kurikulum Berbasis Outcome-Based Education (OBE).

---

## 1. Struktur Folder Proyek

Proyek ini dibangun menggunakan **PHP Native** dan **MySQL** yang berjalan pada web server **Apache (XAMPP)**. Struktur folder proyek adalah sebagai berikut:

```text
c:\xampp\htdocs\obe\
├── .gitignore                      # Konfigurasi file yang diabaikan Git
├── index.php                       # Pintu masuk utama (redirect login / dashboard)
├── assets/
│   ├── css/style.css               # Desain antarmuka & tema UI Bootstrap kustom
│   └── js/app.js                   # Skrip antarmuka pengguna (sidebar toggle, konfirmasi)
├── auth/
│   ├── login.php                   # Form login, CSRF, validasi, rate limit, regenerate ID
│   └── logout.php                  # Penghancuran sesi server & pembersihan cookie browser
├── bahan_kajian/
│   └── index.php                   # Modul Bahan Kajian (Master Kurikulum)
├── config/
│   └── database.php                # Koneksi PDO MySQL & konstanta global aplikasi
├── cpl/
│   └── index.php                   # Modul Capaian Pembelajaran Lulusan (CPL)
├── cpmk/
│   └── index.php                   # Modul Capaian Pembelajaran Mata Kuliah (CPMK)
├── dashboard/
│   └── index.php                   # Dashboard ringkasan statistik & progres kurikulum
├── database/
│   ├── db_obe.sql                  # Skema database awal & data kurikulum OBE
│   ├── migrate_passwords.php       # CLI migrasi password lama ke hash bcrypt
│   └── migration_security.sql      # Skema tabel login_attempts untuk rate limiting
├── docs/
│   └── autentikasi/                # Dokumentasi audit, konfigurasi, dan pengujian sesi
├── import/
│   ├── import_excel.php            # Skrip CLI pemindah data spreadsheet kurikulum
│   └── kurikulum.xlsx              # Dokumen sumber kurikulum OBE
├── includes/
│   ├── auth.php                    # Guard autentikasi (require_login)
│   ├── footer.php                  # Template penutup HTML & pustaka JavaScript
│   ├── forbidden.php               # Halaman respons HTTP 403 Forbidden
│   ├── functions.php               # Fungsi inti, konfigurasi sesi, CSRF, timeout
│   ├── header.php                  # Template pembuka HTML, topbar, user chip
│   └── sidebar.php                 # Navigasi modul kurikulum OBE & hak akses peran
├── mata_kuliah/
│   └── index.php                   # Modul Mata Kuliah
├── monitoring/
│   └── kelengkapan.php             # Modul Inventaris Kelengkapan Dokumen MK
├── pemetaan/
│   ├── bk_mk.php                   # Pemetaan Bahan Kajian ke Mata Kuliah
│   ├── cpl_bk.php                  # Pemetaan CPL ke Bahan Kajian
│   ├── cpl_bk_mk.php               # Pemetaan CPL - Bahan Kajian - Mata Kuliah
│   ├── cpl_mk.php                  # Pemetaan CPL ke Mata Kuliah
│   ├── cpl_pl.php                  # Pemetaan CPL ke Profil Lulusan (PL)
│   └── peta_cpl.php                # Matriks Peta Pemenuhan CPL
├── penilaian/
│   ├── bobot.php                   # Penilaian: Bobot CPMK & Sub-CPMK
│   ├── mekanisme.php               # Penilaian: Tahap & Mekanisme
│   └── teknik.php                  # Penilaian: Teknik Penilaian CPMK
├── profil_lulusan/
│   └── index.php                   # Modul Profil Lulusan / Program Learning (PL)
├── rps/
│   ├── index.php                   # Rencana Pembelajaran Semester (RPS) Dosen
│   ├── rumusan_cpl.php             # Rumusan Akhir CPL
│   └── rumusan_mk.php              # Rumusan Akhir MK
├── sub_cpmk/
│   └── index.php                   # Modul Sub-CPMK
├── tests/
│   ├── auth_flow_test.php          # Uji integrasi alur login hingga logout
│   ├── password_hashing_test.php   # Uji enkripsi bcrypt & upgrade akun lama
│   ├── rate_limit_test.php         # Uji proteksi brute force (HTTP 429)
│   ├── session_logout_test.php     # Uji Test 2: Invalidasi sesi setelah logout
│   ├── session_regeneration_test.php # Uji Test 1: Regenerasi ID sesi saat login
│   └── session_timeout_test.php    # Uji logika idle & absolute timeout
└── users/
    └── index.php                   # Modul Manajemen Pengguna (Khusus Admin)
```

---

## 2. File Koneksi Database

- **File:** `config/database.php`
- **Fungsi:** Menyediakan koneksi singleton PDO melalui fungsi `db()`.
- **Kredensial:** Mengambil kredensial dari environment variable (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) dengan nilai bawaan lokal XAMPP (`127.0.0.1`, `db_obe`, `root`, `""`).
- **Keamanan Konfigurasi:** Mengatur `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`, `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`, dan `PDO::ATTR_EMULATE_PREPARES => false` (mencegah SQL Injection melalui real prepared statements). Detail kegagalan koneksi disembunyikan dari pengguna agar tidak membocorkan informasi server.

---

## 3. File Login dan Verifikasi Kredensial

- **File Form & Proses:** `auth/login.php`
- **File Logika Autentikasi:** `includes/functions.php`
- **Alur Verifikasi:**
  1. Halaman memuat proteksi anti-cache melalui `send_no_cache_headers()`.
  2. Memeriksa apakah pengguna sudah login via `is_logged_in()`; jika sudah, dialihkan ke `dashboard/index.php`.
  3. Memvalidasi token CSRF via `verify_csrf()`.
  4. Memeriksa rate limiting brute-force via `is_login_blocked()`. Jika 5 kali berturut-turut gagal dalam rentang 15 menit, request ditolak dengan HTTP 429 (Too Many Requests).
  5. Mencari data akun pengguna berdasarkan username atau email menggunakan prepared statement PDO.
  6. Memverifikasi password menggunakan `verify_and_upgrade_password()` yang memanggil fungsi bawaan PHP `password_verify()`. Jika akun tidak ditemukan, fungsi `burn_password_verification()` dipanggil untuk menyamakan waktu komputasi sehingga kebal terhadap timing attack.
  7. Jika verifikasi berhasil dan status akun aktif (`aktif == 1`), sistem menjalankan:
     - `session_regenerate_id(true)` untuk membuang session ID lama.
     - Menyimpan data minimal pengguna ke `$_SESSION['user']` (ID, nama, username, role).
     - Mencatat `$_SESSION['login_at']` dan `$_SESSION['last_activity']`.
     - Menghapus token CSRF lama `unset($_SESSION['csrf'])`.
     - Mencatat log audit ke tabel `audit_logs` dan mengarahkan pengguna ke dashboard.

---

## 4. Status Integrasi LDAP Universitas YARSI (LDAP UY)

Berdasarkan audit mendalam terhadap seluruh basis kode proyek:
- **Status Aktual:** **LDAP UY belum terintegrasi di dalam proyek ini.**
- **Temuan Teknis:**
  1. Tidak ditemukan berkas, konfigurasi, maupun pemanggilan fungsi LDAP PHP (seperti `ldap_connect()`, `ldap_bind()`, `ldap_search()`, atau protokol LDAPS).
  2. Ekstensi `ldap` pada instalasi PHP XAMPP lokal saat ini belum dimuat/diaktifkan (`php -m` tidak memuat modul `ldap`).
  3. Autentikasi yang berjalan saat ini adalah autentikasi akun internal menggunakan tabel basis data `users` dan `roles` pada MySQL (`db_obe`), dengan verifikasi hash bcrypt melalui `password_verify()`.
- **Kepatuhan Terhadap Aturan:**
  Sesuai instruksi tugas, pengembang **dilarang mengarang bahwa LDAP sudah terintegrasi**. Oleh karena itu, sistem login yang ada tidak diubah secara artifisial menjadi LDAP tiruan (*mock*).
- **Kesiapan Arsitektur Sesi Pasca-Login (Mode A):**
  Fokus penugasan **Mode A – Proyek LDAP UY: Sesi Setelah Login LDAP** adalah memastikan bahwa begitu proses verifikasi identitas pengguna berhasil (baik dari basis data lokal maupun saat server LDAP UY nantinya dihubungkan), pengelolaan sesi di sisi server dan peramban klien memenuhi standar keamanan tertinggi:
  - Session ID langsung diregenerasi (`session_regenerate_id(true)`).
  - Kredensial, password LDAP, maupun token sensitif **tidak pernah** disimpan ke dalam sesi.
  - Sesi diproteksi dengan cookie flags (*HttpOnly*, *SameSite=Lax*, *Secure* dinamis), batas waktu ganda (*Idle* 30 menit & *Absolute* 8 jam), serta mekanisme pembersihan menyeluruh saat logout.
- **Prasyarat untuk Integrasi LDAP UY Mendatang:**
  1. Mengaktifkan ekstensi `extension=ldap` pada berkas konfigurasi `php.ini` XAMPP.
  2. Menyiapkan konfigurasi parameter LDAP UY (Host, Port, Base DN, Bind DN/Domain) pada environment server.
  3. Mengarahkan fungsi verifikasi di `auth/login.php` untuk melakukan bind ke server LDAP UY, lalu langsung memanfaatkan alur pengamanan sesi yang sudah terpasang saat ini.

---

## 5. Pemanggilan `session_start()` di Seluruh Proyek

Berdasarkan audit penelusuran kode di seluruh folder proyek, pemanggilan `session_start()` ditemukan **hanya pada 1 lokasi terpusat**, yaitu:
- **Lokasi:** `includes/functions.php` baris 66 di dalam fungsi `start_secure_session()`.

Seluruh file modul lainnya **tidak pernah** memanggil `session_start()` secara terpisah, melainkan mengikutsertakan `includes/auth.php` atau `includes/functions.php`. Hal ini meminimalisasi risiko konflik *session already started* atau *headers already sent*.

---

## 5. Pemeriksaan Status Login

- **File Guard:** `includes/auth.php`
  ```php
  <?php
  require_once __DIR__ . '/functions.php';
  require_login();
  ```
- **Fungsi Pemeriksaan:** `require_login()` pada `includes/functions.php`:
  1. Memanggil `enforce_session_timeout()` untuk mengevaluasi apakah sesi telah kedaluwarsa berdasarkan idle timeout atau absolute timeout.
  2. Memeriksa `is_logged_in()` (keberadaan `$_SESSION['user']`).
  3. Jika tidak terautentikasi, segera mengarahkan pengguna ke `auth/login.php` menggunakan `exit`.
  4. Mengirimkan header anti-cache via `send_no_cache_headers()` agar data halaman terproteksi tidak tersimpan pada browser cache.
- **Pemeriksaan Role:** `require_role(array $roles)` memastikan pengguna memiliki hak akses yang sesuai (misal modul `users/index.php` hanya dapat dibuka oleh role `admin`).

---

## 6. File Logout

- **File:** `auth/logout.php`
- **Mekanisme Logout:**
  1. Memeriksa status login dan mencatat aktivitas logout ke tabel `audit_logs`.
  2. Mengirim header anti-cache via `send_no_cache_headers()`.
  3. Memanggil fungsi `destroy_session_and_cookie()`:
     - Mengosongkan data sesi di memori: `$_SESSION = []`.
     - Mengirim cookie penghapus ke browser dengan waktu kedaluwarsa lampau (`time() - 42000`) dan parameter yang cocok (path `/`, HttpOnly, SameSite Lax, Secure sesuai environment).
     - Menghancurkan file sesi fisik di server via `session_destroy()`.
  4. Mengarahkan browser ke `auth/login.php`.

---

## 7. Konfigurasi Cookie dan Sesi Saat Audit

Sebelum dilakukan perbaikan dan standardisasi, konfigurasi sesi pada `includes/functions.php` diatur sebagai berikut:
- **HttpOnly:** Diatur aktif (`true` / `'1'`).
- **SameSite:** Diatur ke `'Lax'`.
- **Path:** Diatur ke `'/'`.
- **Lifetime:** Diatur ke `0` (session cookie, hilang saat browser ditutup).
- **Secure:** Ditentukan secara dinamis melalui helper `session_cookie_secure()`, membaca variabel environment `SESSION_COOKIE_SECURE` atau mendeteksi skema HTTPS (`$_SERVER['HTTPS']`, port 443).
- **Idle Timeout:** Didefinisikan sebesar 1.800 detik (30 menit).
- **Absolute Timeout:** Didefinisikan sebesar 28.800 detik (8 jam).
- **Session Save Path:** Mengikuti pengaturan standar XAMPP (`C:\xampp\tmp`).

---

## 8. Halaman yang Wajib Dilindungi Autentikasi

Seluruh halaman berikut berstatus terproteksi dan wajib menyertakan guard autentikasi:
1. `dashboard/index.php`
2. `profil_lulusan/index.php` (Modul PL)
3. `cpl/index.php` (Modul CPL)
4. `cpmk/index.php` (Modul CPMK)
5. `sub_cpmk/index.php` (Modul Sub-CPMK)
6. `bahan_kajian/index.php`
7. `mata_kuliah/index.php`
8. `pemetaan/cpl_pl.php`
9. `pemetaan/cpl_bk.php`
10. `pemetaan/bk_mk.php`
11. `pemetaan/cpl_mk.php`
12. `pemetaan/cpl_bk_mk.php`
13. `pemetaan/peta_cpl.php`
14. `penilaian/teknik.php`
15. `penilaian/mekanisme.php`
16. `penilaian/bobot.php`
17. `rps/rumusan_mk.php`
18. `rps/rumusan_cpl.php`
19. `rps/index.php`
20. `monitoring/kelengkapan.php`
21. `users/index.php` (dilindungi tambahan dengan `require_role(['admin'])`)

File skrip CLI seperti `import/import_excel.php` dan `database/migrate_passwords.php` dilindungi dengan pengecekan `if (PHP_SAPI !== 'cli') { http_response_code(403); die(); }`.

---

## 9. Identifikasi Potensi Masalah dan Konflik

1. **Penyimpanan Sesi pada Lingkungan CLI / Testing:**
   Saat menjalankan pengujian lokal atau skrip CLI, PHP mencoba menulis file sesi ke direktori bawaan `C:\xampp\tmp`. Pada beberapa konfigurasi sandbox atau sistem Windows dengan hak akses terbatas, hal ini dapat menimbulkan peringatan `Permission denied`. Solusi yang diterapkan adalah menambahkan pemeriksaan fallback otomatis ke `sys_get_temp_dir()` khusus untuk mode CLI (`PHP_SAPI === 'cli'`).
2. **Evaluasi Cookie pada Pengujian cURL:**
   Pada skrip pengujian logout (`tests/session_logout_test.php`), parsing daftar cookie cURL (`CURLINFO_COOKIELIST`) sebelumnya belum mengabaikan cookie yang bernilai `'deleted'` atau ber-timestamp kedaluwarsa lampau. Akibatnya cURL mempertahankan entri berstatus terhapus dalam memori dan menghasilkan indikasi palsu. Logika pembacaan cookie telah diperbaiki agar secara akurat mencerminkan status cookie browser.
3. **Penyimpanan Data Sesi:**
   Perlu dipastikan tidak ada file modul yang secara tidak sengaja menyimpan kredensial atau password hash ke dalam `$_SESSION`. Audit membuktikan data sesi hanya menyimpan `id`, `nama`, `username`, dan `role`.

---

## 10. Ringkasan File yang Diperbaiki

| File | Status Awal | Perbaikan yang Dilakukan |
| :--- | :--- | :--- |
| `includes/functions.php` | Konfigurasi sesi dasar sudah ada | Ditambahkan fallback `session_save_path` aman untuk lingkungan CLI, penguatan konsistensi parameter cookie pada penghancuran sesi. |
| `tests/session_logout_test.php` | Pengujian cookie menganggap cURL menghapus baris entri | Diperbaiki helper `session_cookie_value` agar mengenali status cookie terhapus (`deleted` dan expired timestamp). |
| `tests/session_regeneration_test.php` | Helper pembacaan cookie cURL | Diselaraskan dengan penanganan status cookie aktif agar seragam dengan pengujian logout. |
| `docs/autentikasi/*` | Belum tersedia | Dibuat 6 dokumen audit, konfigurasi, pengujian, ringkasan, notulen, dan daftar bukti lengkap. |
