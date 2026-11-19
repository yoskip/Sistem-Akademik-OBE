<?php
/**
 * Test 1 - SQL Injection pada Input NPM / Identity Login.
 * Jalankan: php tests/sql_injection_npm_test.php
 * Prasyarat: Apache + MySQL XAMPP aktif dan database db_obe tersedia.
 */
require_once __DIR__ . '/../includes/functions.php';

$base = rtrim(getenv('OBE_BASE_URL') ?: 'http://127.0.0.1/obe', '/');
$loginUrl = $base . '/auth/login.php';
$cookieFile = tempnam(sys_get_temp_dir(), 'obe_sqli_');
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

echo "=== PENGUJIAN 1: SQL INJECTION PADA NPM / IDENTITY LOGIN ===" . PHP_EOL;

try {
    // 1. Uji unit allowlist validasi server-side
    $sqliPayload = "' OR 1=1--";
    check(!validate_npm($sqliPayload), "validate_npm menolak payload SQLi '$sqliPayload'");
    check(!validate_login_identity($sqliPayload), "validate_login_identity menolak payload SQLi '$sqliPayload'");
    check(validate_npm("1402022001"), "validate_npm menerima NPM valid 10 digit (1402022001)");
    check(!validate_npm("140202200"), "validate_npm menolak NPM kurang dari 10 digit");
    check(!validate_npm("140202200199"), "validate_npm menolak NPM lebih dari 10 digit");
    check(!validate_npm("140202200a"), "validate_npm menolak NPM dengan huruf");

    // 2. Uji HTTP endpoint /auth/login.php dengan payload SQLi
    $ch = curl_init();
    [$code, $body, , $error] = http_request($ch, $loginUrl);
    check($code === 200, 'Halaman login dapat diakses via HTTP (HTTP 200)');
    if ($code !== 200) {
        throw new RuntimeException('Gagal mengakses login: ' . $error);
    }

    if (!preg_match('/name="csrf"\s+value="([a-f0-9]+)"/i', $body, $m)) {
        throw new RuntimeException('Token CSRF tidak ditemukan.');
    }
    $csrf = $m[1];

    [$postCode, $postBody, $redirectLoc] = http_request($ch, $loginUrl, [
        'csrf'     => $csrf,
        'identity' => $sqliPayload,
        'password' => 'secret123',
    ]);

    // Verifikasi penolakan HTTP 422
    check($postCode === 422, "Login dengan payload SQLi menghasilkan status HTTP 422 (aktual: HTTP $postCode)");
    check(strpos($postBody, 'Format NPM, username, atau email tidak valid.') !== false, 'Pesan validasi non-leaking ditampilkan ke pengguna');

    // Verifikasi tidak ada error database atau SQL yang bocor
    $hasSqlLeak = (
        stripos($postBody, 'SQLSTATE') !== false ||
        stripos($postBody, 'syntax error') !== false ||
        stripos($postBody, 'mysql_') !== false ||
        stripos($postBody, 'PDOException') !== false ||
        stripos($postBody, 'SELECT * FROM') !== false
    );
    check(!$hasSqlLeak, 'Tidak ada query SQL, PDOException, atau pesan error database yang bocor di respons');

    // 3. Uji Prepared Statement Database secara langsung dengan payload SQLi
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, nama, username FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$sqliPayload, $sqliPayload]);
    $results = $stmt->fetchAll();
    check(count($results) === 0, 'Query prepared statement mengeksekusi payload sebagai string literal (0 baris ditemukan, tidak bypass)');

    echo "=== PENGUJIAN 1 SELESAI: SEMUA CHECK BERHASIL ===" . PHP_EOL;
} catch (Throwable $e) {
    check(false, 'Pengujian SQL Injection gagal: ' . $e->getMessage());
} finally {
    if (isset($ch)) {
        curl_close($ch);
    }
    if (is_file($cookieFile)) {
        unlink($cookieFile);
    }
}

exit($failures === 0 ? 0 : 1);
