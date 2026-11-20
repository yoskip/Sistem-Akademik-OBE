<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ldap_guard.php';

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
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * =====================================================================
 * FUNGSI VALIDASI INPUT (ALLOWLIST & SERVER-SIDE VALIDATION)
 * =====================================================================
 */

/** Validasi NPM: string tepat 10 digit angka (0-9) */
function validate_npm(mixed $npm): bool
{
    if (!is_string($npm) && !is_numeric($npm)) {
        return false;
    }
    return (bool)preg_match('/^[0-9]{10}$/', (string)$npm);
}

/** Validasi nama pengguna: string 2–100 karakter, huruf, spasi, tanda hubung, apostrof, titik, koma (gelar akademis) */
function validate_name(mixed $name, int $min = 2, int $max = 100): bool
{
    if (!is_string($name)) {
        return false;
    }
    $trimmed = trim($name);
    $len = mb_strlen($trimmed, 'UTF-8');
    if ($len < $min || $len > $max) {
        return false;
    }
    return (bool)preg_match('/^[\p{L}\s\'\-\.,]+$/u', $trimmed);
}

/** Validasi ID data: integer positif minimal 1 */
function validate_id(mixed $id): bool
{
    if (is_int($id)) {
        return $id >= 1;
    }
    if (is_string($id) && ctype_digit($id)) {
        return (int)$id >= 1 && (string)(int)$id === $id;
    }
    return false;
}

/** Validasi kode PL / CPL / CPMK / Sub-CPMK / BK / MK: string 2–30 karakter, huruf, angka, tanda hubung, underscore */
function validate_code(mixed $code, int $min = 2, int $max = 30): bool
{
    if (!is_string($code)) {
        return false;
    }
    $trimmed = trim($code);
    $len = strlen($trimmed);
    if ($len < $min || $len > $max) {
        return false;
    }
    return (bool)preg_match('/^[a-zA-Z0-9_\-]+$/', $trimmed);
}

/** Validasi deskripsi capaian: string 1–1000 karakter, tidak boleh kosong */
function validate_description(mixed $desc, int $min = 1, int $max = 1000): bool
{
    if (!is_string($desc)) {
        return false;
    }
    $trimmed = trim($desc);
    $len = mb_strlen($trimmed, 'UTF-8');
    return $len >= $min && $len <= $max;
}

/** Validasi username: NPM 10 digit ATAU string alfanumerik 3–50 karakter */
function validate_username(mixed $username): bool
{
    if (!is_string($username)) {
        return false;
    }
    $trimmed = trim($username);
    if (validate_npm($trimmed)) {
        return true;
    }
    return (bool)preg_match('/^[a-zA-Z0-9_\-]{3,50}$/', $trimmed);
}

/** Validasi identitas login: NPM (10 digit), username (3-50 char), atau email valid */
function validate_login_identity(mixed $identity): bool
{
    if (!is_string($identity)) {
        return false;
    }
    $trimmed = trim($identity);
    if ($trimmed === '') {
        return false;
    }
    // Tolak karakter berbahaya SQL injection / whitespace internal
    if (preg_match('/[\s\'"=\-;]/', $trimmed)) {
        return false;
    }
    if (validate_npm($trimmed)) {
        return true;
    }
    if (validate_username($trimmed)) {
        return true;
    }
    return filter_var($trimmed, FILTER_VALIDATE_EMAIL) !== false;
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

function deny_access(): void
{
    $user = current_user();
    security_event('access_denied', 'denied', [
        'user_id' => $user['id'] ?? null,
        'identity' => $user['npm_nidn'] ?? $user['username'] ?? '',
        'role' => $user['role'] ?? '',
        'detail' => 'Hak akses tidak memadai untuk endpoint ini',
    ]);
    http_response_code(403);
    include __DIR__ . '/forbidden.php';
    exit;
}

function require_role(array $roles): void
{
    require_login();
    if (!in_array(current_role(), $roles, true)) {
        deny_access();
    }
}

function obe_login_success_event(array $user): void
{
    security_event('login', 'success', [
        'user_id'  => (int)($user['id'] ?? 0),
        'identity' => $user['npm'] ?? $user['nidn'] ?? $user['username'] ?? '',
        'role'     => (string)($user['role'] ?? ''),
    ]);
}

function obe_login_failed_event(string $identity, string $ip, string $reason = 'Kredensial tidak valid'): void
{
    security_event('login', 'failed', [
        'identity' => $identity,
        'ip'       => $ip,
        'detail'   => $reason,
    ]);
}

function obe_logout_event(): void
{
    $user = current_user();
    security_event('logout', 'success', [
        'user_id'  => (int)($user['id'] ?? 0),
        'identity' => $user['npm_nidn'] ?? $user['username'] ?? '',
        'role'     => (string)($user['role'] ?? ''),
    ]);
}

function obe_role_change_event(int $userId, string $identity, string $from, string $to): void
{
    security_event('role_change', 'success', [
        'user_id'   => $userId,
        'identity'  => $identity,
        'from_role' => $from,
        'to_role'   => $to,
    ]);
}

function obe_mask_display_text(string $text): string
{
    $text = preg_replace('/\b[a-f0-9]{64}\b/i', '[hash]', $text) ?? $text;
    $text = preg_replace('/\b[0-9]{10}\b/', '****', $text) ?? $text;
    return $text;
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

function reject_csrf(): void
{
    security_event('csrf_rejected', 'denied', ['detail' => 'Token CSRF tidak valid']);
    http_response_code(419);
    echo 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.';
    exit;
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        reject_csrf();
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

/** Ambil nilai map kode -> id untuk tabel referensi dengan proteksi allowlist */
function id_map(string $table, string $kodeCol): array
{
    $allowed = [
        'cpl'            => 'kode_cpl',
        'profil_lulusan' => 'kode_pl',
        'bahan_kajian'   => 'kode_bk',
        'mata_kuliah'    => 'kode_mk',
        'cpmk'           => 'kode_cpmk',
    ];
    if (!isset($allowed[$table]) || $allowed[$table] !== $kodeCol) {
        throw new InvalidArgumentException("Tabel atau kolom referensi tidak diizinkan: $table.$kodeCol");
    }

    $map = [];
    $stmt = db()->prepare("SELECT id, `$kodeCol` FROM `$table`");
    $stmt->execute();
    foreach ($stmt->fetchAll() as $row) {
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
