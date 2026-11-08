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
        $kode = trim($_POST['kode_sub'] ?? '');
        $mkId = (int)($_POST['mk_id'] ?? 0);
        $cpmkId = (int)($_POST['cpmk_id'] ?? 0);
        $desk = trim($_POST['deskripsi'] ?? '');
        if (!$mkId || !$cpmkId || $desk === '') {
            flash('danger', 'Mata kuliah, CPMK, dan deskripsi wajib diisi.');
        } else {
            $c = $pdo->prepare('SELECT cpl_id FROM cpmk WHERE id=?');
            $c->execute([$cpmkId]);
            $cplId = $c->fetchColumn();
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE sub_cpmk SET kode_sub=?, mk_id=?, cpmk_id=?, cpl_id=?, deskripsi=? WHERE id=?')
                        ->execute([$kode ?: null, $mkId, $cpmkId, $cplId ?: null, $desk, $id]);
                    log_activity('update', 'sub_cpmk', 'Sub-CPMK diperbarui');
                    flash('success', 'Sub-CPMK diperbarui.');
                } else {
                    $pdo->prepare('INSERT INTO sub_cpmk (kode_sub, mk_id, cpmk_id, cpl_id, deskripsi) VALUES (?,?,?,?,?)')
                        ->execute([$kode ?: null, $mkId, $cpmkId, $cplId ?: null, $desk]);
                    log_activity('create', 'sub_cpmk', 'Sub-CPMK ditambahkan');
                    flash('success', 'Sub-CPMK ditambahkan.');
                }
            } catch (PDOException $e) {
                flash('danger', 'Gagal menyimpan Sub-CPMK.');
            }
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM sub_cpmk WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
        log_activity('delete', 'sub_cpmk', 'Sub-CPMK dihapus');
        flash('success', 'Sub-CPMK dihapus.');
    }
    redirect('index.php');
}

