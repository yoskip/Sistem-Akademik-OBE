<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
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
    if (!is_logged_in()) {
        redirect(BASE_URL . '/auth/login.php');
    }
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
