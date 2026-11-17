# Pengujian Keamanan Session – Sistem Akademik Berbasis OBE

Dokumen ini memuat dokumentasi pengujian keamanan autentikasi dan session management yang dijalankan secara nyata pada lingkungan Apache (XAMPP) dan PHP Native.

---

## 1. Ikhtisar Suite Pengujian

Seluruh pengujian dibangun menggunakan PHP Native dengan pustaka cURL untuk menyimulasikan interaksi HTTP peramban secara realistis.

> **Prinsip Keamanan Pengujian:**
> 1. Pengujian **tidak menggunakan akun asli** dan **tidak menyimpan password asli** dalam file kode pengujian.
> 2. Data pengguna uji dibuat secara acak dan langsung dihapus melalui blok `finally`.
> 3. Nilai riil cookie sesi dan token rahasia tidak ditampilkan di output terminal.
> 4. Pengujian dijalankan secara nyata (*real execution*) di atas web server lokal `http://127.0.0.1/obe`.

---

## 2. Test 1: Session ID Regeneration (`tests/session_regeneration_test.php`)

### A. Tujuan Pengujian
Memverifikasi bahwa aplikasi melakukan regenerasi Session ID saat pengguna berhasil login (`session_regenerate_id(true)`), sehingga Session ID lama sebelum login segera hangus dan tidak dapat digunakan untuk membajak sesi.

### B. Langkah Pengujian
1. Membuat akun dosen sementara di tabel `users` dengan kredensial acak.
2. Mengirim request `GET` ke `/auth/login.php` untuk mendapatkan cookie awal dan token CSRF.
3. Mencatat nilai cookie `PHPSESSID` sebelum login (*pre-login ID*).
4. Mengirim request `POST` login dengan identitas dan password uji.
5. Memverifikasi respons server menghasilkan kode status HTTP 302 yang mengarah ke `/dashboard/index.php`.
6. Mencatat nilai cookie `PHPSESSID` setelah login (*post-login ID*).
7. Membandingkan kedua ID sesi: memastikan Session ID baru berbeda dari Session ID lama.
8. Mencoba mengirim request `GET` ke `/dashboard/index.php` menggunakan cookie Session ID lama.
9. Memverifikasi respons server menolak cookie lama dan mengalihkan ke `/auth/login.php`.
10. Mengirim request ke `/dashboard/index.php` menggunakan cookie Session ID baru dan memastikan akses berhasil (HTTP 200).
11. Menghapus akun uji dan catatan audit log terkait.

### C. Perintah Eksekusi
```bash
php tests/session_regeneration_test.php
```

### D. Hasil Eksekusi Riil
```text
[PASS] Halaman login dapat diakses (HTTP 200)
[PASS] Session ID sebelum login tercatat (tidak dicetak di log)
[PASS] Login berhasil dan diarahkan ke dashboard
[PASS] Session ID setelah login tercatat (tidak dicetak di log)
[PASS] Session ID berubah setelah login (session_regenerate_id)
[PASS] Session ID lama tidak dapat mengakses dashboard
[PASS] Session baru tetap dapat mengakses dashboard
```
**Status: 100% PASS (0 Failure)**

---

## 3. Test 2: Invalidasi Sesi setelah Logout (`tests/session_logout_test.php`)

### A. Tujuan Pengujian
Memverifikasi bahwa proses logout (`auth/logout.php`) menghancurkan sesi di sisi server (`session_destroy()`), menghapus cookie sesi di peramban, dan memastikan cookie lama tidak dapat lagi digunakan untuk mengakses halaman yang diproteksi.

### B. Langkah Pengujian
1. Membuat akun sementara di tabel `users`.
2. Melakukan login berhasil dan mencatat cookie sesi aktif.
3. Memverifikasi pengalihan ke dashboard (HTTP 302).
4. Mengirim request `GET` ke `/auth/logout.php`.
5. Memverifikasi respons server mengembalikan HTTP 302 mengarah kembali ke `/auth/login.php`.
6. Memeriksa cookie jar: memastikan cookie sesi dihapus (*expired* dengan tanggal lampau / bernilai `deleted`).
7. Membuka *client* HTTP baru dan secara sengaja menyertakan cookie sesi lama menuju `/dashboard/index.php`.
8. Memverifikasi bahwa server menolak akses (HTTP 302 ke login).
9. Memverifikasi bahwa tidak ada data pengguna yang tertinggal dalam sesi server.
10. Menghapus akun uji dan audit log di blok `finally`.

### C. Perintah Eksekusi
```bash
php tests/session_logout_test.php
```

### D. Hasil Eksekusi Riil
```text
[PASS] Login berhasil dan diarahkan ke dashboard
[PASS] Cookie session sebelum logout tercatat (tidak dicetak di log)
[PASS] Logout mengarahkan kembali ke halaman login
[PASS] Cookie session dihapus dari browser setelah logout
[PASS] Cookie lama tidak dapat mengakses dashboard (diarahkan ke login)
[PASS] Tidak ada data session pengguna yang tersisa setelah logout
```
**Status: 100% PASS (0 Failure)**

---

## 4. Pengujian Tambahan

