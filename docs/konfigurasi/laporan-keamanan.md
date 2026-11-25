# Laporan Pengamanan Secrets dan Konfigurasi Server

## 1. Pendahuluan

Laporan ini mendokumentasikan hasil audit dan penerapan pengamanan secrets serta konfigurasi server pada aplikasi Sistem Informasi Akademik berbasis OBE (PHP Native + MySQL, XAMPP). Pekerjaan dilakukan bertahap: audit tanpa perubahan, pemindahan konfigurasi database ke `.env`, pemindaian riwayat Git dengan Gitleaks, perlindungan file sensitif, pemasangan header keamanan, penyesuaian CORS, pengujian aplikasi, serta persiapan branch dan Pull Request.

Tidak ada perubahan framework, struktur folder, nama database/tabel/kolom, alur login, session, CRUD, atau navigasi PL → CPL → CPMK → Sub-CPMK.

## 2. Hasil Diskusi Kelompok

Tidak ditemukan notulen diskusi kelompok khusus untuk tahap konfigurasi ini di dalam repositori. Keputusan teknis dalam laporan ini mengikuti instruksi tugas dan hasil audit aktual, bukan hasil diskusi yang dibuat-buat. Notulen diskusi kelompok perlu dilengkapi secara manual oleh anggota kelompok sebelum PR disetujui.

Keputusan penting yang diambil:

- LDAP UY belum terintegrasi, sehingga variabel `LDAP_*` tidak dibuat.
- `APP_KEY` dan `JWT_SECRET` tidak digunakan, sehingga tidak dibuat.
- CORS tidak ditambahkan karena frontend dan backend berada pada origin yang sama.
- Konfigurasi DB dipindahkan ke `.env` menggunakan parser PHP Native tanpa Composer.

## 3. Daftar Secrets dan Lokasi Penyimpanan

| Jenis | Status Penggunaan | Lokasi Sebelum | Lokasi Sesudah | Keterangan |
|---|---|---|---|---|
| Konfigurasi koneksi database | Digunakan | `config/database.php` | `.env` (diabaikan Git) | Nilai dibaca oleh `config/env.php` melalui `getenv()` |
| Nama variabel konfigurasi | Digunakan | - | `.env.example` (dapat di-commit) | Hanya nama variabel, tanpa nilai rahasia |
| Kredensial LDAP | Tidak digunakan | Tidak ada | Tidak dibuat | `includes/ldap_guard.php` hanya guard opsional |
| APP_KEY / JWT_SECRET | Tidak digunakan | Tidak ada | Tidak dibuat | Aplikasi memakai PHP Session Native |
| Token API | Tidak digunakan | Tidak ada | Tidak dibuat | Tidak ada API terpisah |

Variabel yang digunakan: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `SESSION_COOKIE_SECURE`, dan `OBE_BASE_URL`.

Untuk menjaga kompatibilitas, `config/database.php` tetap menerima nama lama `DB_NAME`, `DB_USER`, dan `DB_PASS` sebagai fallback apabila lingkungan server sudah menetapkannya.

## 4. Hasil Pemeriksaan Riwayat Git

Alat: Gitleaks 8.30.1 (portable, diunduh dari rilis resmi GitHub).

Perintah: `gitleaks detect --source . --redact`

| Pemindaian | Waktu | Cakupan | Hasil |
|---|---|---|---|
| Sebelum perbaikan | Tahap awal | Working tree + riwayat Git | 0 temuan |
| Sesudah perbaikan | Setelah perubahan kode | Working tree + riwayat Git | 0 temuan |

Nilai apa pun tidak ditampilkan dalam laporan. Tidak ada kredensial kelompok yang perlu diganti dan tidak ada indikasi kredensial kampus yang bocor berdasarkan pemindaian ini. Jika later ada temuan pada kredensial kampus, tindakan harus dihentikan dan dilaporkan kepada dosen atau administrator kampus.

## 5. Hasil Penerapan `.env`, `.env.example`, dan `.gitignore`

- `config/env.php` dibuat sebagai parser `.env` sederhana PHP Native.
- Parser tidak menimpa environment variable yang sudah ditetapkan Apache/PHP.
- `config/database.php` memanggil `obe_load_env()` dan membaca konfigurasi DB dari environment.
- `DB_PORT` kini ikut dipakai dalam DSN PDO.
- `.env` berisi konfigurasi lokal XAMPP dan tidak pernah ditampilkan dalam laporan.
- `.env.example` berisi nama variabel saja dengan nilai kosong.
- `.gitignore` sudah mengabaikan `.env`, `.env.*`, `*.log`, `*.bak`, dan kini juga `*.old` serta `*.backup`.
- `.env.example` tetap tidak diabaikan karena pengecualian `!.env.example`.
- Verifikasi Git: `git check-ignore -q .env` menghasilkan status 0 (diabaikan), sedangkan `.env.example` status 1 (tidak diabaikan).

## 6. Hasil Pengujian Perlindungan File Sensitif

Apache/XAMPP dinyalakan untuk pengujian. Konfigurasi `httpd.conf` memiliki `LoadModule rewrite_module` aktif dan `AllowOverride All` untuk direktori `C:/xampp/htdocs`, sehingga aturan `.htaccess` berlaku.

