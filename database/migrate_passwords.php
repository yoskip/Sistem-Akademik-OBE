<?php
/**
 * Migrasi password lama ke hash bcrypt (PASSWORD_DEFAULT).
 * Jalankan dari terminal:
 *   php database/migrate_passwords.php --dry-run
 *   php database/migrate_passwords.php
 *
 * Script ini tidak menghapus akun dan tidak pernah menampilkan password.
 * Akun dengan hash MD5/SHA1 tidak dapat dipulihkan otomatis; akun tersebut
 * harus melakukan reset password (lihat laporan).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Script ini hanya boleh dijalankan dari CLI.\n");
}

require_once __DIR__ . '/../config/database.php';

$dryRun = in_array('--dry-run', $argv, true);
$pdo = db();

$select = $pdo->query('SELECT id, username, password FROM users ORDER BY id');
$update = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');

$migrated = 0;
$skipped = 0;
$unsupported = 0;

foreach ($select as $user) {
    $stored = (string)$user['password'];
    $info = password_get_info($stored);
    $algo = (int)($info['algo'] ?? 0);

    if ($algo !== 0) {
        // Hash yang valid tidak bisa di-rehash tanpa password asli.
        // Rehash otomatis dilakukan saat login berhasil (jika diperlukan).
        $skipped++;
        continue;
    }

    // Deteksi hash lama MD5/SHA1 agar tidak keliru di-hash sebagai plaintext.
    if (preg_match('/^[a-f0-9]{32}$/i', $stored) || preg_match('/^[a-f0-9]{40}$/i', $stored)) {
        $unsupported++;
        echo "PERLU RESET: {$user['username']} memakai hash MD5/SHA1.\n";
        continue;
    }

    if ($stored === '') {
        $unsupported++;
        echo "PERLU RESET: {$user['username']} memiliki password kosong.\n";
        continue;
    }

    if (!$dryRun) {
        $update->execute([password_hash($stored, PASSWORD_DEFAULT), (int)$user['id']]);
    }
    $migrated++;
}

echo ($dryRun ? "[DRY RUN] " : '') . "Selesai.\n";
echo "Plaintext dimigrasikan : {$migrated}\n";
echo "Sudah aman (dilewati)  : {$skipped}\n";
echo "Perlu reset manual     : {$unsupported}\n";
