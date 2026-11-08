<?php
require_once __DIR__ . '/functions.php';
$__u = current_user();
$__role = $__u['role'] ?? '';
$__title = $page_title ?? 'Dashboard';
$__subtitle = $page_subtitle ?? '';
$__ikm = $__u['nama'] ?? '';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($__title) ?> &middot; <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <div class="main-wrap">
    <nav class="topbar">
      <button class="btn btn-icon d-lg-none" id="sidebarToggle" type="button" aria-label="Menu"><i class="bi bi-list"></i></button>
      <div class="topbar-title">
        <h1><?= e($__title) ?></h1>
        <?php if ($__subtitle): ?><p><?= e($__subtitle) ?></p><?php endif; ?>
      </div>
      <div class="topbar-right">
        <span class="d-none d-md-inline text-muted small"><?= date('d M Y') ?></span>
        <div class="dropdown">
          <button class="btn user-chip dropdown-toggle" data-bs-toggle="dropdown" type="button">
            <span class="avatar sm"><?= e(strtoupper(substr($__ikm, 0, 1))) ?></span>
            <span class="d-none d-sm-inline"><?= e($__ikm) ?></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow">
            <li><span class="dropdown-item-text small text-muted">Masuk sebagai <b><?= e(ucfirst($__role)) ?></b></span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/auth/logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a></li>
          </ul>
        </div>
      </div>
    </nav>

    <main class="content">
      <?php foreach (get_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
          <?= e($f['message']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endforeach; ?>
