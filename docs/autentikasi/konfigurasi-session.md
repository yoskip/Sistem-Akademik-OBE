# Konfigurasi dan Arsitektur Keamanan Session – Sistem Akademik Berbasis OBE

Dokumen ini menjelaskan implementasi teknis manajemen sesi (PHP Session) dan cookie yang aman pada proyek Sistem Informasi Kurikulum Berbasis Outcome-Based Education (OBE).

---

## 1. Konfigurasi Cookie Sesi

Pengaturan parameter cookie sesi dilakukan **sebelum** fungsi `session_start()` dieksekusi. Hal ini menjamin seluruh cookie sesi yang diterbitkan ke browser klien memenuhi standar keamanan industri (*OWASP Session Management Cheat Sheet*).

### A. Parameter Keamanan Cookie

| Parameter | Nilai | Tujuan Keamanan |
| :--- | :--- | :--- |
| **HttpOnly** | `true` | Mencegah pembacaan cookie oleh skrip JavaScript di browser (`document.cookie`), memitigasi serangan pencurian sesi via Cross-Site Scripting (XSS). |
| **Secure** | Dinamis (`true` / `false`) | Pada lingkungan produksi dengan HTTPS, cookie hanya dikirim melalui koneksi terenkripsi TLS/SSL. Pada lingkungan lokal (HTTP XAMPP), dinonaktifkan agar cookie tetap terkirim. |
| **SameSite** | `'Lax'` | Membatasi pengiriman cookie pada request lintas situs (*cross-site*), memberikan perlindungan dasar terhadap Cross-Site Request Forgery (CSRF). |
| **Path** | `'/'` | Membatasi cakupan cookie agar berlaku pada seluruh endpoint aplikasi. |
| **Lifetime** | `0` | Menjadikan cookie sebagai *Session Cookie* (dihapus otomatis saat peramban ditutup), bukan *Persistent Cookie*. |

### B. Konfigurasi Secure Dinamis Berbasis Environment

Fungsi `session_cookie_secure()` mengevaluasi apakah koneksi menggunakan HTTPS atau menerima instruksi dari variabel environment:

```php
function is_https_request(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function session_cookie_secure(): bool
{
    $env = getenv('SESSION_COOKIE_SECURE');
    if ($env === false && isset($_SERVER['SESSION_COOKIE_SECURE'])) {
        $env = $_SERVER['SESSION_COOKIE_SECURE'];
    }
    if ($env !== false && $env !== '') {
        return filter_var($env, FILTER_VALIDATE_BOOLEAN);
    }

    return is_https_request();
}
```

### C. Inisialisasi Sesi Terpusat (`start_secure_session`)

```php
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Penanganan direktori penyimpanan sesi pada lingkungan pengujian CLI
    if (PHP_SAPI === 'cli') {
        $savePath = session_save_path();
        if ($savePath === '' || !@is_writable($savePath)) {
            @session_save_path(sys_get_temp_dir());
        }
    }

    $secure = session_cookie_secure();

    // Direktif keamanan bawaan PHP
    ini_set('session.use_strict_mode', '1');  // Tolak ID sesi yang tidak diinisialisasi server
    ini_set('session.use_only_cookies', '1'); // Larang ID sesi dikirim lewat parameter URL
    ini_set('session.use_trans_sid', '0');    // Nonaktifkan transparansi session ID di URL
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', $secure ? '1' : '0');
    ini_set('session.cookie_lifetime', '0');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
    enforce_session_timeout();
}
```

---

## 2. Regenerasi ID Sesi Pasca Login

Ketika pengguna berhasil memasukkan username/email dan password yang valid pada `auth/login.php`:

