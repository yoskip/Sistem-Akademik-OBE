<?php
require_once __DIR__ . '/../includes/auth.php';
$pdo = db();
$can = can_manage_cpmk();
$fields = ['mbkm' => 'MBKM', 'partisipasi' => 'Partisipasi', 'observasi' => 'Observasi', 'unjuk_kerja' => 'Unjuk Kerja', 'tes_uts' => 'Tes UTS', 'tes_uas' => 'Tes UAS', 'tes_lisan' => 'Tes Lisan'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$can) { deny_access(); }
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $mkId = (int)($_POST['mk_id'] ?? 0);
        $cpmkId = (int)($_POST['cpmk_id'] ?? 0);
        $versi = trim($_POST['versi'] ?? '18');
        if (!validate_id($mkId)) {
            http_response_code(422);
            flash('danger', 'Mata kuliah wajib dipilih.');
        } elseif ($cpmkId && !validate_id($cpmkId)) {
            http_response_code(422);
            flash('danger', 'Pilihan CPMK tidak valid.');
        } elseif ($id !== 0 && !validate_id($id)) {
            http_response_code(422);
            flash('danger', 'ID data tidak valid.');
        } else {
            $m = $pdo->prepare('SELECT kode_mk FROM mata_kuliah WHERE id=?'); $m->execute([$mkId]); $mkRaw = (string)$m->fetchColumn();
            $kodeCpmk = null; $cplId = null; $cplRaw = null;
            if ($cpmkId) {
                $c = $pdo->prepare('SELECT kode_cpmk, cpl_id FROM cpmk WHERE id=?'); $c->execute([$cpmkId]); $cr = $c->fetch();
                $kodeCpmk = $cr['kode_cpmk'] ?? null; $cplId = $cr['cpl_id'] ?? null;
                if ($cplId) { $q = $pdo->prepare('SELECT kode_cpl FROM cpl WHERE id=?'); $q->execute([$cplId]); $cplRaw = $q->fetchColumn(); }
            }
            $allowedBobotCols = [
                'versi', 'mk_id', 'kode_mk_raw', 'cpmk_id', 'kode_cpmk_raw', 'cpl_id', 'kode_cpl_raw',
                'mbkm', 'partisipasi', 'observasi', 'unjuk_kerja', 'tes_uts', 'tes_uas', 'tes_lisan', 'total'
            ];
            $cols = ['versi', 'mk_id', 'kode_mk_raw', 'cpmk_id', 'kode_cpmk_raw', 'cpl_id', 'kode_cpl_raw'];
            $vals = [$versi, $mkId, $mkRaw, $cpmkId ?: null, $kodeCpmk, $cplId, $cplRaw];
            $totalBobot = 0;
            foreach ($fields as $f => $lbl) {
                if (!in_array($f, $allowedBobotCols, true)) continue;
                $v = ($_POST[$f] ?? '') !== '' ? (float)$_POST[$f] : null;
                $cols[] = $f; $vals[] = $v;
                if ($v !== null) $totalBobot += $v;
            }
            $cols[] = 'total'; $vals[] = $totalBobot;
            try {
                if ($id > 0) {
                    $set = implode(',', array_map(fn($c) => "`$c`=?", array_slice($cols, 1)));
                    $pdo->prepare("UPDATE bobot_penilaian SET $set WHERE id=?")->execute(array_merge(array_slice($vals, 1), [$id]));
                    log_activity('update', 'bobot', "Bobot penilaian $mkRaw diperbarui");
                    flash('success', 'Bobot penilaian diperbarui.');
                } else {
                    $escapedCols = implode(',', array_map(fn($c) => "`$c`", $cols));
                    $ph = implode(',', array_fill(0, count($cols), '?'));
                    $pdo->prepare("INSERT INTO bobot_penilaian ($escapedCols) VALUES ($ph)")->execute($vals);
                    log_activity('create', 'bobot', "Bobot penilaian $mkRaw ditambahkan");
                    flash('success', 'Bobot penilaian ditambahkan.');
                }
            } catch (PDOException $e) { obe_log_database_failure($e); flash('danger', 'Gagal menyimpan bobot penilaian.'); }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        if (!validate_id($id)) {
            http_response_code(422);
            flash('danger', 'ID data tidak valid.');
        } else {
            $pdo->prepare('DELETE FROM bobot_penilaian WHERE id=?')->execute([(int)$id]);
            log_activity('delete', 'bobot', 'Bobot penilaian dihapus');
            flash('success', 'Data dihapus.');
        }
    }
    redirect('bobot.php');
}

$mkFilter = (int)($_GET['mk'] ?? 0);
$where = $mkFilter ? 'WHERE b.mk_id = ?' : '';
$params = $mkFilter ? [$mkFilter] : [];
$s = $pdo->prepare("SELECT COUNT(*) FROM bobot_penilaian b $where"); $s->execute($params); $total = (int)$s->fetchColumn();
[$limit, $offset, , $page, $pages] = paginate($total, 15);
$stmt = $pdo->prepare("SELECT b.*, m.nama_mk FROM bobot_penilaian b LEFT JOIN mata_kuliah m ON m.id=b.mk_id $where ORDER BY b.kode_mk_raw, b.kode_cpmk_raw LIMIT $limit OFFSET $offset");
$stmt->execute($params); $rows = $stmt->fetchAll();
$mks = $pdo->query('SELECT * FROM mata_kuliah ORDER BY CAST(semester AS UNSIGNED), kode_mk')->fetchAll();
$cpmks = $pdo->query('SELECT k.*, c.kode_cpl FROM cpmk k JOIN cpl c ON c.id=k.cpl_id ORDER BY k.kode_cpmk')->fetchAll();

