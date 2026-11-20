<?php
require_once __DIR__ . '/../includes/auth.php';
$pdo = db();
$can = can_manage_cpmk();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$can) { deny_access(); }
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $mkId = (int)($_POST['mk_id'] ?? 0);
        $semester = trim($_POST['semester'] ?? '');
        $dosen = trim($_POST['dosen'] ?? '');
        $cpl = trim($_POST['cpl'] ?? '');
        $cpmk = trim($_POST['cpmk'] ?? '');
        $bobot = trim($_POST['bobot'] ?? '');
        $teknik = trim($_POST['teknik'] ?? '');
        $catatan = trim($_POST['catatan'] ?? '');
        $urutan = (int)($_POST['urutan'] ?? 0);

        if ($id < 0 || ($id > 0 && !validate_id($id))) {
            http_response_code(422);
            flash('danger', 'ID RPS tidak valid.');
            redirect('index.php');
        }
        if (!validate_id($mkId)) {
            http_response_code(422);
            flash('danger', 'Mata kuliah wajib dipilih dengan ID valid.');
            redirect('index.php');
        }
        if (!validate_name($dosen, 2, 200)) {
            http_response_code(422);
            flash('danger', 'Nama Dosen tidak valid (2-200 karakter).');
            redirect('index.php');
        }
        if ($catatan !== '' && !validate_description($catatan, 1, 1000)) {
            http_response_code(422);
            flash('danger', 'Catatan tidak valid (maksimal 1000 karakter).');
            redirect('index.php');
        }

        $m = $pdo->prepare('SELECT kode_mk FROM mata_kuliah WHERE id=?');
        $m->execute([$mkId]);
        $mkRaw = (string)$m->fetchColumn();
        if (!$mkRaw) {
            http_response_code(422);
            flash('danger', 'Mata kuliah yang dipilih tidak ditemukan.');
            redirect('index.php');
        }

        try {
            if ($id > 0) {
                $pdo->prepare('UPDATE rps SET mk_id=?, kode_mk_raw=?, semester=?, dosen=?, cpl=?, cpmk=?, bobot=?, teknik=?, catatan=?, urutan=? WHERE id=?')
                    ->execute([$mkId, $mkRaw, $semester ?: null, $dosen, $cpl ?: null, $cpmk ?: null, $bobot ?: null, $teknik ?: null, $catatan ?: null, $urutan, $id]);
                log_activity('update', 'rps', "RPS $mkRaw diperbarui");
                flash('success', 'RPS diperbarui.');
            } else {
                $pdo->prepare('INSERT INTO rps (mk_id, kode_mk_raw, semester, dosen, cpl, cpmk, bobot, teknik, catatan, urutan) VALUES (?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$mkId, $mkRaw, $semester ?: null, $dosen, $cpl ?: null, $cpmk ?: null, $bobot ?: null, $teknik ?: null, $catatan ?: null, $urutan]);
                log_activity('create', 'rps', "RPS $mkRaw ditambahkan");
                flash('success', 'RPS ditambahkan.');
            }
        } catch (PDOException $e) { obe_log_database_failure($e); flash('danger', 'Gagal menyimpan RPS.'); }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (!validate_id($id)) {
            http_response_code(422);
            flash('danger', 'ID RPS tidak valid.');
            redirect('index.php');
        }
        $pdo->prepare('DELETE FROM rps WHERE id=?')->execute([$id]);
        log_activity('delete', 'rps', 'RPS dihapus');
        flash('success', 'RPS dihapus.');
    }
    redirect('index.php');
}

$q = trim($_GET['q'] ?? '');
$mkFilter = (int)($_GET['mk'] ?? 0);
$where = [];
$params = [];
if ($q !== '') { $where[] = '(r.dosen LIKE ? OR r.kode_mk_raw LIKE ? OR r.cpl LIKE ? OR r.cpmk LIKE ?)'; $params = array_fill(0, 4, "%$q%"); }
if ($mkFilter) { $where[] = 'r.mk_id = ?'; $params[] = $mkFilter; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$s = $pdo->prepare("SELECT COUNT(*) FROM rps r $whereSql"); $s->execute($params); $total = (int)$s->fetchColumn();
[$limit, $offset, , $page, $pages] = paginate($total, 12);
$stmt = $pdo->prepare("SELECT r.*, m.nama_mk FROM rps r LEFT JOIN mata_kuliah m ON m.id=r.mk_id $whereSql ORDER BY r.kode_mk_raw, r.urutan, r.id LIMIT $limit OFFSET $offset");
$stmt->execute($params); $rows = $stmt->fetchAll();
$mks = $pdo->query('SELECT * FROM mata_kuliah ORDER BY CAST(semester AS UNSIGNED), kode_mk')->fetchAll();

$edit = null;
if (!empty($_GET['edit'])) { $s = $pdo->prepare('SELECT * FROM rps WHERE id=?'); $s->execute([(int)$_GET['edit']]); $edit = $s->fetch(); }
$detail = null;
if (!empty($_GET['detail'])) {
    $s = $pdo->prepare('SELECT r.*, m.nama_mk FROM rps r LEFT JOIN mata_kuliah m ON m.id=r.mk_id WHERE r.id=?');
    $s->execute([(int)$_GET['detail']]); $detail = $s->fetch();
}

$page_title = 'RPS Dosen';
$page_subtitle = 'Rekap Rencana Pembelajaran Semester';
$page_scripts = '';
if ($edit) $page_scripts .= '<script>new bootstrap.Modal(document.getElementById("modalRps")).show();</script>';
if ($detail) $page_scripts .= '<script>new bootstrap.Modal(document.getElementById("detailRps")).show();</script>';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h2><i class="bi bi-journal-richtext me-1 text-primary"></i> RPS Dosen</h2>
  <div class="d-flex gap-2 flex-wrap">
    <form class="d-flex" method="get">
      <select name="mk" class="form-select form-select-sm me-1" data-autosubmit style="min-width:230px"><option value="0">Semua Mata Kuliah</option><?php foreach ($mks as $m): ?><option value="<?= $m['id'] ?>" <?= $mkFilter === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['kode_mk']) ?> - <?= e(mb_strimwidth($m['nama_mk'], 0, 35, '...')) ?></option><?php endforeach; ?></select>
      <input type="text" name="q" value="<?= e($q) ?>" class="form-control form-control-sm search-box" placeholder="Cari dosen / MK / CPL...">
      <button class="btn btn-sm btn-outline-secondary ms-1"><i class="bi bi-search"></i></button>
    </form>
    <?php if ($can): ?><a href="?edit=0" class="btn btn-sm btn-blue"><i class="bi bi-plus-lg"></i> Tambah RPS</a><?php endif; ?>
  </div>
