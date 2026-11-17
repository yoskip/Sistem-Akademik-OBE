# Notulen Keputusan Arsitektur dan Keamanan Autentikasi – Proyek OBE
## Mode A – Proyek LDAP UY: Sesi Setelah Login LDAP

Dokumen ini memuat catatan hasil diskusi dan kesepakatan tim pengembang Sistem Informasi Kurikulum Berbasis Outcome-Based Education (OBE) Universitas YARSI terkait penerapan standar keamanan autentikasi dan manajemen sesi pada penugasan **Mode A**.

---

## 1. Informasi Diskusi

- **Topik:** Keamanan Session Setelah Login LDAP UY (Mode A).
- **Aplikasi:** Sistem Informasi Kurikulum Berbasis Outcome-Based Education (OBE).
- **Institusi:** Program Studi Teknik Informatika – Universitas YARSI.
- **Teknologi Utama:** PHP Native, MySQL, Apache (XAMPP), Bootstrap 5.
- **Peran & Tanggung Jawab:**
  - *Lead Developer & Application Security Engineer:* Penanggung jawab audit arsitektur, implementasi pengamanan sesi pasca-login, penanganan timeout, dan skrip pengujian keamanan.
  - *Core Module Maintainer:* Penanggung jawab verifikasi integritas seluruh modul akademik OBE (PL, CPL, CPMK, Sub-CPMK, RPS, Penilaian).
  - *Quality Assurance & Documentation:* Penanggung jawab verifikasi pengujian manual, pengambilan screenshot bukti, dan penyusunan laporan akhir.

---

## 2. Poin-Poin Kesepakatan dan Keputusan

### Keputusan 1: Status LDAP UY dan Fokus Pengamanan Sesi Mode A
- **Latar Belakang:** Penugasan mengambil tema *Mode A – Proyek LDAP UY: Sesi Setelah Login LDAP*. Perlu kepastian status implementasi LDAP saat ini.
- **Hasil Audit:** LDAP UY **belum terintegrasi** di dalam basis kode proyek (ekstensi `ldap` PHP belum aktif dan tidak ada modul LDAP). Sistem saat ini menggunakan autentikasi akun internal MySQL `db_obe`.
- **Keputusan:**
  - Mematuhi aturan utama: **dilarang mengarang bahwa LDAP sudah terintegrasi**.
  - Mengimplementasikan seluruh pengamanan sesi pasca-login yang menjadi inti tugas Mode A.
  - Memastikan arsitektur sesi bersifat *identity-provider agnostic*: begitu proses autentikasi berhasil (baik verifikasi akun basis data saat ini maupun integrasi LDAP UY mendatang), sesi ditangani secara aman dengan Session ID baru, data non-sensitif, cookie flags ketat, dual timeout, dan logout bersih.

---

### Keputusan 2: Spesifikasi Data yang Disimpan dalam Sesi
- **Data yang Diperbolehkan dan Wajib Disimpan:**
  1. `id`: ID unik pengguna (integer).
  2. `username`: Username akun pengguna.
  3. `npm_nidn`: Nomor Pokok Mahasiswa (NPM) atau Nomor Induk Dosen Nasional (NIDN) jika tersedia, dengan fallback ke username.
  4. `nama`: Nama lengkap pengguna untuk tampilan UI dan audit.
  5. `role`: Peran otorisasi pengguna (`admin`, `kaprodi`, `dosen`).
  6. `login_at`: Stempel waktu Unix saat autentikasi berhasil (dasar Absolute Timeout).
  7. `last_activity`: Stempel waktu Unix saat request terakhir diproses (dasar Idle Timeout).
- **Data yang DILARANG KERAS Disimpan di Session:**
  - Password LDAP (plaintext).
  - Kredensial / token bind LDAP.
  - Hash password (bcrypt / MD5 / SHA).
  - Password basis data (`DB_PASS`).
  - Informasi pribadi sensitif lainnya yang tidak diperlukan dalam penentuan otorisasi.

---

