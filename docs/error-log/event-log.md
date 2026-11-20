# Event Log Keamanan

## 1. Lokasi dan format

File: `storage/logs/security.log` (JSON per baris, satu event per baris).
Akses web diblokir `.htaccess`; file masuk `.gitignore`.

```json
{
  "time": "2026-11-20T10:48:52+07:00",
  "ip": "203.0.113.10",
  "action": "login",
  "result": "failed",
  "npm_masked": "1402***002",
  "identity_hash": "ad3db6a...",
  "detail": "Verifier eksternal menolak autentikasi"
}
```

Field minimal yang selalu ada: `time`, `ip`, `action`, `result`.
Field opsional: `user_id`, `npm_masked`, `identity_hash`, `role`, `from_role`, `to_role`, `incident_id`, `detail`.

## 2. Daftar event

| Aksi | Result | Sumber | Catatan |
|---|---|---|---|
| `login` | `success` | `auth/login.php` via `obe_login_success_event()` | Dipanggil setelah session dibuat |
| `login` | `failed` | `auth/login.php` via `obe_login_failed_event()` | Kredensial salah / akun nonaktif / verifier menolak |
| `logout` | `success` | `auth/logout.php` via `obe_logout_event()` | Dicatat sebelum session dihancurkan |
| `access_denied` | `denied` | `deny_access()` (`require_role()` + 18 endpoint) | HTTP 403 |
| `csrf_rejected` | `denied` | `reject_csrf()` | HTTP 419 |
| `role_change` | `success` | `users/index.php` via `obe_role_change_event()` | Mencatat `from_role` dan `to_role` |
| `ldap_failure` | `failed` | `obe_verify_ldap()` | Verifier mengembalikan bukan `true` |
| `ldap_failure` | `error` | `obe_log_ldap_failure()` | Verifier melempar exception/timeout |
| `ldap` | `success` | `obe_verify_ldap()` | Hanya jika verifier nyata didaftarkan |
| `db_failure` | `error` | `obe_log_database_failure()` | Koneksi/query DB gagal, termasuk catch yang sebelumnya ditelan |
| `application_error` | `error` | `obe_handle_exception()` | Exception tak tertangani; membawa `incident_id` |
| `php_error` | `error` | `obe_php_error_handler()` | Warning/notice yang relevan |

`result` yang dipakai: `success`, `failed`, `denied`, `error`.

## 3. Data yang sengaja tidak dicatat

- Password plaintext maupun hash password.
- Token CSRF/autentikasi, cookie, session ID.
- Kredensial database (`DB_USER`, `DB_PASS`, DSN lengkap).
- Respons LDAP lengkap, bind DN/password, filter internal.
- Query SQL atau detail `SQLSTATE`.
- NPM penuh (disimpan sebagai `npm_masked` + `identity_hash`).
- Isi superglobal `$_POST`, `$_GET`, `$_COOKIE`, `$_SESSION`.

Detail exception disimpan terpisah di `php_error.log` setelah melalui `obe_sanitize_log_value()`.

## 4. Contoh potongan log aktual (NPM sudah disamarkan aplikasi)

```json
{"time":"2026-11-20T10:47:18+07:00","ip":"203.0.113.10","action":"login","result":"failed","npm_masked":"1402***002","identity_hash":"ad3db6a67962065404f33d3ff18d02d4a92db032060d68866d139ed546874743","detail":"Verifier eksternal menolak autentikasi"}
{"time":"2026-11-20T10:49:31+07:00","ip":"127.0.0.1","action":"application_error","result":"error","incident_id":"ERR-20261120-932977","detail":"RuntimeException: Mock LDAP timeout: koneksi ke server direktori gagal"}
```

Bukti lengkap: `bukti/security-log-excerpt.txt`.

## 5. Catatan

Tabel `audit_logs` di `db_obe` tetap dipertahankan untuk fitur dashboard dan kompatibilitas dengan tugas sebelumnya. File log keamanan yang baru tidak bergantung pada database, sehingga kegagalan DB tetap dapat dicatat.
