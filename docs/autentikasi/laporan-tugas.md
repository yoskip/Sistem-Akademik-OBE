# LAPORAN TUGAS AKADEMIK
# Keamanan Session Setelah Login LDAP UY – Sistem Akademik Berbasis OBE
### Penugasan: Mode A – Proyek LDAP UY: Sesi Setelah Login LDAP

---

## 1. Identitas Proyek dan Mode Pengerjaan

- **Nama Proyek:** Sistem Informasi Kurikulum Berbasis Outcome-Based Education (OBE).
- **Institusi / Program Studi:** Universitas YARSI – Fakultas Teknologi Informasi, Program Studi Teknik Informatika.
- **Topik Tugas:** **Mode A – Proyek LDAP UY: Sesi Setelah Login LDAP**.
- **Teknologi Utama yang Digunakan:**
  - Bahasa Pemrograman: PHP Native (PHP 8.0+).
  - Basis Data: MySQL / MariaDB (nama database: `db_obe`).
  - Web Server Lokal: Apache HTTP Server via XAMPP for Windows.
  - Antarmuka Pengguna: HTML5, CSS3, JavaScript Native, Bootstrap 5.3.3, Bootstrap Icons 1.11.3.
  - Arsitektur Autentikasi: Server-side PHP Session terproteksi (tanpa framework pihak ketiga, tanpa JWT).
- **Mode Pengerjaan:**
  - Pendekatan rekayasa perangkat lunak dan audit keamanan berbasis *in-place hardening*.
  - Perbaikan dilakukan seminimal mungkin (*least change principle*) pada arsitektur yang sudah ada tanpa menulis ulang kode sistem kurikulum.
  - Mempertahankan integritas seluruh tabel, relasi foreign key, dan modul kurikulum OBE (PL, CPL, CPMK, Sub-CPMK, Bahan Kajian, Mata Kuliah, Pemetaan Matriks, Penilaian, RPS, Monitoring).

---

## 2. Kondisi Awal Proyek dan Status Integrasi LDAP UY

Sebelum perbaikan dilakukan, audit mendalam terhadap seluruh berkas dan sistem menghasilkan temuan berikut:

1. **Status Integrasi LDAP Universitas YARSI (LDAP UY):**
   - **Fakta Aktual:** **LDAP UY belum terintegrasi di dalam proyek ini.**
   - Tidak ada berkas konfigurasi, fungsi pustaka LDAP (`ldap_connect`, `ldap_bind`), maupun komunikasi protokol LDAPS ke server Universitas YARSI. Ekstensi PHP `ldap` pada instalasi XAMPP lokal saat ini juga belum diaktifkan.
   - Sistem login yang ada berjalan menggunakan verifikasi akun basis data internal MySQL (`users` dan `roles`) dengan algoritma bcrypt (`password_verify()`).
   - Sesuai instruksi tugas, pengembang **tidak mengarang bahwa LDAP sudah terintegrasi** dan tidak membuat sistem LDAP tiruan. Pengembang berfokus menyelesaikan inti tugas Mode A, yaitu **pengamanan sesi pasca-login**, yang dirancang siap menerima data hasil otentikasi LDAP UY di masa mendatang.
2. **Sentralisasi `session_start()`:**
   - Pemanggilan `session_start()` sudah terpusat di dalam file `includes/functions.php`, namun saat dieksekusi melalui antarmuka baris perintah (CLI) untuk pengujian otomatis, direktori penyimpanan sesi bawaan XAMPP (`C:\xampp\tmp`) dapat memicu peringatan *permission denied* jika environment sistem operasi membatasi hak akses direktori tersebut.
3. **Penyimpanan Data Sesi:**
   - Data sesi yang disimpan sebelumnya belum mencantumkan atribut `npm_nidn` dan perlu dipastikan secara ketat tidak ada kredensial atau password hash yang tersimpan di memori sesi.
4. **Evaluasi Cookie pada Skrip Pengujian:**
   - Pemeriksaan penghapusan cookie sesi pada skrip pengujian cURL belum membedakan antara nilai cookie string biasa dengan instruksi penghapusan cookie standar PHP (`PHPSESSID=deleted` dengan expired timestamp lampau).
5. **Proteksi Cache Peramban:**
   - Halaman terproteksi memerlukan header anti-cache yang konsisten agar pengguna yang telah logout tidak dapat melihat data kurikulum kembali saat menekan tombol *Back* pada peramban.

---

## 3. Notulen Keputusan Kelompok

Berdasarkan diskusi tim pengembang, ditetapkan keputusan arsitektur berikut:

