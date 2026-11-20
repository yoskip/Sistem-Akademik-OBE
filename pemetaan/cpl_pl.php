<?php
require_once __DIR__ . '/../includes/auth.php';
$pdo = db();
$can = can_manage_master();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$can) { deny_access(); }
    $pairs = $_POST['pairs'] ?? [];
    $cplIds = $pdo->query('SELECT id FROM cpl')->fetchAll(PDO::FETCH_COLUMN);
    $plIds = $pdo->query('SELECT id FROM profil_lulusan')->fetchAll(PDO::FETCH_COLUMN);
    $cplIds = array_flip(array_map('intval', $cplIds));
    $plIds = array_flip(array_map('intval', $plIds));
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM pl_cpl');
        $ins = $pdo->prepare('INSERT IGNORE INTO pl_cpl (cpl_id, pl_id) VALUES (?,?)');
        $n = 0;
        foreach ((array)$pairs as $p) {
            [$c, $pl] = array_map('intval', explode(':', (string)$p) + [0, 0]);
            if (isset($cplIds[$c], $plIds[$pl])) { $ins->execute([$c, $pl]); $n++; }
        }
        $pdo->commit();
        log_activity('update', 'pemetaan', "Pemetaan CPL-PL disimpan ($n relasi)");
        flash('success', "Pemetaan CPL - Profil Lulusan berhasil disimpan ($n relasi).");
    } catch (Throwable $e) { obe_log_database_failure($e);
        $pdo->rollBack();
        flash('danger', 'Gagal menyimpan pemetaan.');
    }
    redirect('cpl_pl.php');
}

$cpls = $pdo->query('SELECT * FROM cpl ORDER BY kode_cpl')->fetchAll();
$pls = $pdo->query('SELECT * FROM profil_lulusan ORDER BY kode_pl')->fetchAll();
$set = [];
foreach ($pdo->query('SELECT cpl_id, pl_id FROM pl_cpl') as $r) $set[$r['cpl_id'] . ':' . $r['pl_id']] = true;

$page_title = 'Pemetaan CPL - Profil Lulusan';
$page_subtitle = 'Keterkaitan CPL dengan Profil Lulusan';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h2><i class="bi bi-diagram-2 me-1 text-primary"></i> CPL &harr; Profil Lulusan</h2>
  <span class="text-muted small">Centang keterkaitan, lalu simpan.</span>
</div>

<form method="post">
  <?= csrf_field() ?>
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-matrix mb-0">
          <thead><tr><th style="min-width:180px">CPL</th><?php foreach ($pls as $pl): ?><th class="text-center" title="<?= e($pl['nama_pl']) ?>"><?= e($pl['kode_pl']) ?></th><?php endforeach; ?></tr></thead>
          <tbody>
            <?php foreach ($cpls as $cpl): ?>
              <tr>
                <td><b><?= e($cpl['kode_cpl']) ?></b><div class="text-muted small text-truncate" style="max-width:320px"><?= e($cpl['deskripsi']) ?></div></td>
                <?php foreach ($pls as $pl): ?>
                  <td class="text-center">
                    <input class="form-check-input" type="checkbox" name="pairs[]" value="<?= $cpl['id'] ?>:<?= $pl['id'] ?>" <?= isset($set[$cpl['id'] . ':' . $pl['id']]) ? 'checked' : '' ?> <?= $can ? '' : 'disabled' ?>>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php if ($can): ?>
      <div class="card-footer bg-white text-end"><button class="btn btn-navy"><i class="bi bi-save"></i> Simpan Pemetaan</button></div>
    <?php endif; ?>
  </div>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
