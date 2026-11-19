# Notulen Keputusan Teknis – Keamanan Input dan Data LDAP (Mode A)
**Sistem Informasi Akademik Berbasis Outcome-Based Education (OBE)**  
**Universitas YARSI**

---

## 1. Latar Belakang & Filosofi Keamanan Input

Sistem Informasi Kurikulum OBE Universitas YARSI mengelola data akademik penting meliputi Profil Lulusan (PL), Capaian Pembelajaran Lulusan (CPL), Capaian Pembelajaran Mata Kuliah (CPMK), Sub-CPMK, Bahan Kajian (BK), Mata Kuliah (MK), Rencana Pembelajaran Semester (RPS), dan Pemetaan Asesmen.

Dalam arsitektur keamanan aplikasi web, pendekatan **Allowlist (Whitelisting)** diadopsi sebagai standar utama pertahanan input, bukan *Blocklist (Blacklisting)*.
- **Kelemahan Blocklist:** Penyerang dapat dengan mudah memotong filter dengan variasi payload baru, encoding ganda, karakter Unicode, atau spasi alternatif.
- **Kekuatan Allowlist:** Sistem hanya menerima data yang secara eksplisit cocok dengan pola struktur, tipe data, panjang karakter, dan karakter yang diizinkan sesuai domain akademik. Data di luar ketentuan langsung ditolak dengan status **HTTP 422 Unprocessable Content**.

---

## 2. Keputusan Allowlist untuk 5 Kategori Input Kritis

Berdasarkan audit skema database `db_obe` dan kebutuhan kurikulum Universitas YARSI, ditetapkan aturan validasi allowlist sebagai berikut:

### 2.1. Nomor Pokok Mahasiswa (NPM)
- **Tipe Data:** String numerik.
- **Panjang:** Tepat 10 digit.
- **Pola Regex:** `^[0-9]{10}$`
- **Alasan Teknis:** Standar penomoran mahasiswa di Universitas YARSI menggunakan 10 digit angka murni (contoh: `1402022001`). Format ini menolak spasi, huruf, karakter simbol (`'`, `"`, `-`, `#`), dan payload SQL Injection seperti `' OR 1=1--`. Pada form login, fungsi `validate_login_identity()` mengizinkan mahasiswa login menggunakan 10 digit NPM, sementara dosen/admin menggunakan username alfanumerik (3–50 karakter) atau email valid.

