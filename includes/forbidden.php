<?php $u = current_user(); ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Akses Ditolak</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="card border-0 shadow-sm mx-auto" style="max-width:560px">
    <div class="card-body p-4 text-center">
      <div class="display-5 text-danger mb-2"><i class="bi bi-shield-lock"></i></div>
      <h4 class="fw-bold">403 - Akses Ditolak</h4>
      <p class="text-muted">Peran <b><?= e($u['role'] ?? '-') ?></b> tidak memiliki hak untuk membuka halaman ini.</p>
      <a class="btn btn-navy" href="<?= BASE_URL ?>/dashboard/index.php"><i class="bi bi-speedometer2"></i> Kembali ke Dashboard</a>
    </div>
  </div>
</div>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</body>
</html>
