# Konfigurasi Keamanan Error & Logging

## 1. Konfigurasi runtime aplikasi

Diterapkan di `config/database.php` melalui `obe_configure_error_handling()` sebelum koneksi DB dan session dibuat:

| Direktif | Nilai runtime | Tujuan |
|---|---|---|
| `display_errors` | `0` | Error tidak tampil di browser |
| `display_startup_errors` | `0` | Error startup tidak tampil |
| `log_errors` | `1` | Error masuk log internal |
| `error_log` | `C:\xampp\htdocs\obe\storage\logs\php_error.log` | Lokasi log internal aplikasi |

Bukti aktual dari request HTTP: `docs/error-log/bukti/debug-config.txt`.

## 2. Konfigurasi produksi yang disarankan (php.ini server)

Jangan mengubah `php.ini` global XAMPP lokal tanpa alasan. Pada server produksi, admin wajib menetapkan:

```ini
display_errors = Off
display_startup_errors = Off
log_errors = On
error_reporting = E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED
error_log = /var/log/php/obe-error.log   ; sesuaikan path server
html_errors = Off
```

`display_startup_errors` hanya benar-benar efektif jika disetel sebelum PHP startup; `ini_set()` saat runtime tidak menggantikan konfigurasi `php.ini` Apache/FPM. Karena itu evidence runtime di atas harus didampingi konfigurasi produksi ini.

## 3. Penanganan terpusat

- `includes/error_handler.php` mendaftarkan:
  - `set_error_handler('obe_php_error_handler')`
  - `set_exception_handler('obe_exception_handler')`
  - `register_shutdown_function('obe_shutdown_handler')`
- ID kejadian: `ERR-YYYYMMDD-` + 6 heksadesimal uppercase dari `random_bytes(3)`.
- Error tak tertangani/fatal: pengguna menerima pesan umum + ID kejadian, HTTP 500.
- Warning/notice yang relevan: dicatat internal dengan ID kejadian, tidak ditampilkan.
- Penulisan log memakai `FILE_APPEND | LOCK_EX`, `@file_put_contents`, dan flag `busy` agar kegagalan logging tidak memicu error berulang.

## 4. Kerahasiaan log

- `storage/logs/.htaccess`: `Require all denied`, `Options -Indexes`.
- Diverifikasi aktual: request HTTP ke `/obe/storage/logs/security.log`, `/obe/storage/logs/php_error.log`, dan `/.htaccess` semuanya menghasilkan **HTTP 403**.
- `.gitignore` mengabaikan `storage/logs/*` kecuali `.htaccess`, sehingga log tidak masuk Git.
- `obe_sanitize_log_value()` menghapus/mengganti pola password, token, secret, cookie, session, kredensial bind, query SQL, NPM 10 digit, dan hash 64 digit.
- `security_event()` hanya menerima field allowlist; superglobals tidak pernah di-dump.

## 5. Yang tidak diubah

- Tidak ada perubahan konfigurasi PHP global XAMPP.
- Tidak ada perubahan struktur folder inti OBE, skema `db_obe`, atau fitur akademik.
- Tidak ada koneksi LDAP nyata yang ditambahkan.

## 6. Tindak lanjut produksi

- `[KONFIRMASI TIM]` Terapkan directive `php.ini` di atas pada server produksi.
- `[KONFIRMASI TIM]` Atur rotasi/retensi log dan hak akses filesystem sesuai kebijakan kampus.
- `[KONFIRMASI TIM]` Pastikan web server benar-benar mematuhi `.htaccess` atau pindahkan log ke luar document root bila diperlukan.
