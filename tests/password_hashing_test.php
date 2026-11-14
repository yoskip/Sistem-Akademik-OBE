<?php
/**
 * Pengujian 1 - Password Hashing & Migrasi Password Lama.
 * Jalankan: php tests/password_hashing_test.php
 */
require_once __DIR__ . '/../includes/functions.php';

$pdo = db();
$suffix = bin2hex(random_bytes(6));
$plain = 'P@ssw0rd-' . bin2hex(random_bytes(12));
$legacy = 'Legacy-' . bin2hex(random_bytes(12));
$userIds = [];
$failures = 0;

function check(bool $ok, string $label): void
{
    global $failures;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

try {
    $insert = $pdo->prepare(
        'INSERT INTO users (nama, username, email, password, role_id, aktif) VALUES (?,?,?,?,3,1)'
    );

    $insert->execute(['Test Hash', 'test_hash_' . $suffix, 'test_hash_' . $suffix . '@example.invalid', hash_password($plain)]);
    $userIds[] = (int)$pdo->lastInsertId();

    $insert->execute(['Test Legacy', 'test_legacy_' . $suffix, 'test_legacy_' . $suffix . '@example.invalid', $legacy]);
    $legacyId = (int)$pdo->lastInsertId();
    $userIds[] = $legacyId;

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userIds[0]]);
    $hashedUser = $stmt->fetch();

    check($hashedUser['password'] !== $plain, 'Password tidak disimpan sebagai plaintext');
    check(is_password_hash($hashedUser['password']), 'Password tersimpan sebagai hash yang dikenali PHP');
    check(verify_password($plain, $hashedUser['password']), 'Password asli berhasil diverifikasi dengan password_verify()');
    check(!verify_password($plain . 'x', $hashedUser['password']), 'Password salah ditolak');

    $stmt->execute([$legacyId]);
    $legacyUser = $stmt->fetch();
    check(!is_password_hash($legacyUser['password']), 'Akun lama terdeteksi masih plaintext sebelum migrasi');
    check(verify_and_upgrade_password($pdo, $legacyUser, $legacy), 'Login akun lama dengan password benar diterima');

    $stmt->execute([$legacyId]);
    $migrated = $stmt->fetch();
    check($migrated['password'] !== $legacy, 'Password lama diubah menjadi hash setelah login');
    check(is_password_hash($migrated['password']), 'Hash hasil migrasi dikenali PHP');
    check(verify_password($legacy, $migrated['password']), 'Hash hasil migrasi dapat diverifikasi');

    $logStmt = $pdo->prepare('SELECT COUNT(*) FROM audit_logs WHERE keterangan LIKE ? OR keterangan LIKE ?');
    $logStmt->execute(['%' . $plain . '%', '%' . $legacy . '%']);
    check((int)$logStmt->fetchColumn() === 0, 'Password tidak muncul di audit log');
} finally {
    if ($userIds) {
        $in = implode(',', array_fill(0, count($userIds), '?'));
        $pdo->prepare("DELETE FROM users WHERE id IN ($in)")->execute($userIds);
    }
}

exit($failures === 0 ? 0 : 1);
