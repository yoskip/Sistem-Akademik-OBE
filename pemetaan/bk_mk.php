<?php
require_once __DIR__ . '/../includes/auth.php';
$pdo = db();
$can = can_manage_master();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$can) { deny_access(); }
    $pairs = (array)($_POST['pairs'] ?? []);
    $bkIds = array_flip(array_map('intval', $pdo->query('SELECT id FROM bahan_kajian')->fetchAll(PDO::FETCH_COLUMN)));
    $mkIds = array_flip(array_map('intval', $pdo->query('SELECT id FROM mata_kuliah')->fetchAll(PDO::FETCH_COLUMN)));
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM bahan_kajian_mk');
        $ins = $pdo->prepare('INSERT IGNORE INTO bahan_kajian_mk (bahan_kajian_id, mk_id) VALUES (?,?)');
        $n = 0;
        foreach ($pairs as $p) {
            [$b, $m] = array_map('intval', explode(':', (string)$p) + [0, 0]);
            if (isset($bkIds[$b], $mkIds[$m])) { $ins->execute([$b, $m]); $n++; }
        }
        $pdo->commit();
        log_activity('update', 'pemetaan', "Pemetaan BK-MK disimpan ($n relasi)");
        flash('success', "Pemetaan Bahan Kajian - Mata Kuliah disimpan ($n relasi).");
    } catch (Throwable $e) { obe_log_database_failure($e);
        $pdo->rollBack();
        flash('danger', 'Gagal menyimpan pemetaan.');
    }
    redirect('bk_mk.php');
}

$bks = $pdo->query('SELECT * FROM bahan_kajian ORDER BY kode_bk')->fetchAll();
$mks = $pdo->query('SELECT * FROM mata_kuliah ORDER BY CAST(semester AS UNSIGNED), kode_mk')->fetchAll();
$set = [];
foreach ($pdo->query('SELECT bahan_kajian_id, mk_id FROM bahan_kajian_mk') as $r) $set[$r['bahan_kajian_id'] . ':' . $r['mk_id']] = true;

$page_title = 'Pemetaan Bahan Kajian - Mata Kuliah';
$page_subtitle = 'Keterkaitan Bahan Kajian dengan Mata Kuliah';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h2><i class="bi bi-diagram-2 me-1 text-primary"></i> Bahan Kajian &harr; Mata Kuliah</h2>
  <span class="text-muted small">Centang keterkaitan, lalu simpan.</span>
</div>
<form method="post">
  <?= csrf_field() ?>
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-matrix mb-0">
          <thead><tr><th style="min-width:240px">Mata Kuliah</th><?php foreach ($bks as $bk): ?><th class="text-center" title="<?= e($bk['nama_bk']) ?>"><?= e($bk['kode_bk']) ?></th><?php endforeach; ?></tr></thead>
          <tbody>
            <?php foreach ($mks as $mk): ?>
              <tr>
                <td><b><?= e($mk['kode_mk']) ?></b> <span class="text-muted small"><?= e($mk['nama_mk']) ?></span></td>
                <?php foreach ($bks as $bk): ?>
                  <td class="text-center"><input class="form-check-input" type="checkbox" name="pairs[]" value="<?= $bk['id'] ?>:<?= $mk['id'] ?>" <?= isset($set[$bk['id'] . ':' . $mk['id']]) ? 'checked' : '' ?> <?= $can ? '' : 'disabled' ?>></td>
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