1. **Kepatuhan Terhadap Ketentuan Mode A:**
   Mempertahankan PHP Session Native sebagai mekanisme otentikasi utama. Menolak konversi ke JWT, Laravel, Node.js, Firebase, atau Supabase.
2. **Data Sesi yang Diperbolehkan:**
   Menyimpan secara eksklusif: `id`, `username`, `npm_nidn`, `nama`, `role`, `login_at`, dan `last_activity`.
   **DILARANG KERAS** menyimpan: password LDAP, hash password, kredensial LDAP, password database, atau informasi sensitif lainnya.
3. **Regenerasi Session ID:**
   Memanggil `session_regenerate_id(true)` segera setelah verifikasi kredensial berhasil untuk memitigasi serangan *Session Fixation*.
4. **Konfigurasi Cookie Session:**
   `HttpOnly = true`, `SameSite = 'Lax'`, `Path = '/'`, `lifetime = 0` (session cookie). Parameter `Secure` dikonfigurasi fleksibel: `false` pada HTTP lokal XAMPP, dan `true` pada produksi HTTPS.
5. **Dual Timeout:**
   Menerapkan *Idle Timeout* 30 menit (1.800 detik) dan *Absolute Timeout* 8 jam (28.800 detik) yang dievaluasi langsung oleh kode PHP di sisi server pada setiap request terproteksi.
6. **Logout Bersih:**
   Menghapus `$_SESSION = []`, menghancurkan sesi di server via `session_destroy()`, mengirimkan instruksi kedaluwarsa cookie ke peramban, dan mengirim header anti-cache `Cache-Control: no-store, no-cache, must-revalidate, max-age=0`.
7. **Perlindungan Modul OBE:**
   Memastikan seluruh modul kurikulum (PL, CPL, CPMK, Sub-CPMK, Bahan Kajian, Mata Kuliah, Pemetaan, Penilaian, RPS, Monitoring) tetap utuh dan terlindungi di balik `require_login()`.

---

## 4. Implementasi Keamanan Session

Keamanan sesi diimplementasikan melalui fungsi terpusat `start_secure_session()` pada file `includes/functions.php`.

### A. Direktif Keamanan PHP Runtime
```php
ini_set('session.use_strict_mode', '1');  // Mencegah session fixation dengan menolak ID yang tidak dibuat server
ini_set('session.use_only_cookies', '1'); // Memastikan ID sesi hanya dikirim via HTTP Cookie, bukan URL
ini_set('session.use_trans_sid', '0');    // Menonaktifkan penempelan session ID pada link HTML
ini_set('session.cookie_httponly', '1');  // Mencegah pembacaan cookie oleh JavaScript
ini_set('session.cookie_samesite', 'Lax'); // Mitigasi serangan CSRF
ini_set('session.cookie_secure', $secure ? '1' : '0');
ini_set('session.cookie_lifetime', '0');   // Cookie kedaluwarsa saat browser ditutup
```

### B. Regenerasi Session ID saat Login (`auth/login.php`)
Setelah verifikasi username dan password berhasil via `verify_and_upgrade_password()`:
```php
session_regenerate_id(true);

$_SESSION['user'] = [
    'id'       => (int)$user['id'],
    'nama'     => $user['nama'],
    'username' => $user['username'],
    'npm_nidn' => $user['npm'] ?? $user['nidn'] ?? $user['username'],
    'role'     => $user['role'],
];
$_SESSION['login_at'] = time();
$_SESSION['last_activity'] = time();
unset($_SESSION['csrf']);
```
*Catatan Keamanan:* Password asli, hash password, maupun kredensial database tidak pernah disimpan dalam array `$_SESSION`.

---

## 5. Pengaturan Cookie

Parameter cookie sesi dikonfigurasi menggunakan `session_set_cookie_params()`:
- **Lifetime:** `0` (menjadikan sesi berstatus sementara / browser session).
- **Path:** `'/'` (berlaku untuk semua modul aplikasi kurikulum).
- **Domain:** `''` (terbatas pada host saat ini).
- **Secure:** Mengikuti fungsi `session_cookie_secure()` (false pada HTTP XAMPP, true pada HTTPS).
- **HttpOnly:** `true` (memitigasi XSS session hijacking).
- **SameSite:** `'Lax'` (mencegah pengiriman cookie pada request lintas situs ilegal).

---

## 6. Idle Timeout dan Absolute Timeout

Aplikasi menerapkan dua batas waktu independen yang diverifikasi pada fungsi `enforce_session_timeout()`:

1. **Idle Timeout (30 Menit / 1.800 Detik):**
   Dihitung dari selisih waktu saat ini (`$now`) dengan waktu aktivitas terakhir (`$_SESSION['last_activity']`). Jika selisih melebihi 1.800 detik tanpa ada request, sesi diputus.
2. **Absolute Timeout (8 Jam / 28.800 Detik):**
   Dihitung dari selisih waktu saat ini (`$now`) dengan waktu awal login (`$_SESSION['login_at']`). Meskipun pengguna aktif terus-menerus, sesi akan diakhiri tepat setelah 8 jam.

Jika salah satu batas waktu terlampaui, fungsi `expire_session()` dijalankan:
```php
function expire_session(): void
{
    destroy_session_and_cookie();
    redirect(BASE_URL . '/auth/login.php?expired=1');
}
```
Pengguna diarahkan ke login dengan notifikasi: *"Sesi Anda telah berakhir. Silakan login kembali."*.

---

## 7. Implementasi Logout

Proses logout pada `auth/logout.php` menjamin pembersihan sesi secara komprehensif:
1. Pencatatan jejak audit aktivitas logout ke tabel `audit_logs`.
2. Pengiriman header anti-cache:
   ```php
   header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
   header('Pragma: no-cache');
   header('Expires: 0');
   ```
3. Pemanggilan `destroy_session_and_cookie()`:
   - `$_SESSION = []`: Mengosongkan data di memori aplikasi.
   - `setcookie(session_name(), '', ['expires' => time() - 42000, ...])`: Mengirim instruksi penghapusan cookie ke browser.
   - `session_destroy()`: Menghapus berkas sesi dari server fisik.
4. Pengalihan browser ke halaman login (`/auth/login.php`).

Dengan kombinasi `session_destroy()` dan header anti-cache, tombol *Back* pada peramban tidak akan menampilkan data halaman terproteksi, melainkan langsung memaksa browser memuat ulang dan dialihkan ke formulir login.

---

## 8. Hasil Pengujian Riil

Pengujian dijalankan langsung pada web server Apache dan MySQL XAMPP lokal dengan hasil nyata sebagai berikut:

### Test 1: Session ID Regeneration (`tests/session_regeneration_test.php`)
```text
[PASS] Halaman login dapat diakses (HTTP 200)
[PASS] Session ID sebelum login tercatat (tidak dicetak di log)
[PASS] Login berhasil dan diarahkan ke dashboard
[PASS] Session ID setelah login tercatat (tidak dicetak di log)
[PASS] Session ID berubah setelah login (session_regenerate_id)
[PASS] Session ID lama tidak dapat mengakses dashboard
[PASS] Session baru tetap dapat mengakses dashboard
```
*Hasil:* **LULUS (7 dari 7 asersi PASS)**. Terbukti ID sesi berganti dan ID lama tidak dapat digunakan kembali.

### Test 2: Invalidasi Sesi setelah Logout (`tests/session_logout_test.php`)
```text
[PASS] Login berhasil dan diarahkan ke dashboard
[PASS] Cookie session sebelum logout tercatat (tidak dicetak di log)
[PASS] Logout mengarahkan kembali ke halaman login
[PASS] Cookie session dihapus dari browser setelah logout
[PASS] Cookie lama tidak dapat mengakses dashboard (diarahkan ke login)
[PASS] Tidak ada data session pengguna yang tersisa setelah logout
```
*Hasil:* **LULUS (6 dari 6 asersi PASS)**. Terbukti cookie lama tidak dapat mengakses halaman terproteksi dan sesi telah musnah.

### Uji Batas Waktu Sesi (`tests/session_timeout_test.php`)
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
*Hasil:* **LULUS (8 dari 8 asersi PASS)**.

### Uji Integritas Seluruh Modul Kurikulum OBE
Pengujian HTTP terhadap seluruh modul akademik setelah penerapan keamanan sesi:
- Dashboard (`/dashboard/index.php`): **HTTP 200 (PASS)**
- Profil Lulusan / PL (`/profil_lulusan/index.php`): **HTTP 200 (PASS)**
- CPL Program Studi (`/cpl/index.php`): **HTTP 200 (PASS)**
- CPMK (`/cpmk/index.php`): **HTTP 200 (PASS)**
- Sub-CPMK (`/sub_cpmk/index.php`): **HTTP 200 (PASS)**
- Bahan Kajian (`/bahan_kajian/index.php`): **HTTP 200 (PASS)**
- Mata Kuliah (`/mata_kuliah/index.php`): **HTTP 200 (PASS)**
- Pemetaan CPL - PL (`/pemetaan/cpl_pl.php`): **HTTP 200 (PASS)**
- Peta Pemenuhan CPL (`/pemetaan/peta_cpl.php`): **HTTP 200 (PASS)**
- Penilaian Bobot (`/penilaian/bobot.php`): **HTTP 200 (PASS)**
- Dokumen RPS Dosen (`/rps/index.php`): **HTTP 200 (PASS)**
- Monitoring Kelengkapan MK (`/monitoring/kelengkapan.php`): **HTTP 200 (PASS)**
- Manajemen Pengguna (`/users/index.php`): **HTTP 200 (PASS)**

