<?php
require_once __DIR__ . '/../config/database.php';

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_WINDOW_MINUTES = 15;
const LOGIN_FAILED_MESSAGE = 'Username atau password salah.';
const LOGIN_THROTTLED_MESSAGE = 'Terlalu banyak percobaan login. Silakan coba kembali beberapa saat lagi.';
const DUMMY_PASSWORD_HASH = '$2y$10$FlzwwjaWTtPVMO0eKmXJTuaoaj0V4iMO37a2WJZe1cBaCXMQ0ML/S';

// Idle timeout 30 menit dan absolute timeout 8 jam sejak login.
const SESSION_IDLE_TIMEOUT = 1800;
const SESSION_ABSOLUTE_TIMEOUT = 28800;
const SESSION_EXPIRED_MESSAGE = 'Sesi Anda telah berakhir. Silakan login kembali.';

function is_https_request(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/**
 * Secure cookie mengikuti environment:
 * - SESSION_COOKIE_SECURE=1 untuk produksi HTTPS
 * - SESSION_COOKIE_SECURE=0 untuk lokal HTTP
 * Bila tidak diset, nilai mengikuti skema request (HTTP=false, HTTPS=true).
 */
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

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (PHP_SAPI === 'cli') {
        $savePath = session_save_path();
        if ($savePath === '' || !@is_writable($savePath)) {
            @session_save_path(sys_get_temp_dir());
        }
    }

    $secure = session_cookie_secure();

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
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

start_secure_session();

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/** Cegah halaman sensitif ditampilkan kembali dari cache browser (tombol Back). */
function send_no_cache_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/** Hapus data session, hancurkan session server, dan hapus cookie di browser. */
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

/** Pemeriksaan murni agar idle timeout dan absolute timeout mudah diuji. */
function session_is_expired(int $loginAt, int $lastActivity, int $now): bool
{
    if ($loginAt <= 0 || $lastActivity <= 0) {
        return true;
    }
    if (($now - $lastActivity) > SESSION_IDLE_TIMEOUT) {
        return true;
    }
    if (($now - $loginAt) > SESSION_ABSOLUTE_TIMEOUT) {
        return true;
    }

    return false;
}

function expire_session(): void
{
    destroy_session_and_cookie();
    redirect(BASE_URL . '/auth/login.php?expired=1');
}

/** Dipanggil pada setiap request; perbarui waktu aktivitas bila session masih valid. */
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

    $_SESSION['last_activity'] = $now;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function current_role(): string
{
    return $_SESSION['user']['role'] ?? '';
}

function require_login(): void
{
    enforce_session_timeout();

    if (!is_logged_in()) {
        redirect(BASE_URL . '/auth/login.php');
    }

    send_no_cache_headers();
}

function require_role(array $roles): void
{
    require_login();
    if (!in_array(current_role(), $roles, true)) {
        http_response_code(403);
        include __DIR__ . '/forbidden.php';
        exit;
    }
}

/** Hak kelola data master kurikulum: admin + kaprodi */
function can_manage_master(): bool
{
    return in_array(current_role(), ['admin', 'kaprodi'], true);
}

/** Hak kelola CPMK / Sub-CPMK / penilaian: semua peran terautentikasi */
function can_manage_cpmk(): bool
{
    return in_array(current_role(), ['admin', 'kaprodi', 'dosen'], true);
}

function can_manage_users(): bool
{
    return current_role() === 'admin';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(419);
        die('Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');
    }
}

function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

function is_password_hash(string $value): bool
{
    return (int)(password_get_info($value)['algo'] ?? 0) !== 0;
}

/**
 * Verifikasi password sekaligus memigrasikan hash lama/plaintext ke bcrypt.
 * Password mentah tidak pernah disimpan atau dicatat.
 */
function verify_and_upgrade_password(PDO $pdo, array $user, string $password): bool
{
    $stored = (string)($user['password'] ?? '');

    if (is_password_hash($stored)) {
        if (!verify_password($password, $stored)) {
            return false;
        }
        if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([hash_password($password), (int)$user['id']]);
        }
        return true;
    }

    // Akun lama dengan password plaintext: cocokkan constant-time lalu hash ulang.
    if (!hash_equals($stored, $password)) {
        return false;
    }
    $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
        ->execute([hash_password($password), (int)$user['id']]);
    return true;
}

