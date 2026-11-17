<?php
/**
 * Test 1 - Session ID Regeneration setelah login.
 * Jalankan: php tests/session_regeneration_test.php
 * Prasyarat: Apache + MySQL XAMPP aktif dan database db_obe tersedia.
 * Akun uji dibuat dan dihapus otomatis; tidak memakai akun asli.
 */
require_once __DIR__ . '/../includes/functions.php';

$base = rtrim(getenv('OBE_BASE_URL') ?: 'http://127.0.0.1/obe', '/');
$loginUrl = $base . '/auth/login.php';
$dashboardUrl = $base . '/dashboard/index.php';
$pdo = db();
$suffix = bin2hex(random_bytes(6));
$username = 'regen_test_' . $suffix;
$nama = 'Regen Test ' . $suffix;
$plain = 'Regen-' . bin2hex(random_bytes(12));
$userId = 0;
$cookieFile = tempnam(sys_get_temp_dir(), 'obe_cookie_');
$failures = 0;
$sessionName = session_name();

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

function session_cookie_value($ch, string $name): string
{
    foreach ((array)curl_getinfo($ch, CURLINFO_COOKIELIST) as $line) {
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) >= 7 && $parts[5] === $name) {
            $expiry = (int)$parts[4];
            if (($expiry > 0 && $expiry <= time()) || $parts[6] === 'deleted' || $parts[6] === '') {
                continue;
            }
            return $parts[6];
        }
    }
    return '';
}

try {
    $pdo->prepare('INSERT INTO users (nama, username, email, password, role_id, aktif) VALUES (?,?,?,?,3,1)')
        ->execute([$nama, $username, $username . '@example.invalid', hash_password($plain)]);
    $userId = (int)$pdo->lastInsertId();

    $ch = curl_init();
    [$code, $body, , $error] = http_request($ch, $loginUrl);
    $before = session_cookie_value($ch, $sessionName);
    check($code === 200, 'Halaman login dapat diakses (HTTP 200)');
    check($before !== '', 'Session ID sebelum login tercatat (tidak dicetak di log)');
    if ($code !== 200) {
        throw new RuntimeException('Halaman login tidak dapat diakses: ' . $error);
    }

    if (!preg_match('/name="csrf"\s+value="([a-f0-9]+)"/i', $body, $m)) {
        throw new RuntimeException('Token CSRF tidak ditemukan pada halaman login.');
    }

    [$code, , $location] = http_request($ch, $loginUrl, [
        'csrf'     => $m[1],
        'identity' => $username,
        'password' => $plain,
    ]);
    $after = session_cookie_value($ch, $sessionName);
    check($code === 302 && strpos($location, '/dashboard/index.php') !== false, 'Login berhasil dan diarahkan ke dashboard');
    check($after !== '', 'Session ID setelah login tercatat (tidak dicetak di log)');
    check($before !== '' && $after !== '' && $before !== $after, 'Session ID berubah setelah login (session_regenerate_id)');

    $oldCh = curl_init();
    curl_setopt($oldCh, CURLOPT_URL, $dashboardUrl);
    curl_setopt($oldCh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($oldCh, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($oldCh, CURLOPT_COOKIE, $sessionName . '=' . $before);
    curl_exec($oldCh);
    $oldCode = (int)curl_getinfo($oldCh, CURLINFO_RESPONSE_CODE);
    $oldLocation = (string)curl_getinfo($oldCh, CURLINFO_REDIRECT_URL);
    curl_close($oldCh);
    check($oldCode === 302 && strpos($oldLocation, '/auth/login.php') !== false, 'Session ID lama tidak dapat mengakses dashboard');

    [$newCode, $newBody] = http_request($ch, $dashboardUrl);
    check($newCode === 200 && strpos($newBody, $nama) !== false, 'Session baru tetap dapat mengakses dashboard');
} catch (Throwable $e) {
    check(false, 'Pengujian regeneration: ' . $e->getMessage());
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
