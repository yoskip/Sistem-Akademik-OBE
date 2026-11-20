<?php
require_once __DIR__ . '/../includes/functions.php';

send_no_cache_headers();

if (is_logged_in()) {
    redirect(BASE_URL . '/dashboard/index.php');
}

$error = isset($_GET['expired']) ? SESSION_EXPIRED_MESSAGE : '';
$identity = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $identity = trim($_POST['identity'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $identityHash = login_identity_hash($identity);
    $ip = client_ip();

    if ($identity === '' || !validate_login_identity($identity)) {
        http_response_code(422);
        $error = 'Format NPM, username, atau email tidak valid.';
    } elseif (is_login_blocked($identityHash, $ip)) {
        // Tolak sebelum query user/password_verify dijalankan.
        http_response_code(429);
        header('Retry-After: ' . (LOGIN_WINDOW_MINUTES * 60));
        $error = LOGIN_THROTTLED_MESSAGE;
        log_login_blocked($identityHash, $ip);
    } else {
        $user = false;
        if ($identity !== '' && $password !== '') {
            $stmt = db()->prepare(
                'SELECT u.*, r.nama AS role FROM users u
                 JOIN roles r ON r.id = u.role_id
                 WHERE (u.username = ? OR u.email = ?) LIMIT 1'
            );
            $stmt->execute([$identity, $identity]);
            $user = $stmt->fetch();
        }

        $verified = $user ? verify_and_upgrade_password(db(), $user, $password) : false;
        if (!$user) {
            // Samakan waktu respons agar akun tidak mudah ditebak.
            burn_password_verification($password);
        }

        $ldapAllowed = ($user && $verified && (int)$user['aktif'] === 1)
            ? obe_verify_ldap($identity, $password)
            : true;

        if (!$user || !$verified || !$ldapAllowed || (int)$user['aktif'] !== 1) {
            record_login_failure($identityHash, $ip);
            log_login_failure($identityHash, $ip);
            obe_login_failed_event(
                $identity,
                $ip,
                $ldapAllowed ? 'Kredensial tidak valid' : 'Verifier eksternal menolak autentikasi'
            );
            $error = LOGIN_FAILED_MESSAGE;
        } else {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'       => (int)$user['id'],
                'nama'     => $user['nama'],
                'username' => $user['username'],
                'npm_nidn' => $user['npm'] ?? $user['nidn'] ?? $user['username'],
                'role'     => $user['role'],
            ];
            $_SESSION['login_at'] = time();
            $_SESSION['last_activity'] = time();
            unset($_SESSION['csrf']);
            log_activity('login', 'auth', 'Pengguna berhasil masuk');
            obe_login_success_event($user);
            flash('success', 'Selamat datang, ' . $user['nama'] . '.');
            redirect(BASE_URL . '/dashboard/index.php');
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login &middot; <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="login-page">
  <div class="card login-card">
    <div class="login-brand">
      <div class="logo"><i class="bi bi-mortarboard-fill"></i></div>
      <h1 class="h4 fw-bold mb-1">Sistem Informasi Kurikulum OBE</h1>
      <p class="mb-0 small opacity-75"><?= e(APP_PRODI) ?></p>
    </div>
    <div class="login-body">
      <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 py-2">
          <i class="bi bi-exclamation-triangle-fill"></i> <?= e($error) ?>
        </div>
      <?php endif; ?>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label fw-semibold small">Email atau Username</label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
            <input type="text" class="form-control" name="identity" value="<?= e($identity) ?>" placeholder="admin / admin@yarsi.ac.id" required autofocus>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold small">Password</label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control" name="password" placeholder="Masukkan password" required>
          </div>
        </div>
        <button type="submit" class="btn btn-navy w-100 py-2 fw-semibold">
          <i class="bi bi-box-arrow-in-right me-1"></i> Login
        </button>
      </form>
    </div>
  </div>
</body>
</html>
