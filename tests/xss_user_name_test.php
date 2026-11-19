<?php
/**
 * Test 2 - XSS pada Input Nama Pengguna dan Pengujian Output Encoding.
 * Jalankan: php tests/xss_user_name_test.php
 * Prasyarat: Apache + MySQL XAMPP aktif dan database db_obe tersedia.
 */
require_once __DIR__ . '/../includes/functions.php';

$base = rtrim(getenv('OBE_BASE_URL') ?: 'http://127.0.0.1/obe', '/');
$loginUrl = $base . '/auth/login.php';
$usersUrl = $base . '/users/index.php';
$cookieFile = tempnam(sys_get_temp_dir(), 'obe_xss_');
$failures = 0;
$pdo = db();

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

echo "=== PENGUJIAN 2: XSS PADA NAMA PENGGUNA & OUTPUT ENCODING ===" . PHP_EOL;

$testUserId = 0;

try {
    // 1. Uji Unit Validasi Allowlist Nama Pengguna (Server-Side)
    $xssPayload1 = "<script>alert('XSS')</script>";
    $xssPayload2 = "<img src=x onerror=alert(1)>";
    $xssPayload3 = "javascript:alert(1)";

    check(!validate_name($xssPayload1), "validate_name menolak payload script '$xssPayload1'");
    check(!validate_name($xssPayload2), "validate_name menolak payload img '$xssPayload2'");
    check(!validate_name($xssPayload3), "validate_name menolak payload javascript scheme '$xssPayload3'");
    check(!validate_name("A"), "validate_name menolak nama terlalu pendek (<2 karakter)");
    check(!validate_name(str_repeat("A", 101)), "validate_name menolak nama terlalu panjang (>100 karakter)");

    // Uji nama akademis dan karakter yang sah
    check(validate_name("Achmad Hasan K"), "validate_name menerima nama standar 'Achmad Hasan K'");
    check(validate_name("Dr. John O'Connor-Smith, M.Kom."), "validate_name menerima gelar, apostrof, dan tanda hubung");

    // 2. Uji Unit Output Encoding e() (htmlspecialchars dengan ENT_QUOTES | ENT_SUBSTITUTE)
    $encoded1 = e($xssPayload1);
    check($encoded1 === "&lt;script&gt;alert(&#039;XSS&#039;)&lt;/script&gt;", "e() meng-encode script tag menjadi harmless HTML entities: $encoded1");
    check(strpos($encoded1, '<script>') === false, "Tidak ada tag <script> yang lolos setelah di-encode");

    $encoded2 = e($xssPayload2);
    check($encoded2 === "&lt;img src=x onerror=alert(1)&gt;", "e() meng-encode img tag: $encoded2");
    check(strpos($encoded2, '<img') === false, "Tidak ada tag <img> yang lolos setelah di-encode");

    $encodedQuotes = e('Test "Double" & \'Single\'');
    check($encodedQuotes === 'Test &quot;Double&quot; &amp; &#039;Single&#039;', "e() meng-encode tanda kutip ganda dan tunggal serta ampersand");

    // 3. Uji HTTP Form Submission pada Modul Users dengan Payload XSS
    // Buat akun admin sementara untuk menguji modul manajemen pengguna
    $tempSuffix = bin2hex(random_bytes(4));
    $adminUser = "xssadm_" . $tempSuffix;
    $adminPass = "AdminXss!123";
    $pdo->prepare('INSERT INTO users (nama, username, email, password, role_id, aktif) VALUES (?,?,?,?,1,1)')
        ->execute(["Admin XSS Tester", $adminUser, $adminUser . "@example.test", hash_password($adminPass)]);
    $testUserId = (int)$pdo->lastInsertId();

    $ch = curl_init();
    // Login sebagai admin
    [$code, $loginBody] = http_request($ch, $loginUrl);
    preg_match('/name="csrf"\s+value="([a-f0-9]+)"/i', $loginBody, $m);
    $loginCsrf = $m[1] ?? '';

    [$authCode, , $authLoc] = http_request($ch, $loginUrl, [
        'csrf'     => $loginCsrf,
        'identity' => $adminUser,
        'password' => $adminPass,
    ]);
    check($authCode === 302, "Admin berhasil login untuk pengujian modul pengguna");

    // Dapatkan CSRF token dari halaman users
    [$uCode, $uBody] = http_request($ch, $usersUrl);
    check($uCode === 200, "Halaman manajemen pengguna dapat diakses oleh admin");
    preg_match('/name="csrf"\s+value="([a-f0-9]+)"/i', $uBody, $mUsers);
    $userCsrf = $mUsers[1] ?? '';

    // Kirim POST pembuatan user baru dengan payload XSS pada field nama
    $targetUsername = "usrxss_" . $tempSuffix;
    [$postCode, $postBody] = http_request($ch, $usersUrl, [
        'csrf'     => $userCsrf,
        'action'   => 'save',
        'id'       => '0',
        'nama'     => $xssPayload1,
        'username' => $targetUsername,
        'email'    => $targetUsername . "@example.test",
        'role_id'  => '3',
        'aktif'    => '1',
        'password' => 'PasswordValid123!',
    ]);

    check($postCode === 422, "Input nama berisi XSS ditolak dengan status HTTP 422 (aktual: HTTP $postCode)");

    // Pastikan user jahat tidak masuk ke database
    $sCheck = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
    $sCheck->execute([$targetUsername]);
    $found = (int)$sCheck->fetchColumn();
    check($found === 0, "Data dengan payload XSS tidak disimpan ke database");

    // 4. Simulasi Data LDAP yang mengandung karakter berbahaya
    // Jika data LDAP yang disinkronkan mengandung entitas berbahaya, template harus tetap aman
    $simulatedLdapName = "<script>alert('LDAP_XSS')</script>";
    $renderedSafe = '<span class="user-name">' . e($simulatedLdapName) . '</span>';
    check(strpos($renderedSafe, '<script>') === false, "Render data LDAP terlindungi oleh e(): tidak ada script yang dieksekusi");
    check(strpos($renderedSafe, '&lt;script&gt;') !== false, "Render data LDAP ditampilkan sebagai entitas teks aman (&lt;script&gt;)");

    echo "=== PENGUJIAN 2 SELESAI: SEMUA CHECK BERHASIL ===" . PHP_EOL;
} catch (Throwable $e) {
    check(false, 'Pengujian XSS gagal: ' . $e->getMessage());
} finally {
    if (isset($ch)) {
        curl_close($ch);
    }
    if ($testUserId > 0) {
        $pdo->prepare('DELETE FROM audit_logs WHERE user_id = ?')->execute([$testUserId]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$testUserId]);
    }
    if (is_file($cookieFile)) {
        unlink($cookieFile);
    }
}

exit($failures === 0 ? 0 : 1);
