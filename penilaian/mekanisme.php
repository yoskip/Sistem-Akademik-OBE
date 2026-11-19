<?php
require_once __DIR__ . '/../includes/auth.php';
$pdo = db();
$can = can_manage_cpmk();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$can) { http_response_code(403); die('Akses ditolak'); }
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $mkId = (int)($_POST['mk_id'] ?? 0);
        $cpmkId = (int)($_POST['cpmk_id'] ?? 0);
        $tahap = trim($_POST['tahap'] ?? '');
        $teknik = trim($_POST['teknik'] ?? '');
        $instrumen = trim($_POST['instrumen'] ?? '');
        $kriteria = trim($_POST['kriteria'] ?? '');
        $bobot = trim($_POST['bobot'] ?? '');
        if (!validate_id($mkId)) {
            http_response_code(422);
            flash('danger', 'Mata kuliah wajib dipilih.');
        } elseif ($cpmkId && !validate_id($cpmkId)) {
            http_response_code(422);
            flash('danger', 'Pilihan CPMK tidak valid.');
        } elseif ($tahap === '' || mb_strlen($tahap) > 1000) {
            http_response_code(422);
            flash('danger', 'Tahap penilaian wajib diisi (1-1000 karakter).');
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
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE mekanisme_penilaian SET mk_id=?, kode_mk_raw=?, cpmk_id=?, kode_cpmk_raw=?, cpl_id=?, kode_cpl_raw=?, tahap=?, teknik=?, instrumen=?, kriteria=?, bobot=? WHERE id=?')
                        ->execute([$mkId, $mkRaw, $cpmkId ?: null, $kodeCpmk, $cplId, $cplRaw, $tahap, $teknik ?: null, $instrumen ?: null, $kriteria ?: null, $bobot !== '' ? $bobot : null, $id]);
                    log_activity('update', 'mekanisme', "Mekanisme $mkRaw diperbarui");
                    flash('success', 'Tahap & mekanisme diperbarui.');
                } else {
                    $pdo->prepare('INSERT INTO mekanisme_penilaian (mk_id, kode_mk_raw, cpmk_id, kode_cpmk_raw, cpl_id, kode_cpl_raw, tahap, teknik, instrumen, kriteria, bobot) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$mkId, $mkRaw, $cpmkId ?: null, $kodeCpmk, $cplId, $cplRaw, $tahap, $teknik ?: null, $instrumen ?: null, $kriteria ?: null, $bobot !== '' ? $bobot : null]);
                    log_activity('create', 'mekanisme', "Mekanisme $mkRaw ditambahkan");
                    flash('success', 'Tahap & mekanisme ditambahkan.');
                }
            } catch (PDOException $e) { flash('danger', 'Gagal menyimpan data.'); }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        if (!validate_id($id)) {
            http_response_code(422);
            flash('danger', 'ID data tidak valid.');
        } else {
            $pdo->prepare('DELETE FROM mekanisme_penilaian WHERE id=?')->execute([(int)$id]);
            log_activity('delete', 'mekanisme', 'Mekanisme dihapus');
            flash('success', 'Data dihapus.');
        }
    }
    redirect('mekanisme.php');
}

$mkFilter = (int)($_GET['mk'] ?? 0);
$where = $mkFilter ? 'WHERE t.mk_id = ?' : '';
$params = $mkFilter ? [$mkFilter] : [];
$s = $pdo->prepare("SELECT COUNT(*) FROM mekanisme_penilaian t $where"); $s->execute($params); $total = (int)$s->fetchColumn();
[$limit, $offset, , $page, $pages] = paginate($total, 15);
$stmt = $pdo->prepare("SELECT t.*, m.nama_mk FROM mekanisme_penilaian t LEFT JOIN mata_kuliah m ON m.id=t.mk_id $where ORDER BY t.kode_mk_raw, t.id LIMIT $limit OFFSET $offset");
$stmt->execute($params); $rows = $stmt->fetchAll();
$mks = $pdo->query('SELECT * FROM mata_kuliah ORDER BY CAST(semester AS UNSIGNED), kode_mk')->fetchAll();
$cpmks = $pdo->query('SELECT k.*, c.kode_cpl FROM cpmk k JOIN cpl c ON c.id=k.cpl_id ORDER BY k.kode_cpmk')->fetchAll();

