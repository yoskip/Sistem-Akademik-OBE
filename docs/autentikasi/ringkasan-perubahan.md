# Ringkasan Perubahan Kode – Sistem Akademik Berbasis OBE

Dokumen ini mendokumentasikan setiap perubahan kode yang dilakukan untuk mengamankan autentikasi dan manajemen sesi pada proyek OBE, beserta alasan teknis dan dampaknya terhadap keamanan.

---

## 1. Tabel Ringkasan File yang Diubah

| Nama File | Perubahan yang Dilakukan | Alasan Perubahan | Pengaruh terhadap Keamanan |
| :--- | :--- | :--- | :--- |
| `includes/functions.php` | Menambahkan pengecekan direktori penyimpanan sesi untuk lingkungan CLI (`session_save_path(sys_get_temp_dir())`). | Mencegah error/warning `Permission denied` saat menjalankan skrip pengujian atau CLI di environment dengan direktori XAMPP terbatas. | Menjamin konsistensi pengujian otomatis tanpa mengorbankan keamanan sesi Apache di lingkungan web. |
| `tests/session_logout_test.php` | Memperbarui fungsi `session_cookie_value` agar mengenali cookie yang kedaluwarsa (`expires <= time()`) atau bernilai `'deleted'` sebagai cookie tidak aktif. | Standar implementasi `setcookie` di PHP mengirimkan nilai `'deleted'` dan waktu lampau saat cookie dihapus, yang dipertahankan cURL dengan status *expired*. | Menjamin pengujian merefleksikan perilaku peramban nyata saat cookie sesi dihapus oleh server. |
| `tests/session_regeneration_test.php` | Menyelaraskan fungsi pembacaan cookie `session_cookie_value` dengan standar verifikasi cookie aktif yang sama. | Menjaga konsistensi pustaka pengujian antara pengujian regenerasi sesi dan pengujian logout. | Menjamin hasil pengujian akurat, deterministik, dan dapat diandalkan. |
| `docs/autentikasi/*` | Membuat 6 dokumen dokumentasi: `audit-proyek.md`, `konfigurasi-session.md`, `pengujian-session.md`, `ringkasan-perubahan.md`, `notulen-keputusan.md`, `daftar-bukti.md`. | Memenuhi standar dokumentasi rekayasa perangkat lunak dan keamanan aplikasi untuk laporan akademik. | Menyediakan bukti audit, panduan operasional, dan notulen keputusan yang transparan dan dapat ditinjau. |

---

## 2. Detail Potongan Kode Penting

### A. Fallback Session Save Path untuk Mode CLI (`includes/functions.php`)

#### Sebelum:
```php
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = session_cookie_secure();
    ...
```

#### Sesudah:
```php
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Pastikan path penyimpanan session di CLI memiliki akses tulis
    if (PHP_SAPI === 'cli') {
        $savePath = session_save_path();
        if ($savePath === '' || !@is_writable($savePath)) {
            @session_save_path(sys_get_temp_dir());
        }
    }

    $secure = session_cookie_secure();
    ...
```
*Alasan Teknis:* Pada mode CLI (PHP_SAPI `'cli'`), jika direktori bawaan `C:\xampp\tmp` tidak memiliki izin tulis dari user sandbox/terminal, PHP dialihkan ke direktori temporer sistem (`sys_get_temp_dir()`). Di lingkungan web server Apache, kode ini tidak dieksekusi sehingga tetap menggunakan konfigurasi standar web server XAMPP.

---

### B. Evaluasi Status Cookie Terhapus pada Pengujian cURL (`tests/session_logout_test.php`)

#### Sebelum:
```php
function session_cookie_value($ch, string $name): string
{
    foreach ((array)curl_getinfo($ch, CURLINFO_COOKIELIST) as $line) {
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) >= 7 && $parts[5] === $name) {
            return $parts[6];
        }
    }
    return '';
}
```

#### Sesudah:
```php
function session_cookie_value($ch, string $name): string
{
    foreach ((array)curl_getinfo($ch, CURLINFO_COOKIELIST) as $line) {
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) >= 7 && $parts[5] === $name) {
            $expiry = (int)$parts[4];
            // Abaikan cookie yang sudah dihapus/kedaluwarsa (expires di masa lalu atau bernilai 'deleted')
            if (($expiry > 0 && $expiry <= time()) || $parts[6] === 'deleted' || $parts[6] === '') {
                continue;
            }
            return $parts[6];
        }
    }
    return '';
}
```
*Alasan Teknis:* Fungsi bawaan PHP `setcookie($name, '', time() - 42000)` mengirimkan header HTTP:
`Set-Cookie: PHPSESSID=deleted; expires=Thu, 01-Jan-1970 00:00:01 GMT; Max-Age=0; path=/; HttpOnly; SameSite=Lax`
libcurl mencatat nilai cookie tersebut sebagai `'deleted'` dengan timestamp `1`. Kode pengujian sebelum perbaikan memperlakukan string `'deleted'` sebagai cookie aktif yang belum terhapus. Perbaikan ini menyelaraskan evaluasi cURL dengan perilaku standar peramban (RFC 6265).

---

### C. Alur Regenerasi ID Sesi Pasca Login (`auth/login.php`)

Potongan kode penting yang dipertahankan dan diverifikasi:
```php
session_regenerate_id(true);
$_SESSION['user'] = [
    'id'       => (int)$user['id'],
    'nama'     => $user['nama'],
    'username' => $user['username'],
    'npm_nidn' => $user['npm'] ?? $user['nidn'] ?? $user['username'],
    'role'     => $user['role'],
];
$_SESSION['login_at'] = time();
$_SESSION['last_activity'] = time();
unset($_SESSION['csrf']);
log_activity('login', 'auth', 'Pengguna berhasil masuk');
flash('success', 'Selamat datang, ' . $user['nama'] . '.');
redirect(BASE_URL . '/dashboard/index.php');
```
*Keamanan:* ID sesi lama dihancurkan di server dan digantikan dengan ID baru. Data sensitif seperti password mentah dan hash tidak pernah dimasukkan ke dalam sesi.

---

### D. Alur Penghancuran Sesi & Cookie pada Logout (`auth/logout.php` & `includes/functions.php`)

```php
function destroy_session_and_cookie(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'] ?: '/',
            'domain'   => $p['domain'] ?? '',
            'secure'   => (bool)($p['secure'] ?? false),
            'httponly' => (bool)($p['httponly'] ?? true),
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
```
*Keamanan:* Memastikan sesi dihancurkan dari 3 sisi: memori variabel PHP (`$_SESSION = []`), peramban klien (`setcookie` kedaluwarsa), dan penyimpanan file server (`session_destroy()`).
