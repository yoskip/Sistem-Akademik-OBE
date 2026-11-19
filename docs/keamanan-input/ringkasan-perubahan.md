# Ringkasan Perubahan Source Code (Pull Request & Code Review)
**Tugas Keamanan Input dan Data LDAP (Mode A)**  
**Sistem Informasi Akademik Berbasis Outcome-Based Education (OBE) Universitas YARSI**

---

## 1. Ikhtisar Perubahan

Perubahan ini mengimplementasikan pengamanan komprehensif terhadap validasi input server-side, perlindungan query SQL dari serangan SQL Injection, mitigasi serangan XSS, dan standarisasi penanganan data LDAP (Mode A) pada Sistem Informasi Kurikulum OBE Universitas YARSI.

Seluruh perubahan mematuhi batasan proyek:
- Menggunakan **PHP Native** murni tanpa framework eksternal.
- Struktur tabel dan nama database `db_obe` tetap 100% utuh tanpa perubahan.
- Seluruh modul akademik OBE (PL, CPL, CPMK, Sub-CPMK, BK, MK, RPS, Pemetaan, Penilaian) tetap berfungsi normal dengan antarmuka UI Bootstrap asli.

---

## 2. File yang Ditambahkan

| File | Deskripsi |
|---|---|
| `tests/sql_injection_npm_test.php` | Skrip otomatis pengujian SQL Injection pada input NPM/login identity (`' OR 1=1--`). |
| `tests/xss_user_name_test.php` | Skrip otomatis pengujian XSS pada input nama pengguna dan output encoding LDAP (`<script>alert('XSS')</script>`). |
| `docs/keamanan-input/notulen-keputusan.md` | Dokumentasi keputusan teknis allowlist, regex, batas karakter, dan integrasi LDAP. |
| `docs/keamanan-input/laporan-penerapan.md` | Laporan teknis file yang diubah dan cara kerja fungsi validasi terpusat. |
| `docs/keamanan-input/audit-query.md` | Laporan audit query SQL sebelum vs sesudah dan proteksi query dinamis. |
| `docs/keamanan-input/hasil-pengujian.md` | Bukti eksekusi dan log hasil pengujian keamanan (100% PASS). |
| `docs/keamanan-input/ringkasan-perubahan.md` | Dokumen panduan pull request dan tinjauan kode bagi reviewer/dosen. |

---

## 3. File yang Dimodifikasi

1. **`includes/functions.php`**:
   - Peningkatan `e()` dengan flag `htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
   - Penambahan fungsi validasi: `validate_npm()`, `validate_name()`, `validate_id()`, `validate_code()`, `validate_description()`, `validate_username()`, dan `validate_login_identity()`.
   - Pengamanan fungsi `id_map()` dengan strict allowlist pasangan tabel dan kolom referensi kurikulum.

2. **`auth/login.php`**:
   - Validasi allowlist parameter `identity` sebelum query atau rate limiting.
   - Mengembalikan HTTP 422 untuk input yang tidak memenuhi standar format.

3. **`users/index.php`**:
   - Validasi allowlist form pengguna (nama, username/NPM, email, role, password, ID).
   - Validasi ID pada penghapusan akun. Pengembalian HTTP 422 pada validasi gagal.

4. **`profil_lulusan/index.php` (PL)**:
   - Validasi allowlist kode PL, nama PL, deskripsi, dan ID data.

5. **`cpl/index.php` (CPL)**:
   - Validasi allowlist kode CPL, deskripsi CPL, dan ID data.

6. **`cpmk/index.php` (CPMK)**:
   - Validasi foreign key `cpl_id`, kode CPMK, deskripsi, dan ID data.

7. **`sub_cpmk/index.php` (Sub-CPMK)**:
   - Validasi foreign key `cpmk_id`, kode Sub-CPMK, deskripsi, dan ID data.

8. **`bahan_kajian/index.php` (BK)**:
   - Validasi allowlist kode BK, nama BK, deskripsi, acuan, dan ID data.

9. **`mata_kuliah/index.php` (MK)**:
   - Validasi kode MK, nama MK, batas SKS, semester, kode internal, dan ID data.

10. **`rps/index.php`, `rps/rumusan_cpl.php`, `rps/rumusan_mk.php`**:
    - Validasi relasi `mk_id`, `cpmk_id`, nama dosen, skor maksimal, total, dan ID data.

11. **`pemetaan/cpl_bk_mk.php`**:
    - Validasi foreign keys `cpl_id`, `bahan_kajian_id`, `mk_id`, dan ID relasi.

12. **`penilaian/bobot.php`, `penilaian/teknik.php`, `penilaian/mekanisme.php`**:
    - Validasi allowlist kolom dinamis pada proses UPDATE tabel penilaian.

13. **`dashboard/index.php`**:
    - Penambahan allowlist nama tabel pada perhitungan statistik ringkasan kurikulum.

---

## 4. Panduan Verifikasi & Eksekusi Pengujian

Bagi reviewer atau dosen pembimbing, pengujian dapat dijalankan melalui terminal dengan perintah berikut:

```powershell
# 1. Pastikan Apache dan MySQL XAMPP sedang berjalan
# 2. Jalankan pengujian keamanan SQL Injection pada NPM
php tests/sql_injection_npm_test.php

# 3. Jalankan pengujian keamanan XSS pada Nama Pengguna & Output Encoding
php tests/xss_user_name_test.php

# 4. Jalankan pengujian regresi keamanan sesi
php tests/session_regeneration_test.php
php tests/session_logout_test.php
php tests/session_timeout_test.php
```

Semua pengujian terkonfirmasi menghasilkan output `[PASS]` tanpa kegagalan.
