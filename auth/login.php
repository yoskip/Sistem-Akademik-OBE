<?php
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    redirect(BASE_URL . '/dashboard/index.php');
}

$error = '';
$identity = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $identity = trim($_POST['identity'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identity === '' || $password === '') {
        $error = 'Username/email dan password wajib diisi.';
    } else {
        $stmt = db()->prepare(
            'SELECT u.*, r.nama AS role FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE (u.username = ? OR u.email = ?) LIMIT 1'
        );
        $stmt->execute([$identity, $identity]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Username/email atau password salah.';
        } elseif ((int)$user['aktif'] !== 1) {
            $error = 'Akun Anda sedang tidak aktif. Hubungi administrator.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'     => (int)$user['id'],
                'nama'   => $user['nama'],
                'username' => $user['username'],
                'email'  => $user['email'],
                'role'   => $user['role'],
            ];
            log_activity('login', 'auth', 'Pengguna berhasil masuk');
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
      <div class="mt-4 small text-muted">
        <div class="fw-semibold mb-1">Akun demo:</div>
        <div>Admin: <code>admin / admin123</code></div>
        <div>Kaprodi: <code>kaprodi / kaprodi123</code></div>
        <div>Dosen: <code>dosen / dosen123</code></div>
      </div>
    </div>
  </div>
</body>
</html>