### A. Pengujian Batas Waktu Sesi (`tests/session_timeout_test.php`)
Menguji logika fungsi murni `session_is_expired()` terhadap idle timeout 30 menit dan absolute timeout 8 jam:
- Perintah: `php tests/session_timeout_test.php`
- Hasil Eksekusi:
  ```text
  [PASS] Session baru dengan waktu lengkap dianggap valid
  [PASS] Tepat 30 menit tanpa aktivitas belum dianggap expired
  [PASS] Lebih dari 30 menit tanpa aktivitas dianggap expired
  [PASS] Tepat 8 jam sejak login belum dianggap expired
  [PASS] Lebih dari 8 jam sejak login dianggap expired
  [PASS] Aktivitas baru tidak membatalkan absolute timeout yang belum terlampaui
  [PASS] Session tanpa waktu login dianggap expired
  [PASS] Session tanpa waktu aktivitas dianggap expired
  ```
- **Status: 100% PASS**

### B. Pengujian Alur Lengkap Autentikasi (`tests/auth_flow_test.php`)
Menguji aliran lengkap login form, dashboard session render, role access, hingga logout:
- Perintah: `php tests/auth_flow_test.php`
- Hasil Eksekusi:
  ```text
  [PASS] Halaman login dan token CSRF tersedia
  [PASS] Login berhasil dan diarahkan ke dashboard
  [PASS] Dashboard dapat diakses dan menampilkan pengguna login
  [PASS] Logout mengarahkan kembali ke halaman login
  [PASS] Session lama tidak dapat mengakses dashboard setelah logout
  ```
- **Status: 100% PASS**

### C. Pengujian Password Hashing & Migrasi (`tests/password_hashing_test.php`)
Menguji enkripsi password menggunakan `password_hash(..., PASSWORD_DEFAULT)` dan deteksi akun warisan (*legacy plaintext*):
- Perintah: `php tests/password_hashing_test.php`
- Hasil Eksekusi:
  ```text
  [PASS] Password tidak disimpan sebagai plaintext
  [PASS] Password tersimpan sebagai hash yang dikenali PHP
  [PASS] Password asli berhasil diverifikasi dengan password_verify()
  [PASS] Password salah ditolak
  [PASS] Akun lama terdeteksi masih plaintext sebelum migrasi
  [PASS] Login akun lama dengan password benar diterima
  [PASS] Password lama diubah menjadi hash setelah login
  [PASS] Hash hasil migrasi dikenali PHP
  [PASS] Hash hasil migrasi dapat diverifikasi
  [PASS] Password tidak muncul di audit log
  ```
- **Status: 100% PASS**

### D. Pengujian Rate Limiting Login (`tests/rate_limit_test.php`)
Menguji pembatasan brute-force: tepat 5x percobaan salah diperbolehkan, percobaan ke-6 diblokir dengan HTTP 429:
- Perintah: `php tests/rate_limit_test.php`
- Hasil Eksekusi:
  ```text
  [PASS] Halaman login dapat diakses (HTTP 200)
  [PASS] Percobaan gagal ke-1 masih diproses (HTTP 200)
  [PASS] Percobaan gagal ke-1 memakai pesan seragam
  [PASS] Percobaan gagal ke-2 masih diproses (HTTP 200)
  [PASS] Percobaan gagal ke-2 memakai pesan seragam
  [PASS] Percobaan gagal ke-3 masih diproses (HTTP 200)
  [PASS] Percobaan gagal ke-3 memakai pesan seragam
  [PASS] Percobaan gagal ke-4 masih diproses (HTTP 200)
  [PASS] Percobaan gagal ke-4 memakai pesan seragam
  [PASS] Percobaan gagal ke-5 masih diproses (HTTP 200)
  [PASS] Percobaan gagal ke-5 memakai pesan seragam
  [PASS] Tercatat tepat 5 percobaan gagal
  [PASS] Percobaan ke-6 ditolak dengan HTTP 429 Too Many Requests
  [PASS] Percobaan ke-6 memakai pesan batas percobaan
  [PASS] Percobaan ke-6 tidak menambah catatan verifikasi password
  [PASS] Password tidak diverifikasi pada percobaan yang dibatasi
  [PASS] Percobaan yang diblokir tercatat aman di audit log
  ```
- **Status: 100% PASS**

---

## 5. Ringkasan Hasil Pengujian

| Pengujian | Total Asersi | Asersi Lulus | Asersi Gagal | Persentase Keberhasilan |
| :--- | :---: | :---: | :---: | :---: |
| **Test 1: Session ID Regeneration** | 7 | 7 | 0 | **100%** |
| **Test 2: Invalidasi Sesi Logout** | 6 | 6 | 0 | **100%** |
| **Uji Batas Waktu Sesi (Timeout)** | 8 | 8 | 0 | **100%** |
| **Uji Alur Lengkap Autentikasi** | 5 | 5 | 0 | **100%** |
| **Uji Password Hashing & Migrasi** | 10 | 10 | 0 | **100%** |
| **Uji Proteksi Brute Force (Rate Limit)** | 17 | 17 | 0 | **100%** |
| **TOTAL KESELURUHAN** | **53** | **53** | **0** | **100%** |
