<?php
/**
 * Pengujian tambahan - Login, session, role dashboard, dan logout.
 * Jalankan: php tests/auth_flow_test.php
 */
require_once __DIR__ . '/../includes/functions.php';

$base = rtrim(getenv('OBE_BASE_URL') ?: 'http://127.0.0.1/obe', '/');
$loginUrl = $base . '/auth/login.php';
$dashboardUrl = $base . '/dashboard/index.php';
$logoutUrl = $base . '/auth/logout.php';
$pdo = db();
$suffix = bin2hex(random_bytes(6));
$username = 'flow_test_' . $suffix;
$nama = 'Flow Test ' . $suffix;
$plain = 'Flow-' . bin2hex(random_bytes(12));
$userId = 0;
$cookieFile = tempnam(sys_get_temp_dir(), 'obe_cookie_');
$failures = 0;

function check(bool $ok, string $label): void
{
    global $failures;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

function http_request($ch, string $url, ?array $post = null): array
{
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $GLOBALS['cookieFile']);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $GLOBALS['cookieFile']);
    curl_setopt($ch, CURLOPT_POST, $post !== null);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $location = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    return [$code, (string)$body, $location, curl_error($ch)];
}

try {
    $pdo->prepare('INSERT INTO users (nama, username, email, password, role_id, aktif) VALUES (?,?,?,?,3,1)')
        ->execute([$nama, $username, $username . '@example.invalid', hash_password($plain)]);
    $userId = (int)$pdo->lastInsertId();

    $ch = curl_init();
    [$code, $body] = http_request($ch, $loginUrl);
    check($code === 200 && preg_match('/name="csrf"\s+value="([a-f0-9]+)"/i', $body, $m) === 1, 'Halaman login dan token CSRF tersedia');
    $csrf = $m[1] ?? '';

    [$code, $body, $location] = http_request($ch, $loginUrl, [
        'csrf'     => $csrf,
        'identity' => $username,
        'password' => $plain,
    ]);
    check($code === 302 && strpos($location, '/dashboard/index.php') !== false, 'Login berhasil dan diarahkan ke dashboard');

    [$code, $body] = http_request($ch, $dashboardUrl);
    check($code === 200 && strpos($body, $nama) !== false, 'Dashboard dapat diakses dan menampilkan pengguna login');

    [$code, $body, $location] = http_request($ch, $logoutUrl);
    check($code === 302 && strpos($location, '/auth/login.php') !== false, 'Logout mengarahkan kembali ke halaman login');

    [$code, $body, $location] = http_request($ch, $dashboardUrl);
    check($code === 302 && strpos($location, '/auth/login.php') !== false, 'Session lama tidak dapat mengakses dashboard setelah logout');
} catch (Throwable $e) {
    check(false, 'Pengujian alur autentikasi: ' . $e->getMessage());
} finally {
    if (isset($ch)) {
        curl_close($ch);
    }
    if ($userId > 0) {
        $pdo->prepare('DELETE FROM audit_logs WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    }
    if (is_file($cookieFile)) {
        unlink($cookieFile);
    }
}

exit($failures === 0 ? 0 : 1);
