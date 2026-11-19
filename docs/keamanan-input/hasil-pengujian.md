# Hasil Pengujian Keamanan Input dan Data LDAP (Mode A)
**Sistem Informasi Akademik Berbasis Outcome-Based Education (OBE)**  
**Universitas YARSI**

---

## 1. Ringkasan Hasil Pengujian

Seluruh pengujian keamanan dieksekusi secara otomatis pada lingkungan pengujian lokal XAMPP (`http://127.0.0.1/obe`) menggunakan skrip pengujian berbasis PHP CLI dan cURL.

| ID Pengujian | Modul / Skenario Uji | Status | Hasil Aktual |
|---|---|:---:|---|
| **TEST-SEC-01** | SQL Injection pada NPM / Login Identity | **PASS** | HTTP 422, query aman, 0 kebocoran data, tidak ada error SQL |
| **TEST-SEC-02** | XSS pada Nama Pengguna & Output Encoding | **PASS** | HTTP 422, script di-encode menjadi entitas HTML aman, tidak dieksekusi |
| **TEST-REG-01** | Session ID Regeneration Setelah Login | **PASS** | Session ID diperbarui, session lama ditolak |
| **TEST-REG-02** | Logout Komprehensif & Pembersihan Cookie | **PASS** | Data sesi server dihapus, cookie kedaluwarsa |
| **TEST-REG-03** | Idle Timeout (30 mnt) & Absolute Timeout (8 jam) | **PASS** | Sesi kedaluwarsa tepat waktu berdasarkan timestamp server |
| **TEST-REG-04** | Rate Limiting Login (Max 5x Percobaan Gagal) | **PASS** | HTTP 429 Too Many Requests aktif pada percobaan ke-6 |
| **TEST-REG-05** | Alur Autentikasi & Otorisasi RBAC | **PASS** | Hak akses Admin, Kaprodi, dan Dosen terisolasi |
| **TEST-REG-06** | Hashing Password (Bcrypt & Constant-Time) | **PASS** | Password ter-hash, tidak ada plaintext |

---

## 2. Rincian Pengujian 1 – SQL Injection pada NPM / Login Identity