| Target | Status HTTP | Hasil |
|---|---|---|
| `/obe/.env` | 403 | Ditolak |
| `/obe/.git/config` | 403 | Ditolak |
| `/obe/.htaccess` | 403 | Ditolak |
| `/obe/.gitignore` | 403 | Ditolak |
| `/obe/.env.example` | 403 | Ditolak |
| `/obe/config/database.php` | 403 | Ditolak |
| `/obe/storage/logs/security.log` | 403 | Ditolak |
| `/obe/storage/logs/php_error.log` | 403 | Ditolak |
| `/obe/database/db_obe.sql` | 403 | Ditolak |
| `/obe/docs/autentikasi/laporan-tugas.md` | 403 | Ditolak |
| File uji `probe.old` | 403 | Ditolak, lalu dihapus |
| File uji `probe.bak` | 403 | Ditolak, lalu dihapus |

Folder `.git` tidak dihapus. Perlindungan dilakukan melalui aturan `.htaccess` yang sudah ada dan ikut disertakan dalam perubahan.

## 7. Header Keamanan dan Hasil Pengujiannya

Header ditambahkan secara terpusat di `includes/functions.php` melalui `send_security_headers()`.

| Header | Nilai | Hasil pada `/obe/auth/login.php` |
|---|---|---|
| `X-Content-Type-Options` | `nosniff` | Muncul |
| `X-Frame-Options` | `DENY` | Muncul |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Muncul |

Tidak ditemukan penggunaan `<iframe>` di proyek, sehingga `DENY` tidak mengganggu fitur yang ada. Tidak ada duplikasi atau konflik header dari `.htaccess`.

## 8. Konfigurasi CORS

Tidak ada API terpisah, tidak ada pemanggilan `fetch()`, `XMLHttpRequest`, atau `axios`, dan tidak ada header CORS di aplikasi. Frontend dan backend berada pada origin yang sama (`/obe`), sehingga CORS tambahan tidak diperlukan.

Pemeriksaan respons menunjukkan `Access-Control-Allow-Origin` dan `Access-Control-Allow-Credentials` tidak muncul. Wildcard `*` dan credentials tidak diaktifkan.

## 9. Hasil Pengujian Aplikasi

Suite pengujian yang sudah ada dijalankan seluruhnya:

| Pengujian | Exit | PASS | FAIL |
|---|---:|---:|---:|
| `auth_flow_test.php` | 0 | 5 | 0 |
| `error_generic_test.php` | 0 | 19 | 0 |
| `login_ldap_fail_test.php` | 0 | 10 | 0 |
| `password_hashing_test.php` | 0 | 10 | 0 |
| `rate_limit_test.php` | 0 | 17 | 0 |
| `session_logout_test.php` | 0 | 6 | 0 |
| `session_regeneration_test.php` | 0 | 7 | 0 |
| `session_timeout_test.php` | 0 | 8 | 0 |
| `sql_injection_npm_test.php` | 0 | 11 | 0 |
| `xss_user_name_test.php` | 0 | 18 | 0 |

Pengujian tambahan alur konfigurasi (menggunakan akun uji sementara yang dihapus setelah pengujian):

- Login dan CSRF: lulus.
- Dashboard pasca-login: lulus.
- Tautan navigasi PL, CPL, CPMK, dan Sub-CPMK tersedia: lulus.
- Halaman PL, CPL, CPMK, dan Sub-CPMK merespons HTTP 200: lulus.
- CRUD create, update, delete pada Profil Lulusan: lulus.
- Logout: lulus.

Percobaan pertama CRUD gagal karena nama data uji mengandung digit, sedangkan validator `validate_name()` hanya menerima huruf. Data uji diperbaiki dan pengujian diulang hingga lulus. Tidak ada perubahan validator yang dilakukan.

Pemeriksaan sintaks: `php -l` untuk `config/env.php`, `config/database.php`, dan `includes/functions.php` tanpa error.

## 10. Informasi Branch dan Pull Request

- Branch: `konfigurasi` (dibuat dari `main`).
- Commit yang direncanakan: `security: secure secrets and server configuration`.
- Base branch PR: `main`.
- Compare branch PR: `konfigurasi`.
- Judul PR: `Security: Pengamanan Secrets dan Konfigurasi Server`.
- Merge tidak dilakukan otomatis; minimal satu anggota kelompok wajib review dan menyetujui.

Status Pull Request akan dicatat setelah branch berhasil di-push dan PR dibuat.

## 11. Kesimpulan

Pengamanan secrets dan konfigurasi server berhasil diterapkan tanpa mengubah arsitektur aplikasi. Hanya konfigurasi database yang benar-benar dipindahkan ke `.env`. LDAP, APP_KEY, JWT_SECRET, dan CORS tidak dibuat karena tidak digunakan. Gitleaks menunjukkan 0 temuan sebelum dan sesudah perbaikan. File sensitif ditolak dengan HTTP 403, ketiga header keamanan muncul, dan seluruh pengujian aplikasi lulus.

Hal yang masih memerlukan tindakan manual: mengisi notulen diskusi kelompok, mengambil screenshot bukti, melakukan review PR oleh minimal satu anggota, dan melakukan merge setelah persetujuan.

## Daftar Bukti yang Perlu Diambil

- Screenshot `.gitignore`.
- Screenshot `.env.example` tanpa nilai rahasia.
- Ringkasan hasil Gitleaks dengan nilai disamarkan.
- Screenshot penolakan akses `/.env`.
- Screenshot penolakan akses `/.git/config`.
- Screenshot Response Headers di DevTools → Network.
- Screenshot Pull Request GitHub.
- Screenshot review dan merge jika sudah dilakukan.