### Keputusan 3: Penanganan Atribut Cookie `Secure`, `HttpOnly`, dan `SameSite`
- **Konfigurasi Cookie:**
  - `HttpOnly = true`: Wajib aktif untuk melindungi cookie `PHPSESSID` dari pembacaan skrip berbahaya via serangan XSS.
  - `SameSite = 'Lax'`: Wajib aktif untuk memitigasi serangan pemalsuan request lintas situs (CSRF).
  - `Path = '/'`: Cookie berlaku untuk seluruh halaman modul akademik OBE.
  - `lifetime = 0`: Cookie berstatus sesi peramban (terhapus otomatis saat peramban ditutup).
  - `Secure`: Dikontrol secara dinamis mengikuti lingkungan:
    - Lingkungan lokal HTTP (XAMPP): `Secure = false` agar cookie tetap terkirim dan login tidak gagal.
    - Lingkungan produksi HTTPS: `Secure = true` agar cookie hanya melintas melalui kanal terenkripsi TLS.
- **Penetapan Waktu Konfigurasi:** Seluruh parameter cookie diatur melalui `session_set_cookie_params()` dan direktif `ini_set()` **sebelum** fungsi `session_start()` dipanggil.

---

### Keputusan 4: Regenerasi Session ID Pasca-Autentikasi (Anti-Session Fixation)
- **Latar Belakang:** Penyerang dapat menyuntikkan ID sesi yang sudah diketahuinya sebelum korban login (*Session Fixation*).
- **Keputusan:**
  - Tepat setelah verifikasi kredensial berhasil di `auth/login.php`, sistem mengeksekusi `session_regenerate_id(true)`.
  - Argumen `true` memastikan berkas sesi lama di server langsung dihapus, sehingga ID sebelum login hangus seketika dan tidak dapat disalahgunakan.

---

### Keputusan 5: Kebijakan Batas Waktu Sesi Ganda (*Dual Timeout Strategy*)
- **Latar Belakang:** Menghindari sesi menggantung tanpa batas jika pengguna meninggalkan workstation atau lupa logout.
- **Keputusan:**
  - **Idle Timeout (30 menit / 1.800 detik):** Jika pengguna tidak melakukan request selama 30 menit, sesi dinyatakan kedaluwarsa.
  - **Absolute Timeout (8 jam / 28.800 detik):** Sesi dipaksa berakhir setelah 8 jam sejak waktu awal login, meskipun pengguna terus aktif.
  - Menggunakan evaluasi stempel waktu eksplisit di sisi server PHP pada fungsi `enforce_session_timeout()` yang dipanggil di setiap request terproteksi (`require_login()`), bukan hanya mengandalkan garbage collector PHP.
  - Sesi yang kedaluwarsa dibersihkan dan pengguna dialihkan ke `auth/login.php?expired=1`.

---

### Keputusan 6: Prosedur Logout Menyeluruh dan Proteksi Cache
- **Keputusan:**
  1. Mengosongkan data sesi di memori: `$_SESSION = []`.
  2. Menghapus cookie browser dengan mengirimkan `setcookie()` bernilai kosong/`deleted` dengan waktu lampau (`time() - 42000`) dan atribut yang identik.
  3. Menghancurkan berkas sesi fisik di server via `session_destroy()`.
  4. Mengirimkan header HTTP `Cache-Control: no-store, no-cache, must-revalidate, max-age=0` pada halaman login, logout, dan seluruh halaman modul terproteksi guna mencegah data kurikulum tampil kembali melalui tombol *Back* peramban.

---

### Keputusan 7: Menjamin Integritas Fitur Akademik OBE
- **Keputusan:**
  - Menjaga keutuhan seluruh modul OBE: Profil Lulusan (PL), CPL, CPMK, Sub-CPMK, Bahan Kajian, Mata Kuliah, Pemetaan Matriks, Penilaian Bobot, RPS, dan Monitoring.
  - Tidak mengubah skema tabel maupun relasi basis data `db_obe`.
  - Memastikan otorisasi berbasis peran (`admin`, `kaprodi`, `dosen`) tetap berjalan semestinya di sisi server.