### 2.1. Parameter Uji
* **Skrip Uji:** [tests/sql_injection_npm_test.php](file:///c:/xampp/htdocs/obe/tests/sql_injection_npm_test.php)
* **Target Endpoint:** `POST /auth/login.php`
* **Payload Uji:** `' OR 1=1--`

### 2.2. Prosedur & Eksekusi
1. Mengirim payload SQL Injection `' OR 1=1--` pada field `identity` (NPM/Username) form login.
2. Memeriksa respons kode HTTP dan body respons dari server.
3. Menguji eksekusi query langsung di database MySQL menggunakan *prepared statement*.

### 2.3. Log Output Pengujian
```text
=== PENGUJIAN 1: SQL INJECTION PADA NPM / IDENTITY LOGIN ===
[PASS] validate_npm menolak payload SQLi '' OR 1=1--'
[PASS] validate_login_identity menolak payload SQLi '' OR 1=1--'
[PASS] validate_npm menerima NPM valid 10 digit (1402022001)
[PASS] validate_npm menolak NPM kurang dari 10 digit
[PASS] validate_npm menolak NPM lebih dari 10 digit
[PASS] validate_npm menolak NPM dengan huruf
[PASS] Halaman login dapat diakses via HTTP (HTTP 200)
[PASS] Login dengan payload SQLi menghasilkan status HTTP 422 (aktual: HTTP 422)
[PASS] Pesan validasi non-leaking ditampilkan ke pengguna
[PASS] Tidak ada query SQL, PDOException, atau pesan error database yang bocor di respons
[PASS] Query prepared statement mengeksekusi payload sebagai string literal (0 baris ditemukan, tidak bypass)
=== PENGUJIAN 1 SELESAI: SEMUA CHECK BERHASIL ===
```

### 2.4. Kesimpulan Pengujian 1
1. **Input Ditolak:** Validasi server-side `validate_login_identity()` mendeteksi bahwa input bukan merupakan 10 digit NPM numerik atau username alfanumerik yang sah, sehingga mengembalikan status **HTTP 422**.
2. **Query Tidak Rusak:** Query tetap utuh karena nilai parameter di-binding terpisah dari instruksi SQL.
3. **Data Tidak Bocor:** Penyerang tidak dapat melakukan bypass autentikasi (`0 rows returned`).
4. **Tidak Ada SQL Error:** Server tidak menampilkan exception atau detail struktur tabel.

---

## 3. Rincian Pengujian 2 – XSS pada Nama Pengguna & Output Encoding

### 3.1. Parameter Uji
* **Skrip Uji:** [tests/xss_user_name_test.php](file:///c:/xampp/htdocs/obe/tests/xss_user_name_test.php)
* **Target Endpoint:** `POST /users/index.php`
* **Payload Uji:**
  - Payload Skrip: `<script>alert('XSS')</script>`
  - Payload Tag Gambar: `<img src=x onerror=alert(1)>`
  - Payload Skema URI: `javascript:alert(1)`

### 3.2. Prosedur & Eksekusi
1. Menguji fungsi allowlist `validate_name()` terhadap berbagai payload XSS dan nama akademis yang valid.
2. Menguji fungsi `e()` (`htmlspecialchars`) terhadap karakter khusus HTML (`<`, `>`, `"`, `'`, `&`).
3. Login sebagai administrator dan mengirimkan form pembuatan pengguna baru dengan field `nama` berisi payload skrip.
4. Menguji simulasi data yang berasal dari server LDAP Universitas YARSI yang berpotensi mengandung karakter berbahaya saat dirender ke template HTML.

### 3.3. Log Output Pengujian
```text
=== PENGUJIAN 2: XSS PADA NAMA PENGGUNA & OUTPUT ENCODING ===
[PASS] validate_name menolak payload script '<script>alert('XSS')</script>'
[PASS] validate_name menolak payload img '<img src=x onerror=alert(1)>'
[PASS] validate_name menolak payload javascript scheme 'javascript:alert(1)'
[PASS] validate_name menolak nama terlalu pendek (<2 karakter)
[PASS] validate_name menolak nama terlalu panjang (>100 karakter)
[PASS] validate_name menerima nama standar 'Achmad Hasan K'
[PASS] validate_name menerima gelar, apostrof, dan tanda hubung
[PASS] e() meng-encode script tag menjadi harmless HTML entities: &lt;script&gt;alert(&#039;XSS&#039;)&lt;/script&gt;
[PASS] Tidak ada tag <script> yang lolos setelah di-encode
[PASS] e() meng-encode img tag: &lt;img src=x onerror=alert(1)&gt;
[PASS] Tidak ada tag <img> yang lolos setelah di-encode
[PASS] e() meng-encode tanda kutip ganda dan tunggal serta ampersand
[PASS] Admin berhasil login untuk pengujian modul pengguna
[PASS] Halaman manajemen pengguna dapat diakses oleh admin
[PASS] Input nama berisi XSS ditolak dengan status HTTP 422 (aktual: HTTP 422)
[PASS] Data dengan payload XSS tidak disimpan ke database
[PASS] Render data LDAP terlindungi oleh e(): tidak ada script yang dieksekusi
[PASS] Render data LDAP ditampilkan sebagai entitas teks aman (&lt;script&gt;)
=== PENGUJIAN 2 SELESAI: SEMUA CHECK BERHASIL ===
```

### 3.4. Kesimpulan Pengujian 2
1. **Validasi Input:** Form penambahan/pengubahan pengguna menolak payload script berbahaya dan mengembalikan status **HTTP 422**.
2. **Database Bersih:** Payload berbahaya tidak tersimpan ke dalam database `users`.
3. **Pertahanan Berlapis (*Defense in Depth*):** Fungsi `e()` berhasil mengubah `<script>` menjadi `&lt;script&gt;`, sehingga apabila terdapat data luar dari LDAP yang memiliki karakter berbahaya, browser hanya menampilkan teks biasa tanpa mengeksekusinya.