### 2.2. Nama Pengguna (Sivitas Akademika)
- **Tipe Data:** String UTF-8.
- **Panjang:** 2 hingga 100 karakter.
- **Pola Regex:** `/^[\p{L}\s\'\-\.,]+$/u`
- **Karakter Diizinkan:** Huruf alfabet (termasuk aksen UTF-8), spasi, tanda hubung (`-`), apostrof (`'`), titik (`.`), dan koma (`,`).
- **Alasan Teknis:** Menampung keberagaman nama sivitas akademika Universitas YARSI, termasuk nama pendek (contoh: "Ali"), nama dengan tanda hubung (contoh: "Siti Nurhaliza-Smith"), nama dengan apostrof (contoh: "Ma'ruf", "O'Connor"), serta gelar akademik (contoh: "Prof. Dr. Ir. H. Ahmad, M.Kom.").
- **Proteksi XSS:** Karakter berbahaya yang digunakan dalam eksekusi skrip (`<`, `>`, `"`, `;`, `(`, `)`, `=`, `/`, `\`) secara ketat ditolak pada validasi server-side. Sebagai lapis pertahanan mendalam (*defense-in-depth*), setiap rendering nama di antarmuka HTML wajib melalui fungsi `e()` (`htmlspecialchars`).

### 2.3. ID Data (Primary Key & Foreign Key)
- **Tipe Data:** Integer positif.
- **Nilai Minimal:** 1.
- **Validasi Teknis:** `ctype_digit($id) && (int)$id >= 1 && (string)(int)$id === (string)$id`
- **Alasan Teknis:** Seluruh primary key (`id`) pada tabel `db_obe` (`cpl`, `profil_lulusan`, `cpmk`, `sub_cpmk`, `bahan_kajian`, `mata_kuliah`, `rps`, `users`) bertipe `INT(11) AUTO_INCREMENT`. Nilai non-numerik, bilangan negatif, nol, atau float langsung ditolak untuk mencegah injeksi parameter URL (misal: `?edit=1'` atau `?detail=1 UNION SELECT`).

### 2.4. Kode Capaian (PL, CPL, CPMK, Sub-CPMK, BK, MK)
- **Tipe Data:** String alfanumerik kapital.
- **Panjang:** 2 hingga 30 karakter.
- **Pola Regex:** `/^[A-Za-z0-9_\-]{2,30}$/`
- **Alasan Teknis:** Kode capaian kurikulum OBE YARSI memiliki format standar:
  - Profil Lulusan: `PL01` s/d `PL10`
  - CPL: `CPL-01` s/d `CPL-08`
  - CPMK: `CPMK-1` s/d `CPMK-10`
  - Sub-CPMK: `Sub-CPMK-1.1` s/d `Sub-CPMK-8.2`
  - Bahan Kajian: `BK01` s/d `BK28`
  - Mata Kuliah: `IF101`, `UY101`
  Semua kode disimpan tanpa tag HTML, spasi bebas, atau kutipan.

### 2.5. Deskripsi Capaian & Catatan
- **Tipe Data:** String teks multi-baris.
- **Panjang:** 1 hingga 1000 karakter (tidak boleh kosong atau hanya whitespace).
- **Validasi Teknis:** `mb_strlen(trim($desc)) >= 1 && mb_strlen(trim($desc)) <= 1000 && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $desc)`
- **Alasan Teknis:** Menyimpan rumusan capaian pembelajaran dan deskripsi mata kuliah. Input dibersihkan dari *null byte* (`\0`) dan karakter kontrol ASCII yang berpotensi merusak buffer database atau sistem log. Data teks tidak disanitasi secara destruktif (tidak menghapus kata valid), melainkan divalidasi keutuhannya dan di-encode saat ditampilkan.

---

## 3. Strategi Penanganan Data LDAP UY (Mode A)

### 3.1. Status Aktual Integrasi LDAP
Audit source code dan konfigurasi server lokal XAMPP mengonfirmasi:
1. Ekstensi PHP `ldap` belum aktif di lingkungan lokal XAMPP (`extension_loaded('ldap') === false`).
2. Aplikasi saat ini menggunakan penyimpanan lokal tabel MySQL `users`.
3. Sesuai etika rekayasa perangkat lunak, sistem **tidak memalsukan (mock fake)** status integrasi LDAP. Arsitektur disiapkan agar siap terhubung saat server LDAP Universitas YARSI dikonfigurasi.

### 3.2. Kebijakan Keamanan Data dari LDAP
Jika LDAP UY aktif, prinsip **Zero Trust Data Ingestion** diterapkan:
1. **Sanitasi Filter LDAP:** Input login (NPM/username) wajib divalidasi menggunakan allowlist (`validate_login_identity`) sebelum dimasukkan ke query filter LDAP (`(uid=$user)` atau `(mail=$user)`). Karakter injeksi LDAP seperti `*`, `(`, `)`, `\`, `\0`, `|`, `&` ditolak.
2. **Untrusted External Attributes:** Atribut yang dikembalikan oleh server LDAP (seperti `cn`, `displayName`, `sn`, `mail`) tidak boleh dipercaya secara membabi buta. Jika akun LDAP memiliki karakter berbahaya (misal akibat celah pada sistem direktori induk), seluruh atribut tersebut wajib melalui:
   - Validasi batas panjang.
   - Output encoding kontekstual dengan `htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.

---

## 4. Standar Respons Kesalahan Validasi

1. **Kode Status HTTP:** Mengembalikan **HTTP 422 Unprocessable Content** ketika validasi server-side gagal.
2. **Pesan Kesalahan Ramah & Non-Leaking:**
   - Memberitahukan batas format yang benar kepada pengguna (contoh: *"NPM harus terdiri dari tepat 10 digit angka."*).
   - **Dilarang keras:** Menampilkan string query SQL, nama tabel/kolom database internal, baris kode, atau stack trace PDOException kepada pengguna. Seluruh exception database ditangkap di blok `catch (PDOException $e)` dan dicatat ke log server/audit log internal secara aman.
