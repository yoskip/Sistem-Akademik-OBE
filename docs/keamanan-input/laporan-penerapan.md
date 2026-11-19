# Laporan Penerapan Keamanan Input dan Data LDAP (Mode A)
**Sistem Informasi Akademik Berbasis Outcome-Based Education (OBE)**  
**Universitas YARSI**

---

## 1. Ringkasan Eksekutif

Penerapan keamanan input pada proyek Sistem Informasi Kurikulum OBE Universitas YARSI difokuskan pada penguatan validasi di sisi server (*server-side input validation*), perlindungan query database dengan *prepared statements*, pengamanan query dinamis dengan *allowlist column/table*, serta pencegahan serangan Cross-Site Scripting (XSS) melalui *output encoding* yang ketat.

Seluruh modifikasi dilakukan pada arsitektur **PHP Native** yang sudah ada tanpa mengubah skema tabel database, tanpa migrasi framework, dan tanpa merusak antarmuka pengguna (UI/Bootstrap).

---

## 2. Fungsi Validasi Terpusat (`includes/functions.php`)

Untuk memastikan konsistensi dan efisiensi, fungsi-fungsi validasi yang dapat digunakan kembali (*reusable functions*) telah ditambahkan ke [includes/functions.php](file:///c:/xampp/htdocs/obe/includes/functions.php):

| Nama Fungsi | Parameter | Pola / Ketentuan | Penjelasan |
|---|---|---|---|
| `validate_npm($npm)` | `mixed $npm` | `/^[0-9]{10}$/` | Memastikan NPM terdiri dari tepat 10 digit angka numerik. Menolak huruf, spasi, atau simbol. |
| `validate_name($name, $min=2, $max=100)` | `mixed $name, int $min, int $max` | `/^[\p{L}\s\'\-\.,]+$/u` | Memvalidasi nama pengguna, dosen, atau mahasiswa. Mengizinkan alfabet, tanda hubung, apostrof, titik, dan koma gelar. Menolak tag HTML/skrip. |
| `validate_id($id)` | `mixed $id` | `ctype_digit && (int)$id >= 1` | Memvalidasi parameter ID (primary key / foreign key) sebagai integer positif minimal 1. Menolak karakter non-angka atau float. |
| `validate_code($code, $min=2, $max=30)` | `mixed $code, int $min, int $max` | `/^[A-Za-z0-9_\-]{2,30}$/` | Memvalidasi kode akademik: PL, CPL, CPMK, Sub-CPMK, BK, MK. Hanya alfanumerik, dash, dan underscore. |
| `validate_description($desc, $min=1, $max=1000)` | `mixed $desc, int $min, int $max` | `mb_strlen 1–1000`, no control chars | Memvalidasi teks narasi capaian pembelajaran atau catatan RPS. Mencegah null byte atau karakter kontrol berbahaya. |
| `validate_username($username)` | `mixed $username` | 10 digit NPM atau `/^[a-zA-Z0-9_\.]{3,50}$/` | Mendukung username login baik berupa NPM mahasiswa maupun username dosen/admin. |
| `validate_login_identity($identity)` | `mixed $identity` | NPM / Username / Email valid | Memvalidasi identitas login sebelum query database dijalankan atau dikirim ke LDAP. |
| `e($value)` | `mixed $value` | `htmlspecialchars(..., ENT_QUOTES \| ENT_SUBSTITUTE, 'UTF-8')` | Output encoding ketat untuk mencegah reflected dan stored XSS pada seluruh view HTML. |

---

## 3. Daftar File yang Diperbaiki

Penerapan keamanan input dan query diterapkan secara menyeluruh pada file-file berikut:

### 3.1. Modul Autentikasi & Pengguna
1. [auth/login.php](file:///c:/xampp/htdocs/obe/auth/login.php):
   - Menambahkan validasi allowlist pada parameter input `identity` melalui `validate_login_identity()`.
   - Menolak input berbahaya dengan kode respons **HTTP 422** sebelum query database atau rate-limiting dieksekusi.
   - Menggunakan query berparameter: `SELECT ... WHERE (u.username = ? OR u.email = ?) LIMIT 1`.

2. [users/index.php](file:///c:/xampp/htdocs/obe/users/index.php):
   - Memvalidasi seluruh input form pengguna: nama (`validate_name`), username/NPM (`validate_username`), role_id (`in_array([1,2,3])`), email (`filter_var`), dan ID (`validate_id`).
   - Validasi ID pada aksi penghapusan pengguna (`action=delete`).
   - Mengembalikan **HTTP 422** jika input tidak valid.

### 3.2. Modul Kurikulum Inti OBE (PL, CPL, CPMK, Sub-CPMK)
3. [profil_lulusan/index.php](file:///c:/xampp/htdocs/obe/profil_lulusan/index.php):
   - Validasi kode PL (`validate_code`), nama PL (`validate_name`), deskripsi (`validate_description`), dan ID (`validate_id`).
   - Validasi ID URL `?edit=` dan `?detail=`.

4. [cpl/index.php](file:///c:/xampp/htdocs/obe/cpl/index.php):
   - Validasi kode CPL (`validate_code`), deskripsi CPL (`validate_description`), dan ID (`validate_id`).
   - Validasi parameter URL dan tombol hapus.

5. [cpmk/index.php](file:///c:/xampp/htdocs/obe/cpmk/index.php):
   - Validasi foreign key `cpl_id` (`validate_id`), kode CPMK (`validate_code`), deskripsi (`validate_description`), dan ID.

6. [sub_cpmk/index.php](file:///c:/xampp/htdocs/obe/sub_cpmk/index.php):
   - Validasi foreign key `cpmk_id` (`validate_id`), kode Sub-CPMK (`validate_code`), deskripsi (`validate_description`), dan ID.

### 3.3. Modul Struktur Akademik & Asesmen
7. [bahan_kajian/index.php](file:///c:/xampp/htdocs/obe/bahan_kajian/index.php):
   - Validasi kode BK, nama BK, deskripsi, acuan kurikulum, dan ID.
8. [mata_kuliah/index.php](file:///c:/xampp/htdocs/obe/mata_kuliah/index.php):
   - Validasi kode MK, nama MK, batas SKS (0–20), semester (1–10 alfanumerik), kode internal, dan ID.
9. [rps/index.php](file:///c:/xampp/htdocs/obe/rps/index.php):
   - Validasi `mk_id` (`validate_id`), nama dosen (`validate_name`), catatan RPS, urutan, dan ID.
10. [rps/rumusan_cpl.php](file:///c:/xampp/htdocs/obe/rps/rumusan_cpl.php) & [rps/rumusan_mk.php](file:///c:/xampp/htdocs/obe/rps/rumusan_mk.php):
    - Validasi `mk_id`, `cpmk_id`, skor maksimal (0–1000), total, dan ID.
11. [pemetaan/cpl_bk_mk.php](file:///c:/xampp/htdocs/obe/pemetaan/cpl_bk_mk.php):
    - Validasi relasi triplet `cpl_id`, `bahan_kajian_id`, `mk_id`, dan ID penghapusan relasi.
12. [penilaian/bobot.php](file:///c:/xampp/htdocs/obe/penilaian/bobot.php) & [penilaian/teknik.php](file:///c:/xampp/htdocs/obe/penilaian/teknik.php):
    - Menambahkan perlindungan allowlist ketat pada nama kolom dinamis (`$allowedBobotCols`, `$allowedTeknikCols`) untuk mencegah manipulasi query UPDATE.
    - Validasi `mk_id` dan `id` baris penilaian.
13. [dashboard/index.php](file:///c:/xampp/htdocs/obe/dashboard/index.php):
    - Membatasi fungsi counter dinamis hanya pada tabel yang terdaftar di `$allowedTables`.

---

## 4. Alur Kerja Keamanan Permintaan (Request Lifecycle)

```
[ Klien / Browser / LDAP ]
          │
          ▼
1. Validasi Token CSRF (verify_csrf())
          │
          ▼
2. Validasi Sesi & Timeout (require_login() & enforce_session_timeout())
          │
          ▼
3. Validasi Otorisasi Peran (require_role() / can_manage_*)
          │
          ▼
4. Validasi Allowlist Input Server-Side (validate_npm, validate_name, validate_id, dll.)
   ├── Tidak Valid ──► Set HTTP 422, Flash Error, Tolak Eksekusi Query
   └── Valid
          │
          ▼
5. Eksekusi Query Database (Prepared Statement PDO dengan Parameter Binding)
          │
          ▼
6. Sanitasi & Output Encoding pada View HTML (e($data) - htmlspecialchars ENT_QUOTES)
          │
          ▼
[ Respons Aman Dikirim ke Klien ]
```
