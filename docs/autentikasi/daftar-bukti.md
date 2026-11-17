# Daftar Bukti dan Panduan Pengambilan Bukti – Sistem Akademik Berbasis OBE

Dokumen ini memuat panduan lengkap mengenai daftar tangkapan layar (*screenshot*) dan bukti pengujian yang perlu diambil secara manual oleh mahasiswa untuk melengkapi laporan tugas kuliah.

> **Peringatan Keamanan saat Mengambil Bukti:**
> - Jangan menampilkan password akun asli dalam dokumen laporan.
> - Jika menampilkan tabel cookie atau header jaringan, pastikan token atau kredensial rahasia disensor (*blur*) bila dipublikasikan ke publik.
> - Gunakan akun pengujian bawaan (`admin`, `kaprodi`, `dosen`) yang berjalan di server lokal XAMPP.

---

## 1. Daftar Bukti yang Harus Diambil dan Status Ketersediaan

| No | Nama Bukti | Media / Sumber / Lokasi | Tujuan Bukti | Status Ketersediaan |
| :---: | :--- | :--- | :--- | :---: |
| **1** | **Cookie Sebelum Login** | Browser DevTools (F12) &rarr; Tab Application / Storage &rarr; Cookies (`http://127.0.0.1`) | Memperlihatkan bahwa sesi awal belum terautentikasi dan memiliki ID sesi pra-login (untuk token CSRF). | **Perlu Diambil Manual di Browser** |
| **2** | **Cookie Setelah Login** | Browser DevTools (F12) &rarr; Tab Application / Storage &rarr; Cookies (`http://127.0.0.1`) | Membuktikan bahwa nilai `PHPSESSID` telah berganti (*regenerated*), serta memiliki bendera `HttpOnly` tercentang dan `SameSite = Lax`. | **Perlu Diambil Manual di Browser** |
| **3** | **Cookie Setelah Logout** | Browser DevTools (F12) &rarr; Tab Application / Storage &rarr; Cookies (`http://127.0.0.1`) | Membuktikan bahwa cookie `PHPSESSID` telah terhapus atau bernilai `deleted` dengan tanggal kedaluwarsa lampau. | **Perlu Diambil Manual di Browser** |
| **4** | **Header Anti-Cache pada Network Tab** | Browser DevTools (F12) &rarr; Tab Network &rarr; Request Dashboard / Logout | Memperlihatkan response header `Cache-Control: no-store, no-cache, must-revalidate, max-age=0` dan `Pragma: no-cache`. | **Perlu Diambil Manual di Browser** |
| **5** | **Hasil Test 1: Session Regeneration** | Terminal / PowerShell (`php tests/session_regeneration_test.php`) | Membuktikan skrip pengujian regenerasi ID sesi lulus 100% (7/7 asersi PASS). | **Sudah Teruji (Tinggal Screenshot)** |
| **6** | **Hasil Test 2: Invalidasi Logout** | Terminal / PowerShell (`php tests/session_logout_test.php`) | Membuktikan skrip pengujian pembersihan sesi dan penolakan cookie lama lulus 100% (6/6 asersi PASS). | **Sudah Teruji (Tinggal Screenshot)** |
| **7** | **Hasil Uji Logika Timeout** | Terminal / PowerShell (`php tests/session_timeout_test.php`) | Membuktikan logika idle timeout 30 menit dan absolute timeout 8 jam lulus 100% (8/8 asersi PASS). | **Sudah Teruji (Tinggal Screenshot)** |
| **8** | **Uji Proteksi Tombol Back Peramban** | Browser (Chrome / Edge / Firefox) | Menunjukkan bahwa setelah menekan tombol logout, jika pengguna menekan tombol *Back* pada peramban, halaman tidak menampilkan data lama melainkan mengarahkan kembali ke login. | **Perlu Diambil Manual di Browser** |
| **9** | **Tampilan Notifikasi Sesi Berakhir** | Browser (`http://127.0.0.1/obe/auth/login.php?expired=1`) | Menampilkan alert peringatan bertuliskan *"Sesi Anda telah berakhir. Silakan login kembali."*. | **Perlu Diambil Manual di Browser** |
| **10** | **Integritas Fitur Modul OBE** | Browser (Halaman CPL, CPMK, Sub-CPMK, RPS) | Memperlihatkan bahwa seluruh modul akademik OBE tetap berfungsi normal dengan navigasi dan data yang utuh. | **Perlu Diambil Manual di Browser** |

