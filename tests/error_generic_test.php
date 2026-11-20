<?php
/**
 * Test 1 - Error umum dengan mock kegagalan verifier LDAP.
 * Jalankan: php tests/error_generic_test.php
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

echo "=== TEST 1: PESAN ERROR UMUM & ID KEJADIAN ===" . PHP_EOL;

$securityLog = obe_log_directory() . DIRECTORY_SEPARATOR . 'security.log';
$errorLog = obe_log_directory() . DIRECTORY_SEPARATOR . 'php_error.log';
$secOffset = is_file($securityLog) ? (int)filesize($securityLog) : 0;
$errOffset = is_file($errorLog) ? (int)filesize($errorLog) : 0;

$npm = '1402022001';
$password = 'DUMMY_PASSWORD_TEST_1';
$mockSecret = 'DUMMY_SENSITIVE_VALUE_123';

obe_register_ldap_verifier(static function (string $identity, string $passwordValue) use ($mockSecret): bool {
    throw new RuntimeException(
        'LDAP timeout: bind password=' . $mockSecret . ' token=DUMMY_TOKEN_VALUE SELECT * FROM users WHERE npm=1402022001'
    );
});

$incidentId = '';
ob_start();
try {
    obe_verify_ldap($npm, $password);
    check(false, 'Mock LDAP error seharusnya melempar exception');
} catch (Throwable $e) {
    $incidentId = obe_handle_exception($e, 'ldap_failure', true);
}
$body = (string)ob_get_clean();
obe_register_ldap_verifier(null);

check(strpos($body, OBE_GENERIC_ERROR_MESSAGE) !== false, 'Pengguna hanya melihat pesan error umum');
check((bool)preg_match('/ERR-[0-9]{8}-[A-F0-9]{6}/', $body, $match), 'Respons menampilkan ID kejadian berformat ERR-YYYYMMDD-XXXXXX');
$incidentId = $incidentId ?: ($match[0] ?? '');
check($incidentId !== '', 'ID kejadian berhasil dibuat');

foreach ([
    'LDAP timeout', $mockSecret, 'DUMMY_TOKEN_VALUE', 'SELECT * FROM', '1402022001',
    'Stack trace', '#0 ', 'PDOException',
] as $leak) {
    check(strpos($body, $leak) === false, 'Respons tidak membocorkan: ' . $leak);
}

$secTail = tail_from($securityLog, $secOffset);
$errTail = tail_from($errorLog, $errOffset);

check($incidentId !== '' && strpos($secTail, $incidentId) !== false, 'ID kejadian yang sama tercatat di security.log');
check($incidentId !== '' && strpos($errTail, $incidentId) !== false, 'ID kejadian yang sama tercatat di php_error.log');
check(strpos($secTail, $npm) === false, 'NPM penuh tidak dicatat (hanya bentuk samaran/hash)');
check(strpos($secTail, $password) === false, 'Password pengguna tidak dicatat');
check(strpos($secTail, 'DUMMY_TOKEN_VALUE') === false, 'Token tidak dicatat di security.log');
check(strpos($errTail, 'DUMMY_TOKEN_VALUE') === false, 'Token tidak dicatat di php_error.log');
check(strpos($errTail, 'SELECT * FROM') === false, 'Query SQL tidak dicatat di log internal');
check(empty($_SESSION['user']), 'Tidak ada session autentikasi yang diberikan saat error');

echo "ID kejadian aktual: " . $incidentId . PHP_EOL;
echo "=== TEST 1 SELESAI ===" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
