<?php
/**
 * Test 2 - Login gagal dengan mock LDAP yang menolak autentikasi.
 * Jalankan: php tests/login_ldap_fail_test.php
 */
require_once __DIR__ . '/../includes/functions.php';

$failures = 0;
function check(bool $ok, string $label): void
{
    global $failures;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

function tail_from(string $file, int $offset): string
{
    if (!is_file($file)) {
        return '';
    }
    clearstatcache(true, $file);
    $handle = @fopen($file, 'rb');
    if ($handle === false) {
        return '';
    }
    fseek($handle, $offset);
    $data = stream_get_contents($handle);
    fclose($handle);
    return (string)$data;
}

echo "=== TEST 2: LOGIN GAGAL VIA MOCK LDAP ===" . PHP_EOL;

$securityLog = obe_log_directory() . DIRECTORY_SEPARATOR . 'security.log';
$offset = is_file($securityLog) ? (int)filesize($securityLog) : 0;

$npm = '1402022002';
$password = 'DUMMY_PASSWORD_TEST_2';
$ip = '203.0.113.10';

unset($_SESSION['user']);
obe_register_ldap_verifier(static fn(string $identity, string $passwordValue): bool => false);

$allowed = obe_verify_ldap($npm, $password);
obe_login_failed_event($npm, $ip, 'Verifier eksternal menolak autentikasi');

obe_register_ldap_verifier(null);
$tail = tail_from($securityLog, $offset);

check($allowed === false, 'Mock LDAP yang menolak tidak mengizinkan autentikasi');
check(strpos($tail, '"action":"login"') !== false && strpos($tail, '"result":"failed"') !== false, 'Event login gagal tercatat di security.log');
check(strpos($tail, '"action":"ldap_failure"') !== false && strpos($tail, '"result":"failed"') !== false, 'Event kegagalan LDAP tercatat');
check(strpos($tail, '"npm_masked":"1402***002"') !== false, 'NPM tercatat dalam bentuk samaran');
check(strpos($tail, '"identity_hash":"' . hash('sha256', $npm) . '"') !== false, 'Hash identitas tercatat untuk korelasi');
check(strpos($tail, '"ip":"' . $ip . '"') !== false, 'IP pengguna tercatat');
check(strpos($tail, $npm) === false, 'NPM penuh tidak ditulis ke log');
check(strpos($tail, $password) === false, 'Password tidak pernah ditulis ke log');
check(strpos($tail, '"password"') === false && strpos($tail, '"token"') === false, 'Tidak ada field password/token di entri log');
check(empty($_SESSION['user']), 'Tidak ada session autentikasi setelah login gagal');

echo "=== TEST 2 SELESAI ===" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
