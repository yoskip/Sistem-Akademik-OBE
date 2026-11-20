# Notulen Keputusan (Draft) – Mode A: Error, LDAP, dan Log Kampus

**Proyek:** Sistem Informasi Akademik berbasis OBE (PHP Native + MySQL + XAMPP)
**Tanggal:** 2026-11-20
**Status:** DRAFT – bagian bertanda `[KONFIRMASI TIM]` belum disetujui kelompok.

## 1. Keputusan teknis yang sudah diterapkan

1. **Tetap PHP Native.** Tidak ada perubahan ke Laravel/Node.js/Express. Struktur folder, database `db_obe`, tabel, halaman, desain, dan fitur PL/CPL/CPMK/Sub-CPMK tidak diubah.
2. **Status LDAP aktual:** LDAP UY **belum terintegrasi**. Tidak ada `ldap_connect()`, `ldap_bind()`, `ldap_search()`, konfigurasi kredensial LDAP, atau ekstensi `ldap` aktif. Tidak ada LDAP tiruan yang dipasang ke alur login produksi.
3. **Fail closed:** `includes/ldap_guard.php` hanya menyediakan guard. Jika belum ada verifier yang didaftarkan, login lokal tetap berjalan seperti semula. Jika verifier didaftarkan dan mengembalikan `false`, melempar error, atau timeout, login ditolak dan tidak ada session yang dibuat.
4. **Pesan error umum** dipakai untuk error tak tertangani:

   > Terjadi kesalahan pada sistem. Silakan coba kembali beberapa saat lagi. Jika masalah berlanjut, hubungi administrator.

   Pesan disertai ID kejadian `ERR-YYYYMMDD-XXXXXX`.
5. **Log internal** berada di `storage/logs/security.log` dan `storage/logs/php_error.log`, ditutup dengan `storage/logs/.htaccess` (`Require all denied`). File log tidak mengandung password, token, cookie, session ID, SQL, kredensial DB, atau respons LDAP lengkap.
6. **Identitas pada log** disimpan sebagai `npm_masked` + `identity_hash`, bukan NPM penuh.
7. **Event yang dicatat:** login sukses/gagal, logout, akses ditolak (403), CSRF ditolak, perubahan peran, kegagalan LDAP, kegagalan DB, error PHP/kejadian aplikasi.
8. **Pengujian** memakai mock verifier pada level fungsi, bukan server LDAP nyata.

## 2. Bagian yang perlu dikonfirmasi kelompok

- `[KONFIRMASI TIM]` Apakah format pesan error umum dan ID kejadian sudah sesuai kebijakan fakultas?
- `[KONFIRMASI TIM]` Apakah penyimpanan NPM sebagai `npm_masked` + hash sudah memenuhi kebutuhan audit, atau akan diminta NPM penuh di log internal dengan akses pembatasan lebih ketat?
- `[KONFIRMASI TIM]` Guard LDAP di `includes/ldap_guard.php` akan dipakai sebagai kontrak saat integrasi LDAP UY nyata dibangun. Tolak/terima kontrak ini sebelum implementasi LDAP berjalan.
- `[KONFIRMASI TIM]` Retensi log (mis. 30/90 hari) dan prosedur rotasi belum ditentukan; saat ini file ditulis append terus-menerus.
- `[KONFIRMASI TIM]` Konfigurasi `php.ini` produksi (lihat `konfigurasi-keamanan.md`) perlu diterapkan oleh admin server, karena perubahan runtime tidak menggantikan `display_startup_errors` pada fase startup Apache.
- `[KONFIRMASI TIM]` Daftar peran yang dicatat (`admin`, `kaprodi`, `dosen`) perlu diverifikasi jika ada penambahan peran baru.
- Reviewer eksternal tidak tersedia di repo. Pemilik repo menyetujui dan melakukan merge PR #1 atas instruksi eksplisit pengguna.

## 3. Status GitHub aktual

- Branch `error-log` dibuat dari `main`, commit `3f03d0a` + `e54e682`, dan berhasil di-push ke `origin/error-log`.
- Pull Request: **#1 – Implementasi Error Handling dan Security Logging** — https://github.com/yoskip/Sistem-Akademik-OBE/pull/1
- Sebelum merge, permintaan review via API ditolak GitHub (HTTP 422): `HasanBiasaAje` bukan collaborator dan `yoskip` adalah author PR sehingga tidak dapat menyetujui PR sendiri.
- Atas instruksi eksplisit pengguna (pemilik repo), PR #1 telah **di-merge ke `main`** dengan metode squash sebagai commit `753e1af`. Tidak ada klaim approval dari anggota lain.
- Branch `error-log` masih tersimpan di remote; `main` lokal sudah di-fast-forward ke `753e1af`.

## 4. Catatan etika

Tidak ada klaim bahwa LDAP sudah terhubung, tidak ada screenshot hasil pengujian yang dibuat-buat, dan tidak ada hasil test yang ditulis lulus tanpa dijalankan. Bukti aktual ada di `docs/error-log/bukti/` dan `hasil-pengujian.md`.