</div>
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead><tr><th style="width:180px">Mata Kuliah</th><th>Smt</th><th>Dosen</th><th>CPL</th><th>CPMK</th><th class="text-center">Bobot</th><th>Teknik</th><th style="width:110px" class="text-center">Aksi</th></tr></thead>
        <tbody>
          <?php if (!$rows): ?><tr><td colspan="8"><div class="empty-state"><i class="bi bi-inbox"></i> Data tidak ditemukan.</div></td></tr><?php endif; ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><b><?= e($r['kode_mk_raw']) ?></b><div class="small text-muted"><?= e($r['nama_mk'] ?? '') ?></div></td>
              <td class="text-center"><?= e($r['semester'] ?? '-') ?></td>
              <td class="small"><?= e($r['dosen']) ?></td>
              <td class="small"><?= e($r['cpl'] ?? '-') ?></td>
              <td class="small"><?= e($r['cpmk'] ?? '-') ?></td>
              <td class="text-center"><?= e($r['bobot'] ?? '-') ?></td>
              <td class="small text-muted"><?= e($r['teknik'] ?? '-') ?></td>
              <td class="text-center">
                <a class="btn btn-sm btn-outline-secondary" href="?detail=<?= $r['id'] ?>"><i class="bi bi-eye"></i></a>
                <?php if ($can): ?>
                  <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $r['id'] ?>"><i class="bi bi-pencil"></i></a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Hapus RPS ini?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
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
<div class="modal fade" id="modalRps" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="modal-header"><h5 class="modal-title"><?= $edit ? 'Edit' : 'Tambah' ?> RPS</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-5"><label class="form-label">Mata Kuliah <span class="text-danger">*</span></label><select name="mk_id" class="form-select" required><option value="">-- pilih --</option><?php foreach ($mks as $m): ?><option value="<?= $m['id'] ?>" <?= (int)($edit['mk_id'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['kode_mk']) ?> - <?= e($m['nama_mk']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label class="form-label">Semester</label><input type="text" name="semester" class="form-control" value="<?= e($edit['semester'] ?? '') ?>"></div>
          <div class="col-md-4"><label class="form-label">Urutan</label><input type="number" name="urutan" class="form-control" value="<?= e($edit['urutan'] ?? 0) ?>"></div>
          <div class="col-12"><label class="form-label">Dosen <span class="text-danger">*</span></label><input type="text" name="dosen" class="form-control" value="<?= e($edit['dosen'] ?? '') ?>" required></div>
          <div class="col-md-6"><label class="form-label">CPL</label><input type="text" name="cpl" class="form-control" value="<?= e($edit['cpl'] ?? '') ?>"></div>
          <div class="col-md-6"><label class="form-label">CPMK</label><input type="text" name="cpmk" class="form-control" value="<?= e($edit['cpmk'] ?? '') ?>"></div>
          <div class="col-md-6"><label class="form-label">Bobot</label><input type="text" name="bobot" class="form-control" value="<?= e($edit['bobot'] ?? '') ?>"></div>
          <div class="col-md-6"><label class="form-label">Teknik</label><input type="text" name="teknik" class="form-control" value="<?= e($edit['teknik'] ?? '') ?>"></div>
          <div class="col-12"><label class="form-label">Catatan</label><textarea name="catatan" class="form-control" rows="2"><?= e($edit['catatan'] ?? '') ?></textarea></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-navy"><i class="bi bi-save"></i> Simpan</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
<div class="modal fade" id="detailRps" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Detail RPS</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <table class="table table-sm">
          <tr><th style="width:120px">Mata Kuliah</th><td><?= e($detail['kode_mk_raw'] ?? '') ?> - <?= e($detail['nama_mk'] ?? '') ?></td></tr>
          <tr><th>Semester</th><td><?= e($detail['semester'] ?? '-') ?></td></tr>
          <tr><th>Dosen</th><td><?= e($detail['dosen'] ?? '-') ?></td></tr>
          <tr><th>CPL</th><td><?= e($detail['cpl'] ?? '-') ?></td></tr>
          <tr><th>CPMK</th><td><?= e($detail['cpmk'] ?? '-') ?></td></tr>
          <tr><th>Bobot</th><td><?= e($detail['bobot'] ?? '-') ?></td></tr>
          <tr><th>Teknik</th><td><?= e($detail['teknik'] ?? '-') ?></td></tr>
          <tr><th>Catatan</th><td><?= e($detail['catatan'] ?? '-') ?></td></tr>
        </table>
      </div>
      <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Tutup</button></div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