---

## 9. Ringkasan Perubahan Kode

1. `includes/functions.php`:
   - Menambahkan fallback `session_save_path(sys_get_temp_dir())` khusus pada eksekusi mode CLI (`PHP_SAPI === 'cli'`) saat direktori sesi bawaan tidak writable.
   - Memastikan `start_secure_session()` dipanggil sebelum manipulasi sesi lainnya.
2. `tests/session_logout_test.php`:
   - Memperbaiki helper pembacaan cookie cURL agar mengenali cookie yang dihapus (`deleted` dan expired timestamp) sehingga mencerminkan perilaku browser yang sesungguhnya.
3. `tests/session_regeneration_test.php`:
   - Menyelaraskan pembacaan cookie cURL untuk menjaga konsistensi suite pengujian.
4. `docs/autentikasi/*`:
   - Menghasilkan dokumentasi formal: audit proyek, konfigurasi sesi, pengujian sesi, ringkasan perubahan, notulen keputusan, dan panduan bukti.

---

## 10. Bukti yang Harus Dilampirkan dalam Laporan Fisik/PDF

Mahasiswa disarankan melampirkan tangkapan layar manual berikut sesuai panduan di `docs/autentikasi/daftar-bukti.md`:
1. **Screenshot DevTools Application - Cookies:** Menampilkan nilai `PHPSESSID`, `HttpOnly = true`, dan `SameSite = Lax` sebelum vs sesudah login.
2. **Screenshot DevTools Network - Response Headers:** Menampilkan header `Cache-Control: no-store, no-cache, must-revalidate` dan `Set-Cookie`.
3. **Screenshot Terminal Eksekusi Test 1:** Output dari `php tests/session_regeneration_test.php` (seluruh asersi PASS).
4. **Screenshot Terminal Eksekusi Test 2:** Output dari `php tests/session_logout_test.php` (seluruh asersi PASS).
5. **Screenshot Terminal Uji Timeout:** Output dari `php tests/session_timeout_test.php` (seluruh asersi PASS).
6. **Screenshot Tombol Back Peramban:** Memperlihatkan penolakan akses setelah logout saat tombol Back ditekan.
7. **Screenshot Notifikasi Timeout:** Menampilkan alert `"Sesi Anda telah berakhir. Silakan login kembali."`.
8. **Screenshot Halaman Modul OBE:** Tampilan antarmuka modul CPL, CPMK, dan Sub-CPMK dengan akun Dosen/Kaprodi/Admin.

---

## 11. Kesimpulan

Penerapan keamanan autentikasi dan session management pada Sistem Informasi Kurikulum Berbasis OBE telah berhasil dilaksanakan dengan hasil sebagai berikut:
1. **Mitigasi Kerentanan Sesi:** Serangan *Session Fixation* telah dicegah melalui `session_regenerate_id(true)`, serangan pencurian sesi via XSS dicegah dengan `HttpOnly = true`, dan manipulasi request lintas situs dibatasi via `SameSite = Lax`.
2. **Kontrol Akses dan Timeout Deterministik:** Diterapkannya kombinasi *Idle Timeout* 30 menit dan *Absolute Timeout* 8 jam menjamin sesi tidak dapat dibiarkan tergantung selamanya.
3. **Integritas Aplikasi OBE Terjaga 100%:** Seluruh modul kurikulum (PL, CPL, CPMK, Sub-CPMK, Bahan Kajian, Mata Kuliah, Pemetaan, Penilaian, RPS, Monitoring) tetap berfungsi sempurna tanpa ada kerusakan tampilan, navigasi, maupun struktur basis data.
4. **Kepatuhan Terhadap Ketentuan:** Seluruh solusi mengandalkan kemampuan murni PHP Native dan Apache XAMPP yang mudah dipahami, dipelajari, dan dipertanggungjawabkan untuk tugas kuliah.