1. Sistem menjalankan `session_regenerate_id(true)`. Argumen `true` menginstruksikan PHP untuk menghapus file sesi lama di server dan menghasilkan ID sesi baru yang sepenuhnya acak.
2. Langkah ini menetralkan serangan **Session Fixation**, di mana penyerang mencoba memaksakan ID sesi tertentu kepada korban sebelum login.
3. Hanya data identitas publik dan otorisasi yang disimpan ke dalam sesi:

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
unset($_SESSION['csrf']); // Hapus token form login lama
```

> **Aturan Keamanan Data Sesi:**
> - **DILARANG** menyimpan password mentah (`password`).
> - **DILARANG** menyimpan hash password (`password_hash`).
> - **DILARANG** menyimpan kredensial database (`DB_USER`, `DB_PASS`).
> - Hanya simpan atribut: ID pengguna, nama, username/NPM, peran (role), waktu login, dan waktu aktivitas terakhir.

---

## 3. Batas Waktu Sesi (Session Timeouts)

Aplikasi menerapkan dua lapis batas waktu (*Dual Timeout Strategy*):

1. **Idle Timeout (30 Menit / 1.800 Detik):**
   Jika pengguna tidak melakukan request apa pun selama 30 menit, sesi dianggap kedaluwarsa demi keamanan (misal pengguna meninggalkan komputer tanpa logout).
2. **Absolute Timeout (8 Jam / 28.800 Detik):**
   Sesi akan dipaksa berakhir setelah 8 jam sejak waktu awal login, meskipun pengguna terus aktif. Hal ini membatasi jendela eksploitasi jika ada token atau perangkat yang disusupi.

### Implementasi Evaluasi Waktu Eksplisit

Pemeriksaan waktu dilakukan secara eksplisit pada setiap request yang membutuhkan autentikasi:

```php
const SESSION_IDLE_TIMEOUT = 1800;       // 30 menit
const SESSION_ABSOLUTE_TIMEOUT = 28800;  // 8 jam
const SESSION_EXPIRED_MESSAGE = 'Sesi Anda telah berakhir. Silakan login kembali.';

function session_is_expired(int $loginAt, int $lastActivity, int $now): bool
{
    // Jika stempel waktu tidak valid, anggap sesi kedaluwarsa
    if ($loginAt <= 0 || $lastActivity <= 0) {
        return true;
    }
    // Cek Idle Timeout
    if (($now - $lastActivity) > SESSION_IDLE_TIMEOUT) {
        return true;
    }
    // Cek Absolute Timeout
    if (($now - $loginAt) > SESSION_ABSOLUTE_TIMEOUT) {
        return true;
    }

    return false;
}

function enforce_session_timeout(): void
{
    if (!is_logged_in()) {
        return;
    }

    $now = time();
    $loginAt = (int)($_SESSION['login_at'] ?? 0);
    $lastActivity = (int)($_SESSION['last_activity'] ?? 0);

    if (session_is_expired($loginAt, $lastActivity, $now)) {
        expire_session();
    }

    // Perbarui waktu aktivitas terakhir untuk request berjalan
    $_SESSION['last_activity'] = $now;
}

function expire_session(): void
{
    destroy_session_and_cookie();
    redirect(BASE_URL . '/auth/login.php?expired=1');
}
```

Ketika sesi berakhir akibat timeout, pengguna dialihkan ke halaman login dengan parameter `?expired=1` dan diberikan notifikasi peringatan.

---

## 4. Mekanisme Logout yang Benar

Proses logout pada `auth/logout.php` memastikan tidak ada jejak sesi yang tertinggal, baik di memori PHP, media penyimpanan server, maupun peramban klien:

```php
function destroy_session_and_cookie(): void
{
    // 1. Kosongkan array sesi di memori server
    $_SESSION = [];

    // 2. Kirim header Set-Cookie dengan masa berlaku lampau ke peramban
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

    // 3. Hancurkan file sesi fisik di server
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
```

### Pencegahan Akses Kembali via Tombol Back Peramban

Untuk mencegah pengguna melihat kembali data rahasia kurikulum menggunakan tombol *Back* setelah logout, seluruh halaman terproteksi menyertakan header HTTP anti-cache:

```php
function send_no_cache_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
```

Dengan header ini, browser tidak menyimpan salinan HTML di cache lokal (*back-forward cache*), dan setiap navigasi kembali akan memicu request baru ke server yang langsung ditolak oleh `require_login()`.

---

## 5. Pemeriksaan Akses Halaman (Auth Guard)

Setiap halaman modul OBE diawali dengan pemanggilan guard:

```php
require_once __DIR__ . '/../includes/auth.php';
```

Fungsi `require_login()` yang dijalankan di dalamnya:
1. Memeriksa batas kedaluwarsa sesi (`enforce_session_timeout()`).
2. Memeriksa keberadaan data pengguna login (`is_logged_in()`).
3. Mengarahkan pengunjung tanpa sesi ke `auth/login.php` dan menghentikan eksekusi skrip (`exit`).
4. Mengirimkan header anti-cache (`send_no_cache_headers()`).

Modul khusus administrator (seperti `users/index.php`) menambahkan pemeriksaan otorisasi:

```php
require_role(['admin']);
```

Jika pengguna login tidak memiliki peran `admin`, sistem merespons dengan kode status **HTTP 403 Forbidden** dan menampilkan tampilan `includes/forbidden.php`.