---

## 2. Panduan Langkah Demi Langkah Pengambilan Bukti

### A. Mengambil Bukti Cookie Browser (Bukti 1, 2, dan 3)
1. Buka peramban Google Chrome atau Microsoft Edge.
2. Buka URL `http://127.0.0.1/obe/auth/login.php`.
3. Tekan tombol `F12` atau klik kanan &rarr; **Inspect**.
4. Masuk ke tab **Application** (Chrome/Edge) atau **Storage** (Firefox).
5. Pada panel kiri, klik **Cookies** &rarr; `http://127.0.0.1`.
6. **Ambil Screenshot 1 (Sebelum Login):** Tangkap baris `PHPSESSID` sebelum login.
7. Masukkan kredensial login (misal `admin` / `admin123`) lalu klik **Login**.
8. Setelah masuk ke dashboard, perhatikan baris `PHPSESSID` di panel cookies.
9. **Ambil Screenshot 2 (Setelah Login):** Perlihatkan bahwa nilai Value telah berubah menjadi ID baru, kolom `HTTPOnly` tercentang (`✓`), dan kolom `SameSite` berisi `Lax`.
10. Klik tombol **Logout** di pojok kanan atas.
11. **Ambil Screenshot 3 (Setelah Logout):** Perlihatkan bahwa cookie telah hilang dari daftar atau kosong.

---

### B. Mengambil Bukti Header Network (Bukti 4)
1. Buka DevTools (`F12`), masuk ke tab **Network**.
2. Pastikan filter **Doc** aktif.
3. Buka halaman dashboard (`http://127.0.0.1/obe/dashboard/index.php`).
4. Klik pada request pertama (`index.php`), lalu lihat panel kanan pada bagian **Response Headers**.
5. **Ambil Screenshot 4:** Tangkap header:
   - `Cache-Control: no-store, no-cache, must-revalidate, max-age=0`
   - `Pragma: no-cache`
   - `Expires: 0`

---

### C. Mengambil Bukti Pengujian Terminal (Bukti 5, 6, dan 7)
1. Buka terminal (PowerShell atau Command Prompt) di direktori proyek `C:\xampp\htdocs\obe`.
2. Pastikan service Apache dan MySQL di XAMPP sedang berjalan.
3. Jalankan perintah Test 1:
   ```powershell
   php tests/session_regeneration_test.php
   ```
   **Ambil Screenshot 5** yang memperlihatkan seluruh teks `[PASS]`.
4. Jalankan perintah Test 2:
   ```powershell
   php tests/session_logout_test.php
   ```
   **Ambil Screenshot 6** yang memperlihatkan seluruh teks `[PASS]`.
5. Jalankan perintah Uji Timeout:
   ```powershell
   php tests/session_timeout_test.php
   ```
   **Ambil Screenshot 7** yang memperlihatkan seluruh teks `[PASS]`.

---

### D. Mengambil Bukti Tombol Back dan Sesi Kedaluwarsa (Bukti 8 dan 9)
1. Login ke aplikasi, buka modul CPL atau CPMK.
2. Klik **Logout** melalui menu user di pojok kanan atas.
3. Setelah kembali di halaman login, tekan tombol **Back** (&larr;) pada peramban.
4. **Ambil Screenshot 8:** Tunjukkan bahwa peramban tidak menampilkan data CPL/CPMK lama melainkan tetap berada di form login.
5. Untuk bukti timeout, buka URL `http://127.0.0.1/obe/auth/login.php?expired=1`.
6. **Ambil Screenshot 9:** Tunjukkan kotak peringatan berwarna merah (*alert danger*) bertuliskan: *"Sesi Anda telah berakhir. Silakan login kembali."*.

---

### E. Mengambil Bukti Modul OBE Berjalan Normal (Bukti 10)
1. Login sebagai akun Dosen atau Kaprodi.
2. Buka menu **CPL Program Studi** (`/cpl/index.php`), ambil screenshot matriks CPL.
3. Buka menu **CPMK** (`/cpmk/index.php`), ambil screenshot daftar capaian pembelajaran mata kuliah.
4. Buka menu **Sub-CPMK** (`/sub_cpmk/index.php`), ambil screenshot daftar sub-cpmk.
5. **Ambil Screenshot 10:** Tunjukkan antarmuka navigasi sidebar, data tabel, dan badge peran pengguna yang berfungsi sempurna.
