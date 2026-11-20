<?php
require_once __DIR__ . '/../includes/auth.php';
$pdo = db();
$can = can_manage_master();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$can) { deny_access(); }
    $pairs = (array)($_POST['pairs'] ?? []);
    $cplIds = array_flip(array_map('intval', $pdo->query('SELECT id FROM cpl')->fetchAll(PDO::FETCH_COLUMN)));
    $bkIds = array_flip(array_map('intval', $pdo->query('SELECT id FROM bahan_kajian')->fetchAll(PDO::FETCH_COLUMN)));
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM cpl_bahan_kajian');
        $ins = $pdo->prepare('INSERT IGNORE INTO cpl_bahan_kajian (cpl_id, bahan_kajian_id) VALUES (?,?)');
        $n = 0;
        foreach ($pairs as $p) {
            [$c, $b] = array_map('intval', explode(':', (string)$p) + [0, 0]);
            if (isset($cplIds[$c], $bkIds[$b])) { $ins->execute([$c, $b]); $n++; }
        }
        $pdo->commit();
        log_activity('update', 'pemetaan', "Pemetaan CPL-BK disimpan ($n relasi)");
        flash('success', "Pemetaan CPL - Bahan Kajian disimpan ($n relasi).");
    } catch (Throwable $e) { obe_log_database_failure($e);
        $pdo->rollBack();
        flash('danger', 'Gagal menyimpan pemetaan.');
    }
    redirect('cpl_bk.php');
}

$cpls = $pdo->query('SELECT * FROM cpl ORDER BY kode_cpl')->fetchAll();
$bks = $pdo->query('SELECT * FROM bahan_kajian ORDER BY kode_bk')->fetchAll();
$set = [];
foreach ($pdo->query('SELECT cpl_id, bahan_kajian_id FROM cpl_bahan_kajian') as $r) $set[$r['cpl_id'] . ':' . $r['bahan_kajian_id']] = true;

$page_title = 'Pemetaan CPL - Bahan Kajian';
$page_subtitle = 'Keterkaitan CPL dengan Bahan Kajian';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h2><i class="bi bi-diagram-2 me-1 text-primary"></i> CPL &harr; Bahan Kajian</h2>
  <span class="text-muted small">Centang keterkaitan, lalu simpan.</span>
</div>
<form method="post">
  <?= csrf_field() ?>
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-matrix mb-0">
          <thead><tr><th style="min-width:180px">CPL</th><?php foreach ($bks as $bk): ?><th class="text-center" title="<?= e($bk['nama_bk']) ?>"><?= e($bk['kode_bk']) ?></th><?php endforeach; ?></tr></thead>
          <tbody>
            <?php foreach ($cpls as $cpl): ?>
              <tr>
                <td><b><?= e($cpl['kode_cpl']) ?></b><div class="text-muted small text-truncate" style="max-width:280px"><?= e($cpl['deskripsi']) ?></div></td>
                <?php foreach ($bks as $bk): ?>
                  <td class="text-center"><input class="form-check-input" type="checkbox" name="pairs[]" value="<?= $cpl['id'] ?>:<?= $bk['id'] ?>" <?= isset($set[$cpl['id'] . ':' . $bk['id']]) ? 'checked' : '' ?> <?= $can ? '' : 'disabled' ?>></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php if ($can): ?><div class="card-footer bg-white text-end"><button class="btn btn-navy"><i class="bi bi-save"></i> Simpan Pemetaan</button></div><?php endif; ?>
  </div>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