$q = trim($_GET['q'] ?? '');
$mkFilter = (int)($_GET['mk'] ?? 0);
$where = [];
$params = [];
if ($q !== '') { $where[] = '(s.deskripsi LIKE ? OR s.kode_sub LIKE ? OR k.kode_cpmk LIKE ? OR m.nama_mk LIKE ?)'; $params = array_fill(0, 4, "%$q%"); }
if ($mkFilter) { $where[] = 's.mk_id = ?'; $params[] = $mkFilter; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$s = $pdo->prepare("SELECT COUNT(*) FROM sub_cpmk s JOIN cpmk k ON k.id=s.cpmk_id JOIN mata_kuliah m ON m.id=s.mk_id $whereSql");
$s->execute($params); $total = (int)$s->fetchColumn();
[$limit, $offset, , $page, $pages] = paginate($total, 15);
$stmt = $pdo->prepare("SELECT s.*, k.kode_cpmk, m.kode_mk, m.nama_mk, c.kode_cpl
    FROM sub_cpmk s JOIN cpmk k ON k.id=s.cpmk_id JOIN mata_kuliah m ON m.id=s.mk_id LEFT JOIN cpl c ON c.id=s.cpl_id
    $whereSql ORDER BY m.semester, m.kode_mk, k.kode_cpmk, s.id LIMIT $limit OFFSET $offset");
$stmt->execute($params); $rows = $stmt->fetchAll();
$mks = $pdo->query('SELECT * FROM mata_kuliah ORDER BY CAST(semester AS UNSIGNED), kode_mk')->fetchAll();
$cpmks = $pdo->query('SELECT k.*, c.kode_cpl FROM cpmk k JOIN cpl c ON c.id=k.cpl_id ORDER BY k.kode_cpmk')->fetchAll();

$edit = null;
if (!empty($_GET['edit'])) { $s = $pdo->prepare('SELECT * FROM sub_cpmk WHERE id=?'); $s->execute([(int)$_GET['edit']]); $edit = $s->fetch(); }
$detail = null;
if (!empty($_GET['detail'])) {
    $s = $pdo->prepare('SELECT s.*, k.kode_cpmk, m.kode_mk, m.nama_mk, c.kode_cpl FROM sub_cpmk s JOIN cpmk k ON k.id=s.cpmk_id JOIN mata_kuliah m ON m.id=s.mk_id LEFT JOIN cpl c ON c.id=s.cpl_id WHERE s.id=?');
    $s->execute([(int)$_GET['detail']]); $detail = $s->fetch();
}

$page_title = 'Sub-CPMK';
$page_subtitle = 'Rincian Capaian Pembelajaran Mata Kuliah';
$page_scripts = '';
if ($edit) $page_scripts .= '<script>new bootstrap.Modal(document.getElementById("modalSub")).show();</script>';
if ($detail) $page_scripts .= '<script>new bootstrap.Modal(document.getElementById("detailSub")).show();</script>';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h2><i class="bi bi-list-check me-1 text-primary"></i> Daftar Sub-CPMK</h2>
  <div class="d-flex gap-2 flex-wrap">
    <form class="d-flex" method="get">
      <select name="mk" class="form-select form-select-sm me-1" data-autosubmit>
        <option value="0">Semua Mata Kuliah</option>
        <?php foreach ($mks as $m): ?><option value="<?= $m['id'] ?>" <?= $mkFilter === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['kode_mk']) ?> - <?= e(mb_strimwidth($m['nama_mk'], 0, 40, '...')) ?></option><?php endforeach; ?>
      </select>
      <input type="text" name="q" value="<?= e($q) ?>" class="form-control form-control-sm search-box" placeholder="Cari Sub-CPMK / CPMK / MK...">
      <button class="btn btn-sm btn-outline-secondary ms-1"><i class="bi bi-search"></i></button>
    </form>
    <?php if ($can): ?><a href="?edit=0" class="btn btn-sm btn-blue"><i class="bi bi-plus-lg"></i> Tambah Sub-CPMK</a><?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th style="width:90px">Kode</th><th style="width:200px">Mata Kuliah</th><th style="width:110px">CPMK</th><th>Deskripsi Sub-CPMK</th><th style="width:110px" class="text-center">Aksi</th></tr></thead>
        <tbody>
          <?php if (!$rows): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-inbox"></i> Data tidak ditemukan.</div></td></tr><?php endif; ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><span class="badge bg-secondary">SUB-<?= (int)$r['id'] ?></span><?php if ($r['kode_sub']): ?><div class="small"><?= e($r['kode_sub']) ?></div><?php endif; ?></td>
              <td><b><?= e($r['kode_mk']) ?></b><div class="small text-muted"><?= e($r['nama_mk']) ?></div></td>
              <td><span class="badge bg-primary"><?= e($r['kode_cpmk']) ?></span><?php if ($r['kode_cpl']): ?><div class="small text-muted mt-1"><?= e($r['kode_cpl']) ?></div><?php endif; ?></td>
              <td class="small"><?= e($r['deskripsi']) ?></td>
              <td class="text-center">
                <a class="btn btn-sm btn-outline-secondary" href="?detail=<?= $r['id'] ?>"><i class="bi bi-eye"></i></a>
                <?php if ($can): ?>
                  <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $r['id'] ?>"><i class="bi bi-pencil"></i></a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Hapus Sub-CPMK ini?')">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center">
    <span class="text-muted small">Total <?= $total ?> data</span><?= render_pagination($page, $pages) ?>
  </div>
</div>

<?php if ($can): ?>
<div class="modal fade" id="modalSub" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="modal-header"><h5 class="modal-title"><?= $edit ? 'Edit' : 'Tambah' ?> Sub-CPMK</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Kode Sub-CPMK</label><input type="text" name="kode_sub" class="form-control" value="<?= e($edit['kode_sub'] ?? '') ?>" placeholder="opsional"></div>
          <div class="col-md-8"><label class="form-label">Mata Kuliah <span class="text-danger">*</span></label><select name="mk_id" class="form-select" required><option value="">-- pilih MK --</option><?php foreach ($mks as $m): ?><option value="<?= $m['id'] ?>" <?= (int)($edit['mk_id'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['kode_mk']) ?> - <?= e($m['nama_mk']) ?></option><?php endforeach; ?></select></div>
          <div class="col-12"><label class="form-label">CPMK <span class="text-danger">*</span></label><select name="cpmk_id" class="form-select" required><option value="">-- pilih CPMK --</option><?php foreach ($cpmks as $c): ?><option value="<?= $c['id'] ?>" <?= (int)($edit['cpmk_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['kode_cpmk']) ?> (<?= e($c['kode_cpl']) ?>) - <?= e(mb_strimwidth($c['deskripsi'], 0, 60, '...')) ?></option><?php endforeach; ?></select></div>
          <div class="col-12"><label class="form-label">Deskripsi Sub-CPMK <span class="text-danger">*</span></label><textarea name="deskripsi" class="form-control" rows="3" required><?= e($edit['deskripsi'] ?? '') ?></textarea></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-navy"><i class="bi bi-save"></i> Simpan</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="modal fade" id="detailSub" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Detail Sub-CPMK</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <table class="table table-sm">
          <tr><th style="width:120px">Kode</th><td><?= e($detail['kode_sub'] ?? ('SUB-' . ($detail['id'] ?? ''))) ?></td></tr>
          <tr><th>Mata Kuliah</th><td><?= e($detail['kode_mk'] ?? '') ?> - <?= e($detail['nama_mk'] ?? '') ?></td></tr>
          <tr><th>CPMK</th><td><?= e($detail['kode_cpmk'] ?? '') ?></td></tr>
          <tr><th>CPL</th><td><?= e($detail['kode_cpl'] ?? '-') ?></td></tr>
          <tr><th>Deskripsi</th><td><?= e($detail['deskripsi'] ?? '') ?></td></tr>
        </table>
      </div>
      <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Tutup</button></div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
