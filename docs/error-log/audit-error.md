# Audit Error, LDAP, dan Log – Mode A

Dibuat sebelum perubahan kode. Bukti berasal dari pemeriksaan langsung berkas dan perintah yang dijalankan di workspace `C:\xampp\htdocs\obe` pada 2026-11-20.

## Metode dan batas audit

- `git status --short --branch`: `## main...origin/main`, working tree bersih sebelum tugas dimulai.
- `php -v`: PHP 8.0.30 (CLI XAMPP).
- `php -i`: `display_errors => STDOUT`, `display_startup_errors => On`, `log_errors => On`, `error_log => C:\xampp\php\logs\php_error_log` untuk SAPI CLI. Konfigurasi Apache tidak diubah/diperiksa lewat `phpinfo()` web; produksi wajib memakai rekomendasi di `konfigurasi-keamanan.md`.
- `php -m` tidak memuat `ldap`; pencarian `ldap_connect|ldap_bind|ldap_search` tidak menemukan implementasi kode. Dokumen lama `docs/autentikasi/audit-proyek.md:112-130` dan `docs/keamanan-input/notulen-keputusan.md:64-69` juga menyatakan LDAP UY belum terintegrasi.

## Temuan

| # | Lokasi | Bukti | Status / Dampak |
|---|--------|-------|-----------------|
| 1 | `config/database.php:39-46` | `catch (PDOException $e)` lalu `die('<div ...>Koneksi database gagal ... database <b>db_obe</b> ...</div>')` | Error ditampilkan ke browser, menyebut nama database, tanpa ID kejadian, tanpa pencatatan internal. |
| 2 | Seluruh aplikasi | Tidak ditemukan `set_error_handler`, `set_exception_handler`, `register_shutdown_function`, `ErrorException`, atau `error_reporting` pada kode aplikasi. | Belum ada penanganan error terpusat; error PHP bergantung pada konfigurasi `php.ini`. |
| 3 | Lingkungan CLI XAMPP | `display_errors=STDOUT`, `display_startup_errors=On` | Jika dipakai untuk menjalankan skrip/endpoint, detail error dapat tampil. Perlu diatur ulang saat runtime aplikasi dan didokumentasikan untuk produksi. |
| 4 | `auth/login.php:14`, `includes/functions.php:343-350` | `verify_csrf()` memakai `die('Token keamanan tidak valid...')` | Tidak ada event keamanan untuk penolakan CSRF. |
| 5 | 16 endpoint (mis. `cpmk/index.php:8`, `users/index.php:3`, `rps/index.php:8`, `pemetaan/*.php`) | `http_response_code(403); die('Akses ditolak')` / `require_role()` | Akses ditolak belum dicatat sebagai event keamanan; format respons tidak seragam. |
| 6 | `includes/functions.php:444-469` | `log_login_failure`, `log_login_blocked`, `log_activity` menulis ke tabel `audit_logs` via DB | Sudah ada audit DB, tetapi belum ada file log keamanan terpusat dengan waktu/ID/IP/aksi/hasil terstruktur; pencatatan gagal bila DB mati. |
| 7 | `dashboard/index.php:36-39,128-131` | `audit_logs.keterangan` ditampilkan apa adanya (di-`e()` namun tidak disamarkan) | Hash identitas login 64 hex dan username (dari `users/index.php:47,52`) dapat terlihat oleh pengguna terautentikasi. Perlu disamarkan. |
| 8 | `users/index.php:37-58` | Update `role_id` berhasil dicatat hanya sebagai `log_activity('update', 'users', "Pengguna $username diperbarui")` | Belum ada event khusus perubahan peran/hak akses. |
| 9 | `auth/login.php:13-66` | Alur login memakai DB + `password_verify`, session, rate limit | Tidak ada pemeriksaan/guard LDAP sama sekali. LDAP **belum terintegrasi**, jadi aturan fail-closed LDAP belum dapat diuji pada integrasi nyata. |
| 10 | `cpmk/index.php:44,59`, `bahan_kajian/index.php:55`, `cpl/index.php:39`, `mata_kuliah/index.php:70`, `users/index.php:56`, dll. | `catch (PDOException $e)` diikuti `flash('danger', ...)` tanpa logging | Kegagalan DB ditelan; administrator tidak mendapat jejak internal. |
| 11 | `.gitignore:1-13` | Sudah mengabaikan `.env`, `config/secrets.php`, `*.log`, `logs/`, `tests/*.log` | Baik. Folder log baru harus tetap berada di bawah pola ignore dan ditutup akses webnya. |
| 12 | Eksploitasi error mentah | Tidak ditemukan `var_dump`, `print_r`, `getMessage()` yang dirender ke halaman web (hanya di test CLI), tidak ada stack trace yang sengaja ditampilkan. | Tidak ada bukti endpoint yang saat ini merender exception; risiko utama ada pada konfigurasi default PHP dan `die()` pada kegagalan DB. |

## Kesimpulan audit

LDAP tidak terintegrasi, sehingga tidak boleh diklaim sudah terhubung dan tidak boleh dibuat integrasi tiruan produksi. Yang akan dikerjakan: (1) pengamanan konfigurasi error, (2) handler terpusat + ID kejadian, (3) fail-closed via guard yang dapat diuji dengan mock pada level fungsi, (4) event log keamanan berbasis file yang terlindungi, (5) perbaikan titik-titik `die()` dan `catch` yang menelan error.
