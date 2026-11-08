<?php
require_once __DIR__ . '/../includes/auth.php';
$pdo = db();
$can = can_manage_cpmk();
$fields = ['mbkm' => 'MBKM', 'partisipasi' => 'Partisipasi', 'observasi' => 'Observasi', 'unjuk_kerja' => 'Unjuk Kerja', 'tes_uts' => 'Tes UTS', 'tes_uas' => 'Tes UAS', 'tes_lisan' => 'Tes Lisan'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$can) { http_response_code(403); die('Akses ditolak'); }
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $mkId = (int)($_POST['mk_id'] ?? 0);
        $cpmkId = (int)($_POST['cpmk_id'] ?? 0);
        if (!$mkId || !$cpmkId) {
            flash('danger', 'Mata kuliah dan CPMK wajib dipilih.');
        } else {
            $m = $pdo->prepare('SELECT kode_mk FROM mata_kuliah WHERE id=?'); $m->execute([$mkId]); $mkRaw = (string)$m->fetchColumn();
            $c = $pdo->prepare('SELECT kode_cpmk, cpl_id FROM cpmk WHERE id=?'); $c->execute([$cpmkId]); $cr = $c->fetch();
            $cplId = $cr['cpl_id'] ?? null;
            $cplRaw = '-';
            if ($cplId) { $q = $pdo->prepare('SELECT kode_cpl FROM cpl WHERE id=?'); $q->execute([$cplId]); $cplRaw = (string)$q->fetchColumn(); }
            $cols = ['mk_id', 'kode_mk_raw', 'cpmk_id', 'kode_cpmk_raw', 'cpl_id', 'kode_cpl_raw'];
            $vals = [$mkId, $mkRaw, $cpmkId, $cr['kode_cpmk'] ?? '', $cplId, $cplRaw];
            foreach ($fields as $f => $lbl) { $cols[] = $f; $vals[] = !empty($_POST[$f]) ? 'v' : null; }
            try {
                if ($id > 0) {
                    $set = implode(',', array_map(fn($c) => "$c=?", array_slice($cols, 1)));
                    $pdo->prepare("UPDATE teknik_penilaian_cpmk SET $set WHERE id=?")->execute(array_merge(array_slice($vals, 1), [$id]));
                    log_activity('update', 'teknik_penilaian', "Teknik penilaian $mkRaw diperbarui");
                    flash('success', 'Teknik penilaian diperbarui.');
                } else {
                    $ph = implode(',', array_fill(0, count($cols), '?'));
                    $pdo->prepare("INSERT INTO teknik_penilaian_cpmk (" . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
                    log_activity('create', 'teknik_penilaian', "Teknik penilaian $mkRaw ditambahkan");
                    flash('success', 'Teknik penilaian ditambahkan.');
                }
            } catch (PDOException $e) { flash('danger', 'Gagal menyimpan teknik penilaian.'); }
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM teknik_penilaian_cpmk WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
        log_activity('delete', 'teknik_penilaian', 'Teknik penilaian dihapus');
        flash('success', 'Data dihapus.');
    }
    redirect('teknik.php');
}

$mkFilter = (int)($_GET['mk'] ?? 0);
$where = $mkFilter ? 'WHERE t.mk_id = ?' : '';
$params = $mkFilter ? [$mkFilter] : [];
$s = $pdo->prepare("SELECT COUNT(*) FROM teknik_penilaian_cpmk t $where"); $s->execute($params); $total = (int)$s->fetchColumn();
[$limit, $offset, , $page, $pages] = paginate($total, 15);
$stmt = $pdo->prepare("SELECT t.*, m.nama_mk FROM teknik_penilaian_cpmk t LEFT JOIN mata_kuliah m ON m.id=t.mk_id $where ORDER BY t.kode_mk_raw, t.kode_cpmk_raw LIMIT $limit OFFSET $offset");
$stmt->execute($params); $rows = $stmt->fetchAll();
$mks = $pdo->query('SELECT * FROM mata_kuliah ORDER BY CAST(semester AS UNSIGNED), kode_mk')->fetchAll();
$cpmks = $pdo->query('SELECT k.*, c.kode_cpl FROM cpmk k JOIN cpl c ON c.id=k.cpl_id ORDER BY k.kode_cpmk')->fetchAll();

$edit = null;
if (!empty($_GET['edit'])) { $s = $pdo->prepare('SELECT * FROM teknik_penilaian_cpmk WHERE id=?'); $s->execute([(int)$_GET['edit']]); $edit = $s->fetch(); }

$page_title = 'Teknik Penilaian CPMK';
$page_subtitle = 'Teknik penilaian tiap CPMK per mata kuliah';
$page_scripts = $edit ? '<script>new bootstrap.Modal(document.getElementById("modalTeknik")).show();</script>' : '';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h2><i class="bi bi-clipboard-check me-1 text-primary"></i> Teknik Penilaian CPMK</h2>
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
        <thead><tr><th>MK</th><th>CPMK</th><?php foreach ($fields as $lbl): ?><th class="text-center" style="width:78px"><?= e($lbl) ?></th><?php endforeach; ?><th style="width:100px" class="text-center">Aksi</th></tr></thead>
        <tbody>
          <?php if (!$rows): ?><tr><td colspan="<?= count($fields) + 3 ?>"><div class="empty-state"><i class="bi bi-inbox"></i> Data tidak ditemukan.</div></td></tr><?php endif; ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><b><?= e($r['kode_mk_raw']) ?></b><div class="small text-muted"><?= e($r['nama_mk'] ?? '') ?></div></td>
              <td><span class="badge bg-primary"><?= e($r['kode_cpmk_raw'] ?? '-') ?></span></td>
              <?php foreach ($fields as $f => $lbl): ?><td class="text-center"><?= $r[$f] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<span class="text-muted">-</span>' ?></td><?php endforeach; ?>
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
<div class="modal fade" id="modalTeknik" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="modal-header"><h5 class="modal-title"><?= $edit ? 'Edit' : 'Tambah' ?> Teknik Penilaian</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Mata Kuliah <span class="text-danger">*</span></label><select name="mk_id" class="form-select" required><option value="">-- pilih --</option><?php foreach ($mks as $m): ?><option value="<?= $m['id'] ?>" <?= (int)($edit['mk_id'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['kode_mk']) ?> - <?= e($m['nama_mk']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label">CPMK <span class="text-danger">*</span></label><select name="cpmk_id" class="form-select" required><option value="">-- pilih --</option><?php foreach ($cpmks as $c): ?><option value="<?= $c['id'] ?>" <?= (int)($edit['cpmk_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['kode_cpmk']) ?> (<?= e($c['kode_cpl']) ?>)</option><?php endforeach; ?></select></div>
          <div class="col-12"><label class="form-label">Teknik yang Digunakan</label>
            <div class="row g-2">
              <?php foreach ($fields as $f => $lbl): ?>
                <div class="col-6 col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="<?= $f ?>" id="tf_<?= $f ?>" <?= !empty($edit[$f]) ? 'checked' : '' ?>><label class="form-check-label" for="tf_<?= $f ?>"><?= e($lbl) ?></label></div></div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-navy"><i class="bi bi-save"></i> Simpan</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
