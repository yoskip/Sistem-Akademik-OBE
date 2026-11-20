# Hasil Pengujian – Mode A: Error, LDAP, dan Log

Tanggal: 2026-11-20. Lingkungan: Windows/XAMPP, PHP 8.0.30, Apache + MySQL aktif, database `db_obe` tersedia.

## Cara menjalankan

```powershell
php tests/error_generic_test.php
php tests/login_ldap_fail_test.php
php tests/auth_flow_test.php
php tests/session_logout_test.php
php tests/session_regeneration_test.php
php tests/session_timeout_test.php
php tests/rate_limit_test.php
php tests/password_hashing_test.php
php tests/sql_injection_npm_test.php
php tests/xss_user_name_test.php
```

Jalankan test berurutan, bukan paralel: kedua test baru membaca offset file log sebelum menulis; eksekusi paralel dapat mencampur entri antar-test.

## Test 1 – Error umum (mock LDAP error)

Perintah: `php tests/error_generic_test.php`
Hasil aktual: **PASS (exit 0)**, 19 check lolos.

Bukti kunci:

```text
[PASS] Pengguna hanya melihat pesan error umum
[PASS] Respons menampilkan ID kejadian berformat ERR-YYYYMMDD-XXXXXX
[PASS] Respons tidak membocorkan: LDAP timeout
[PASS] Respons tidak membocorkan: DUMMY_SENSITIVE_VALUE_123
[PASS] Respons tidak membocorkan: DUMMY_TOKEN_VALUE
[PASS] Respons tidak membocorkan: SELECT * FROM
[PASS] ID kejadian yang sama tercatat di security.log
[PASS] ID kejadian yang sama tercatat di php_error.log
[PASS] Query SQL tidak dicatat di log internal
[PASS] Tidak ada session autentikasi yang diberikan saat error
ID kejadian aktual: ERR-20261120-916204
```

Mock melempar `RuntimeException` berisi nilai dummy sensitif (`password=DUMMY_SENSITIVE_VALUE_123`, `token=DUMMY_TOKEN_VALUE`, `SELECT * FROM users WHERE npm=1402022001`). Respons hanya berisi pesan umum dan ID kejadian. ID yang sama muncul di `security.log` dan `php_error.log`.

Bukti render web nyata: `bukti/generic-error-response.txt` dan `bukti/generic-error.png` (Chrome headless, 8888 byte, ID `ERR-20261120-932977`). Entri log dengan ID tersebut ada di `bukti/security-log-excerpt.txt`. Screenshot dibuat dari halaman demo sementara yang sudah dihapus setelah pengambilan bukti; bukan screenshot tiruan.

## Test 2 – Login gagal (mock LDAP menolak)

Perintah: `php tests/login_ldap_fail_test.php`
Hasil aktual: **PASS (exit 0)**, 10 check lolos setelah perbaikan cara eksekusi dan assertion (lihat "Kegagalan saat pengujian").

```text
[PASS] Mock LDAP yang menolak tidak mengizinkan autentikasi
[PASS] Event login gagal tercatat di security.log
[PASS] Event kegagalan LDAP tercatat
[PASS] NPM tercatat dalam bentuk samaran
[PASS] Hash identitas tercatat untuk korelasi
[PASS] IP pengguna tercatat
[PASS] NPM penuh tidak ditulis ke log
[PASS] Password tidak pernah ditulis ke log
[PASS] Tidak ada field password/token di entri log
[PASS] Tidak ada session autentikasi setelah login gagal
```

Entri aktual: `"npm_masked":"1402***002"`, `"result":"failed"`, tanpa password/token/session ID.

## Kegagalan saat pengujian dan penyebabnya

- Percobaan pertama menjalankan Test 1 dan Test 2 **secara paralel**. Test 2 membaca offset `security.log`, tetapi Test 1 menulis entri di antara pembacaan offset dan pembacaan tail. Test 2 gagal pada check `Tidak ada field password/token di entri log` karena tail menyertakan entri Test 1 yang memuat placeholder `password=[REDACTED] token=[REDACTED]`.
- Perbaikan: assertion diubah untuk memeriksa kunci JSON `"password"`/`"token"` (bukan kemunculan kata yang sudah diredaksi), dan seluruh test dijalankan berurutan. Tidak ada perilaku aplikasi yang diubah untuk menyembunyikan kegagalan; password/token mentah memang tidak pernah ditulis.
- Hasil setelah perbaikan: kedua test exit 0.

## Regresi seluruh test yang tersedia

Seluruh 10 file `tests/*_test.php` dijalankan berurutan: **TOTAL_FAILED=0**.

| Test | Hasil |
|---|---|
| `auth_flow_test.php` | PASS (5/5) |
| `error_generic_test.php` | PASS (19/19) |
| `login_ldap_fail_test.php` | PASS (10/10) |
| `password_hashing_test.php` | PASS (10/10) |
| `rate_limit_test.php` | PASS (17/17) |
| `session_logout_test.php` | PASS (6/6) |
| `session_regeneration_test.php` | PASS (7/7) |
| `session_timeout_test.php` | PASS (8/8) |
| `sql_injection_npm_test.php` | PASS (11/11) |
| `xss_user_name_test.php` | PASS (18/18) |

## Pemeriksaan tambahan

- `php -l` pada 44 file PHP: 0 failure.
- Runtime via HTTP: `display_errors=0`, `display_startup_errors=0`, `log_errors=1` → `bukti/debug-config.txt`.
- Akses HTTP ke `/obe/storage/logs/security.log`, `php_error.log`, dan `.htaccess`: semuanya **HTTP 403**.
- `git check-ignore`: `storage/logs/security.log` dan `php_error.log` terabaikan; `.htaccess` tidak terabaikan sehingga tetap dapat di-commit.

## Batas pengujian

- LDAP UY belum terintegrasi. Mock hanya menguji guard `obe_verify_ldap()` pada level fungsi, bukan koneksi LDAPS/bind server nyata.
- Fail-closed pada timeout LDAP nyata, sertifikat LDAPS, dan respons direktori asli **belum dapat diuji** sampai integrasi dan kredensial resmi tersedia. `[KONFIRMASI TIM]`
- Tidak ada pengujian beban/rotasi log yang dilakukan.