$edit = null;
if (!empty($_GET['edit'])) { $s = $pdo->prepare('SELECT * FROM mekanisme_penilaian WHERE id=?'); $s->execute([(int)$_GET['edit']]); $edit = $s->fetch(); }

$page_title = 'Tahap & Mekanisme Penilaian';
$page_subtitle = 'Tahapan, instrumen, kriteria, dan bobot penilaian';
$page_scripts = $edit ? '<script>new bootstrap.Modal(document.getElementById("modalMek")).show();</script>' : '';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h2><i class="bi bi-list-ol me-1 text-primary"></i> Tahap &amp; Mekanisme Penilaian</h2>
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
        <thead><tr><th>MK</th><th>CPMK</th><th>Tahap</th><th>Teknik</th><th>Instrumen</th><th>Kriteria</th><th class="text-center">Bobot</th><th style="width:100px" class="text-center">Aksi</th></tr></thead>
        <tbody>
          <?php if (!$rows): ?><tr><td colspan="8"><div class="empty-state"><i class="bi bi-inbox"></i> Data tidak ditemukan.</div></td></tr><?php endif; ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><b><?= e($r['kode_mk_raw']) ?></b><div class="small text-muted"><?= e($r['nama_mk'] ?? '') ?></div></td>
              <td><span class="badge bg-primary"><?= e($r['kode_cpmk_raw'] ?? '-') ?></span></td>
              <td class="small"><?= e($r['tahap']) ?></td>
              <td class="small"><?= e($r['teknik'] ?? '-') ?></td>
              <td class="small text-muted"><?= e($r['instrumen'] ?? '-') ?></td>
              <td class="small text-muted"><?= e($r['kriteria'] ?? '-') ?></td>
              <td class="text-center"><?= $r['bobot'] !== null ? '<span class="badge bg-light text-dark border">' . e($r['bobot']) . '</span>' : '-' ?></td>
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
<div class="modal fade" id="modalMek" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="modal-header"><h5 class="modal-title"><?= $edit ? 'Edit' : 'Tambah' ?> Tahap &amp; Mekanisme</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Mata Kuliah <span class="text-danger">*</span></label><select name="mk_id" class="form-select" required><option value="">-- pilih --</option><?php foreach ($mks as $m): ?><option value="<?= $m['id'] ?>" <?= (int)($edit['mk_id'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['kode_mk']) ?> - <?= e($m['nama_mk']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label">CPMK</label><select name="cpmk_id" class="form-select"><option value="">-- opsional --</option><?php foreach ($cpmks as $c): ?><option value="<?= $c['id'] ?>" <?= (int)($edit['cpmk_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['kode_cpmk']) ?> (<?= e($c['kode_cpl']) ?>)</option><?php endforeach; ?></select></div>
          <div class="col-md-8"><label class="form-label">Tahap <span class="text-danger">*</span></label><input type="text" name="tahap" class="form-control" value="<?= e($edit['tahap'] ?? '') ?>" required></div>
          <div class="col-md-4"><label class="form-label">Bobot</label><input type="number" step="0.01" name="bobot" class="form-control" value="<?= e($edit['bobot'] ?? '') ?>"></div>
          <div class="col-12"><label class="form-label">Teknik</label><input type="text" name="teknik" class="form-control" value="<?= e($edit['teknik'] ?? '') ?>"></div>
          <div class="col-12"><label class="form-label">Instrumen</label><textarea name="instrumen" class="form-control" rows="2"><?= e($edit['instrumen'] ?? '') ?></textarea></div>
          <div class="col-12"><label class="form-label">Kriteria</label><textarea name="kriteria" class="form-control" rows="2"><?= e($edit['kriteria'] ?? '') ?></textarea></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-navy"><i class="bi bi-save"></i> Simpan</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
