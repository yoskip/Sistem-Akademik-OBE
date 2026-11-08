<?php
$u = current_user();
$uri = $_SERVER['REQUEST_URI'] ?? '';
$act = function (string $path) use ($uri) {
    return strpos($uri, BASE_URL . '/' . $path) === 0 ? ' active' : '';
};
$role = $u['role'] ?? '';
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
    <div>
      <div class="brand-title">SI Kurikulum</div>
      <div class="brand-sub">OBE &middot; Teknik Informatika</div>
    </div>
  </div>

  <div class="sidebar-user">
    <div class="avatar"><?= e(strtoupper(substr($u['nama'] ?? 'U', 0, 1))) ?></div>
    <div class="meta">
      <div class="name"><?= e($u['nama'] ?? '') ?></div>
      <span class="badge bg-<?= badge_for_role($role) ?> badge-role"><?= e(ucfirst($role)) ?></span>
    </div>
  </div>

  <nav class="sidebar-nav">
    <a class="nav-item<?= $act('dashboard/') ?>" href="<?= BASE_URL ?>/dashboard/index.php">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    <div class="nav-section">Data Kurikulum</div>
    <a class="nav-item<?= $act('profil_lulusan/') ?>" href="<?= BASE_URL ?>/profil_lulusan/index.php"><i class="bi bi-person-badge"></i> Profil Lulusan</a>
    <a class="nav-item<?= $act('cpl/') ?>" href="<?= BASE_URL ?>/cpl/index.php"><i class="bi bi-bullseye"></i> CPL Program Studi</a>
    <a class="nav-item<?= $act('bahan_kajian/') ?>" href="<?= BASE_URL ?>/bahan_kajian/index.php"><i class="bi bi-journal-bookmark"></i> Bahan Kajian</a>
    <a class="nav-item<?= $act('mata_kuliah/') ?>" href="<?= BASE_URL ?>/mata_kuliah/index.php"><i class="bi bi-journal-text"></i> Mata Kuliah</a>

    <div class="nav-section">Pemetaan Kurikulum</div>
    <a class="nav-item<?= $act('pemetaan/cpl_pl') ?>" href="<?= BASE_URL ?>/pemetaan/cpl_pl.php"><i class="bi bi-diagram-2"></i> CPL - Profil Lulusan</a>
    <a class="nav-item<?= $act('pemetaan/cpl_bk') ?>" href="<?= BASE_URL ?>/pemetaan/cpl_bk.php"><i class="bi bi-diagram-2"></i> CPL - Bahan Kajian</a>
    <a class="nav-item<?= $act('pemetaan/bk_mk') ?>" href="<?= BASE_URL ?>/pemetaan/bk_mk.php"><i class="bi bi-diagram-2"></i> Bahan Kajian - Mata Kuliah</a>
    <a class="nav-item<?= $act('pemetaan/cpl_mk') ?>" href="<?= BASE_URL ?>/pemetaan/cpl_mk.php"><i class="bi bi-diagram-2"></i> CPL - Mata Kuliah</a>
    <a class="nav-item<?= $act('pemetaan/cpl_bk_mk') ?>" href="<?= BASE_URL ?>/pemetaan/cpl_bk_mk.php"><i class="bi bi-diagram-3"></i> CPL - BK - MK</a>
    <a class="nav-item<?= $act('pemetaan/peta_cpl') ?>" href="<?= BASE_URL ?>/pemetaan/peta_cpl.php"><i class="bi bi-table"></i> Peta Pemenuhan CPL</a>

    <div class="nav-section">Capaian Pembelajaran</div>
    <a class="nav-item<?= $act('cpmk/') ?>" href="<?= BASE_URL ?>/cpmk/index.php"><i class="bi bi-check2-circle"></i> CPMK</a>
    <a class="nav-item<?= $act('sub_cpmk/') ?>" href="<?= BASE_URL ?>/sub_cpmk/index.php"><i class="bi bi-list-check"></i> Sub-CPMK</a>

    <div class="nav-section">Penilaian</div>
    <a class="nav-item<?= $act('penilaian/teknik') ?>" href="<?= BASE_URL ?>/penilaian/teknik.php"><i class="bi bi-clipboard-check"></i> Teknik Penilaian CPMK</a>
    <a class="nav-item<?= $act('penilaian/mekanisme') ?>" href="<?= BASE_URL ?>/penilaian/mekanisme.php"><i class="bi bi-list-ol"></i> Tahap &amp; Mekanisme</a>
    <a class="nav-item<?= $act('penilaian/bobot') ?>" href="<?= BASE_URL ?>/penilaian/bobot.php"><i class="bi bi-sliders"></i> Bobot Penilaian</a>

    <div class="nav-section">Dokumen Kurikulum</div>
    <a class="nav-item<?= $act('rps/rumusan_mk') ?>" href="<?= BASE_URL ?>/rps/rumusan_mk.php"><i class="bi bi-file-earmark-text"></i> Rumusan Akhir MK</a>
    <a class="nav-item<?= $act('rps/rumusan_cpl') ?>" href="<?= BASE_URL ?>/rps/rumusan_cpl.php"><i class="bi bi-file-earmark-check"></i> Rumusan Akhir CPL</a>
    <a class="nav-item<?= $act('rps/index') ?>" href="<?= BASE_URL ?>/rps/index.php"><i class="bi bi-journal-richtext"></i> RPS Dosen</a>

    <div class="nav-section">Monitoring</div>
    <a class="nav-item<?= $act('monitoring/') ?>" href="<?= BASE_URL ?>/monitoring/kelengkapan.php"><i class="bi bi-clipboard-data"></i> Inventaris Kelengkapan MK</a>

    <div class="nav-section">Pengaturan</div>
    <?php if (can_manage_users()): ?>
      <a class="nav-item<?= $act('users/') ?>" href="<?= BASE_URL ?>/users/index.php"><i class="bi bi-people"></i> Manajemen Pengguna</a>
    <?php endif; ?>
    <a class="nav-item logout" href="<?= BASE_URL ?>/auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </nav>

  <div class="sidebar-foot">&copy; <?= date('Y') ?> FTI Universitas YARSI</div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