function burn_password_verification(string $password): void
{
    verify_password($password, DUMMY_PASSWORD_HASH);
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function login_identity_hash(string $identity): string
{
    return hash('sha256', strtolower(trim($identity)));
}

/** @return array{0:int,1:int} [percobaan per akun, percobaan per IP] */
function login_attempt_counts(string $identityHash, string $ip): array
{
    $cutoff = date('Y-m-d H:i:s', time() - (LOGIN_WINDOW_MINUTES * 60));
    $stmt = db()->prepare(
        'SELECT
            COALESCE(SUM(identity_hash = ?), 0) AS account_hits,
            COALESCE(SUM(ip = ?), 0) AS ip_hits
         FROM login_attempts
         WHERE success = 0 AND attempted_at >= ?'
    );
    $stmt->execute([$identityHash, $ip, $cutoff]);
    $row = $stmt->fetch() ?: ['account_hits' => 0, 'ip_hits' => 0];

    return [(int)$row['account_hits'], (int)$row['ip_hits']];
}

function is_login_blocked(string $identityHash, string $ip): bool
{
    [$accountHits, $ipHits] = login_attempt_counts($identityHash, $ip);

    return $accountHits >= LOGIN_MAX_ATTEMPTS || $ipHits >= LOGIN_MAX_ATTEMPTS;
}

function record_login_failure(string $identityHash, string $ip): void
{
    db()->prepare(
        'INSERT INTO login_attempts (identity_hash, ip, success, attempted_at) VALUES (?, ?, 0, ?)'
    )->execute([$identityHash, $ip, date('Y-m-d H:i:s')]);

    db()->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')
        ->execute([date('Y-m-d H:i:s', time() - 86400)]);
}

function log_login_failure(string $identityHash, string $ip): void
{
    db()->prepare(
        'INSERT INTO audit_logs (user_id, aksi, modul, keterangan, ip) VALUES (NULL, ?, ?, ?, ?)'
    )->execute(['login_failed', 'auth', 'Percobaan login gagal untuk identitas hash ' . $identityHash, $ip]);
}

function log_login_blocked(string $identityHash, string $ip): void
{
    db()->prepare(
        'INSERT INTO audit_logs (user_id, aksi, modul, keterangan, ip) VALUES (NULL, ?, ?, ?, ?)'
    )->execute(['login_blocked', 'auth', 'Rate limit tercapai untuk identitas hash ' . $identityHash, $ip]);
}

function log_activity(string $aksi, string $modul, string $keterangan = ''): void
{
    $user = current_user();
    $stmt = db()->prepare('INSERT INTO audit_logs (user_id, aksi, modul, keterangan, ip) VALUES (?,?,?,?,?)');
    $stmt->execute([
        $user['id'] ?? null,
        $aksi,
        $modul,
        $keterangan,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

/** Ambil nilai map kode -> id untuk tabel referensi */
function id_map(string $table, string $kodeCol): array
{
    $map = [];
    foreach (db()->query("SELECT id, $kodeCol FROM $table") as $row) {
        $map[$row[$kodeCol]] = (int)$row['id'];
    }
    return $map;
}

/** Hitung status kelengkapan dari jumlah bagian terisi */
function status_kelengkapan(int $ada): string
{
    if ($ada >= 8) return 'Lengkap';
    if ($ada <= 0) return 'Belum';
    return 'Sebagian';
}

/** Pagination sederhana; mengembalikan [limit, offset, total, halaman] */
function paginate(int $total, int $perPage = 10): array
{
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    return [$perPage, ($page - 1) * $perPage, $total, $page, $pages];
}

function render_pagination(int $page, int $pages): string
{
    if ($pages <= 1) return '';
    $base = strtok($_SERVER['REQUEST_URI'], '?');
    parse_str($_SERVER['QUERY_STRING'] ?? '', $q);
    $html = '<nav><ul class="pagination pagination-sm mb-0">';
    for ($i = 1; $i <= $pages; $i++) {
        $q['page'] = $i;
        $url = $base . '?' . http_build_query($q);
        $active = $i === $page ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . e($url) . '">' . $i . '</a></li>';
    }
    $html .= '</ul></nav>';
    return $html;
}

function badge_for_role(string $role): string
{
    return match ($role) {
        'admin'   => 'danger',
        'kaprodi' => 'primary',
        'dosen'   => 'success',
        default   => 'secondary',
    };
}