$edit = null;
if (!empty($_GET['edit'])) { $s = $pdo->prepare('SELECT * FROM bobot_penilaian WHERE id=?'); $s->execute([(int)$_GET['edit']]); $edit = $s->fetch(); }

$page_title = 'Bobot Penilaian';
$page_subtitle = 'Bobot tiap teknik penilaian per CPMK';
$page_scripts = $edit ? '<script>new bootstrap.Modal(document.getElementById("modalBobot")).show();</script>' : '';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h2><i class="bi bi-sliders me-1 text-primary"></i> Bobot Penilaian</h2>
  <div class="d-flex gap-2 flex-wrap">
    <form method="get">
      <select name="mk" class="form-select form-select-sm" data-autosubmit style="min-width:260px">
        <option value="0">Semua Mata Kuliah</option>
        <?php foreach ($mks as $m): ?><option value="<?= $m['id'] ?>" <?= $mkFilter === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['kode_mk']) ?> - <?= e(mb_strimwidth($m['nama_mk'], 0, 45, '...')) ?></option><?php endforeach; ?>
      </select>
    </form>
    <?php if ($can): ?><a href="?edit=0" class="btn btn-sm btn-blue"><i class="bi bi-plus-lg"></i> Tambah</a><?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0 align-middle">
        <thead><tr><th>MK</th><th>CPMK</th><?php foreach ($fields as $lbl): ?><th class="text-center" style="width:62px"><?= e($lbl) ?></th><?php endforeach; ?><th class="text-center">Total</th><th style="width:100px" class="text-center">Aksi</th></tr></thead>
        <tbody>
          <?php if (!$rows): ?><tr><td colspan="<?= count($fields) + 4 ?>"><div class="empty-state"><i class="bi bi-inbox"></i> Data tidak ditemukan.</div></td></tr><?php endif; ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><b><?= e($r['kode_mk_raw']) ?></b><div class="small text-muted"><?= e($r['nama_mk'] ?? '') ?></div></td>
              <td><span class="badge bg-primary"><?= e($r['kode_cpmk_raw'] ?? '-') ?></span></td>
              <?php foreach ($fields as $f => $lbl): ?><td class="text-center"><?= $r[$f] !== null && $r[$f] !== '' ? e(rtrim(rtrim(number_format((float)$r[$f], 2, '.', ''), '0'), '.')) : '<span class="text-muted">-</span>' ?></td><?php endforeach; ?>
              <td class="text-center"><span class="badge bg-<?= ((float)$r['total'] >= 100) ? 'success' : 'warning text-dark' ?>"><?= e(rtrim(rtrim(number_format((float)$r['total'], 2, '.', ''), '0'), '.')) ?></span></td>
              <td class="text-center">
                <?php if ($can): ?>
                  <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $r['id'] ?>"><i class="bi bi-pencil"></i></a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Hapus data ini?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center"><span class="text-muted small">Total <?= $total ?> data</span><?= render_pagination($page, $pages) ?></div>
</div>

<?php if ($can): ?>
<div class="modal fade" id="modalBobot" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="modal-header"><h5 class="modal-title"><?= $edit ? 'Edit' : 'Tambah' ?> Bobot Penilaian</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Versi</label><input type="text" name="versi" class="form-control" value="<?= e($edit['versi'] ?? '18') ?>"></div>
          <div class="col-md-8"><label class="form-label">Mata Kuliah <span class="text-danger">*</span></label><select name="mk_id" class="form-select" required><option value="">-- pilih --</option><?php foreach ($mks as $m): ?><option value="<?= $m['id'] ?>" <?= (int)($edit['mk_id'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['kode_mk']) ?> - <?= e($m['nama_mk']) ?></option><?php endforeach; ?></select></div>
          <div class="col-12"><label class="form-label">CPMK</label><select name="cpmk_id" class="form-select"><option value="">-- opsional --</option><?php foreach ($cpmks as $c): ?><option value="<?= $c['id'] ?>" <?= (int)($edit['cpmk_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['kode_cpmk']) ?> (<?= e($c['kode_cpl']) ?>)</option><?php endforeach; ?></select></div>
          <?php foreach ($fields as $f => $lbl): ?>
            <div class="col-6 col-md-3"><label class="form-label"><?= e($lbl) ?></label><input type="number" step="0.01" min="0" name="<?= $f ?>" class="form-control bobot-input" value="<?= e($edit[$f] ?? '') ?>"></div>
          <?php endforeach; ?>
          <div class="col-12"><div class="alert alert-info mb-0 py-2"><i class="bi bi-calculator"></i> Total bobot dihitung otomatis: <b id="totalBobot">0</b></div></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-navy"><i class="bi bi-save"></i> Simpan</button></div>
    </form>
  </div>
</div>
<script>
document.addEventListener('input', function (e) {
  if (!e.target.classList.contains('bobot-input')) return;
  let t = 0;
  document.querySelectorAll('.bobot-input').forEach(function (i) { t += parseFloat(i.value) || 0; });
  document.getElementById('totalBobot').textContent = t.toLocaleString('id-ID');
});
</script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
