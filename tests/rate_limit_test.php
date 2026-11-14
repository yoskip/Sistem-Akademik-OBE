<?php
/**
 * Pengujian 2 - Rate Limiting Login (HTTP 429).
 * Jalankan setelah Apache + MySQL XAMPP aktif:
 *   php tests/rate_limit_test.php
 * URL default: http://127.0.0.1/obe
 * Ubah dengan env OBE_BASE_URL bila perlu.
 */
require_once __DIR__ . '/../includes/functions.php';

$base = rtrim(getenv('OBE_BASE_URL') ?: 'http://127.0.0.1/obe', '/');
$loginUrl = $base . '/auth/login.php';
$pdo = db();
$suffix = bin2hex(random_bytes(8));
$identity = 'security_test_' . $suffix . '@example.invalid';
$identityHash = login_identity_hash($identity);
$cookieFile = tempnam(sys_get_temp_dir(), 'obe_cookie_');
$failures = 0;
$attemptCount = 0;

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
    return [$code, (string)$body, curl_error($ch)];
}

function cleanup(PDO $pdo, string $identityHash): void
{
    $pdo->prepare('DELETE FROM login_attempts WHERE identity_hash = ?')->execute([$identityHash]);
    $pdo->prepare("DELETE FROM audit_logs WHERE keterangan LIKE ? AND aksi IN ('login_failed','login_blocked')")
        ->execute(['%' . $identityHash . '%']);
}

try {
    $ch = curl_init();
    [$getCode, $getBody, $curlError] = http_request($ch, $loginUrl);
    check($getCode === 200, 'Halaman login dapat diakses (HTTP 200)');
    if ($getCode !== 200) {
        echo "Info: {$curlError}\n";
        throw new RuntimeException('Halaman login tidak dapat diakses.');
    }

    if (!preg_match('/name="csrf"\s+value="([a-f0-9]+)"/i', $getBody, $m)) {
        throw new RuntimeException('Token CSRF tidak ditemukan pada halaman login.');
    }
    $csrf = $m[1];
    $wrongPassword = 'wrong-' . bin2hex(random_bytes(12));

    for ($i = 1; $i <= LOGIN_MAX_ATTEMPTS; $i++) {
        [$code, $body] = http_request($ch, $loginUrl, [
            'csrf'     => $csrf,
            'identity' => $identity,
            'password' => $wrongPassword,
        ]);
        check($code === 200, "Percobaan gagal ke-{$i} masih diproses (HTTP 200)");
        check(strpos($body, LOGIN_FAILED_MESSAGE) !== false, "Percobaan gagal ke-{$i} memakai pesan seragam");
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE identity_hash = ?');
    $stmt->execute([$identityHash]);
    $attemptCount = (int)$stmt->fetchColumn();
    check($attemptCount === LOGIN_MAX_ATTEMPTS, 'Tercatat tepat 5 percobaan gagal');

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM audit_logs WHERE aksi = ? AND keterangan LIKE ?');
    $stmt->execute(['login_failed', '%' . $identityHash . '%']);
    $failedLogsBefore = (int)$stmt->fetchColumn();

    [$blockedCode, $blockedBody] = http_request($ch, $loginUrl, [
        'csrf'     => $csrf,
        'identity' => $identity,
        'password' => $wrongPassword,
    ]);
    check($blockedCode === 429, 'Percobaan ke-6 ditolak dengan HTTP 429 Too Many Requests');
    check(strpos($blockedBody, LOGIN_THROTTLED_MESSAGE) !== false, 'Percobaan ke-6 memakai pesan batas percobaan');

    $stmt->execute(['login_failed', '%' . $identityHash . '%']);
    $failedLogsAfter = (int)$stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE identity_hash = ?');
    $stmt->execute([$identityHash]);
    $attemptsAfter = (int)$stmt->fetchColumn();

    check($attemptsAfter === LOGIN_MAX_ATTEMPTS, 'Percobaan ke-6 tidak menambah catatan verifikasi password');
    check($failedLogsAfter === $failedLogsBefore, 'Password tidak diverifikasi pada percobaan yang dibatasi');

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM audit_logs WHERE aksi = ? AND keterangan LIKE ?');
    $stmt->execute(['login_blocked', '%' . $identityHash . '%']);
    check((int)$stmt->fetchColumn() >= 1, 'Percobaan yang diblokir tercatat aman di audit log');
} catch (Throwable $e) {
    check(false, 'Pengujian rate limiting: ' . $e->getMessage());
} finally {
    if (isset($ch)) {
        curl_close($ch);
    }
    cleanup($pdo, $identityHash);
    if (is_file($cookieFile)) {
        unlink($cookieFile);
    }
}

exit($failures === 0 ? 0 : 1);
