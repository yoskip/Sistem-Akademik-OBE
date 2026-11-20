# Ringkasan Perubahan

## File baru

| File | Alasan |
|---|---|
| `includes/error_handler.php` | Exception/error handler terpusat, ID kejadian, pesan umum, shutdown handler, perlindungan terhadap error berulang saat logging gagal, helper pencatatan kegagalan DB. |
| `includes/security_log.php` | Event log keamanan berbasis file dengan allowlist field, mask identitas, sanitasi nilai, penulisan aman (`LOCK_EX`), dan direktori log internal. |
| `includes/ldap_guard.php` | Guard fail-closed. Tidak membuat koneksi LDAP; hanya menerima verifier yang didaftarkan dan menolak bila gagal/menolak. |
| `storage/logs/.htaccess` | Memblokir akses browser langsung ke file log (terverifikasi HTTP 403). |
| `tests/error_generic_test.php` | Test 1: mock error LDAP, pesan umum, ID kejadian identik di log, tanpa kebocoran detail. |
| `tests/login_ldap_fail_test.php` | Test 2: mock LDAP menolak login, event login gagal/log LDAP tercatat, tanpa password dan tanpa session. |
| `docs/error-log/*.md`, `docs/error-log/bukti/*` | Audit, keputusan, konfigurasi, format event, hasil pengujian, ringkasan, dan bukti aktual. |

## File diubah

| File | Alasan |
|---|---|
| `.gitignore` | Memastikan `storage/logs/*` diabaikan kecuali `.htaccess`, sehingga log tidak masuk Git tetapi proteksi tetap ter-commit. |
| `config/database.php` | Mengaktifkan handler terpusat lebih awal; mengganti `die()` yang membocorkan nama DB dengan pesan umum + ID kejadian dan event `db_failure`. |
| `includes/functions.php` | Menambah `deny_access()`, event login/logout/role change, `reject_csrf()`, dan `obe_mask_display_text()`; `require_role()` kini mencatat 403. |
| `auth/login.php` | Memanggil guard LDAP sebelum session dibuat; mencatat login sukses/gagal tanpa password. |
| `auth/logout.php` | Mencatat event logout sebelum session dihancurkan. |
| `users/index.php` | Mencatat perubahan peran (`from_role`/`to_role`) dan menghapus username dari keterangan audit DB. |
| `dashboard/index.php` | Menyamarkan hash identitas/NPM pada keterangan aktivitas yang ditampilkan. |
| `bahan_kajian/index.php`, `cpl/index.php`, `cpmk/index.php`, `mata_kuliah/index.php`, `profil_lulusan/index.php`, `sub_cpmk/index.php`, `penilaian/bobot.php`, `penilaian/mekanisme.php`, `penilaian/teknik.php`, `rps/index.php`, `rps/rumusan_mk.php`, `rps/rumusan_cpl.php`, `pemetaan/bk_mk.php`, `pemetaan/cpl_bk.php`, `pemetaan/cpl_mk.php`, `pemetaan/cpl_pl.php` | Mengganti `die('Akses ditolak')` dengan `deny_access()` dan menambahkan pencatatan `db_failure` pada `catch` yang sebelumnya hanya menampilkan flash. |
| `pemetaan/cpl_bk_mk.php`, `pemetaan/peta_cpl.php` | Mengganti `die('Akses ditolak')` dengan `deny_access()`. |

## Tidak diubah

- Skema database `db_obe`, tabel, query fitur OBE, PL/CPL/CPMK/Sub-CPMK, hak akses, desain, dan halaman.
- Alur login lokal dengan PHP Session dan password hashing yang sudah ada.
- Konfigurasi PHP global XAMPP.
- Tidak ada integrasi LDAP nyata atau mock LDAP di produksi.

## Catatan branch

Branch yang digunakan: `error-log` (dibuat dari `main`). Commit hanya mencakup file di atas; file log aktual, `.env`, kredensial, dan demo sementara tidak dimasukkan.
