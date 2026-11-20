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
        $kode = strtoupper(trim($_POST['kode_cpmk'] ?? ''));
        $cplId = (int)($_POST['cpl_id'] ?? 0);
        $desk = trim($_POST['deskripsi'] ?? '');
        if (!validate_code($kode, 2, 30)) {
            http_response_code(422);
            flash('danger', 'Format kode CPMK tidak sesuai (harus 2-30 karakter alfanumerik/tanda hubung).');
        } elseif (!validate_id($cplId)) {
            http_response_code(422);
            flash('danger', 'Pilihan CPL tidak valid.');
        } elseif (!validate_description($desk, 1, 1000)) {
            http_response_code(422);
            flash('danger', 'Deskripsi CPMK wajib diisi (1-1000 karakter).');
        } elseif ($id !== 0 && !validate_id($id)) {
            http_response_code(422);
            flash('danger', 'ID data tidak valid.');
        } else {
            try {
                $pdo->beginTransaction();
                if ($id > 0) {
                    $pdo->prepare('UPDATE cpmk SET kode_cpmk=?, cpl_id=?, deskripsi=? WHERE id=?')->execute([$kode, $cplId, $desk, $id]);
                    $pdo->prepare('DELETE FROM cpl_cpmk WHERE cpmk_id=?')->execute([$id]);
                    $pdo->prepare('INSERT IGNORE INTO cpl_cpmk (cpl_id, cpmk_id) VALUES (?,?)')->execute([$cplId, $id]);
                    log_activity('update', 'cpmk', "CPMK $kode diperbarui");
                    flash('success', 'CPMK berhasil diperbarui.');
                } else {
                    $pdo->prepare('INSERT INTO cpmk (kode_cpmk, cpl_id, deskripsi) VALUES (?,?,?)')->execute([$kode, $cplId, $desk]);
                    $newId = (int)$pdo->lastInsertId();
                    $pdo->prepare('INSERT IGNORE INTO cpl_cpmk (cpl_id, cpmk_id) VALUES (?,?)')->execute([$cplId, $newId]);
                    log_activity('create', 'cpmk', "CPMK $kode ditambahkan");
                    flash('success', 'CPMK berhasil ditambahkan.');
                }
                $pdo->commit();
            } catch (PDOException $e) { obe_log_database_failure($e);
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash('danger', 'Gagal menyimpan: kode CPMK mungkin sudah dipakai.');
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        if (!validate_id($id)) {
            http_response_code(422);
            flash('danger', 'ID data tidak valid.');
        } else {
            try {
                $pdo->prepare('DELETE FROM cpmk WHERE id=?')->execute([(int)$id]);
                log_activity('delete', 'cpmk', 'CPMK dihapus');
                flash('success', 'CPMK dihapus.');
            } catch (PDOException $e) { obe_log_database_failure($e);
                flash('danger', 'CPMK tidak dapat dihapus karena masih direferensikan data lain.');
            }
        }
    }
    redirect('index.php');
}

$q = trim($_GET['q'] ?? '');
$cplFilter = (int)($_GET['cpl'] ?? 0);
$where = [];
$params = [];
if ($q !== '') {
    $q = mb_substr($q, 0, 100);
    $where[] = '(k.kode_cpmk LIKE ? OR k.deskripsi LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($cplFilter && validate_id($cplFilter)) {
    $where[] = 'k.cpl_id = ?';
    $params[] = $cplFilter;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$s = $pdo->prepare("SELECT COUNT(*) FROM cpmk k $whereSql"); $s->execute($params); $total = (int)$s->fetchColumn();
[$limit, $offset, , $page, $pages] = paginate($total, 12);
$stmt = $pdo->prepare("SELECT k.*, c.kode_cpl,
        (SELECT COUNT(*) FROM cpmk_mk x WHERE x.cpmk_id=k.id) AS jml_mk
    FROM cpmk k JOIN cpl c ON c.id=k.cpl_id $whereSql ORDER BY k.kode_cpmk LIMIT $limit OFFSET $offset");
$stmt->execute($params); $rows = $stmt->fetchAll();
$cpls = $pdo->query('SELECT * FROM cpl ORDER BY kode_cpl')->fetchAll();

$edit = null;
if (!empty($_GET['edit']) && $_GET['edit'] !== '0') {
    if (validate_id($_GET['edit'])) {
        $s = $pdo->prepare('SELECT * FROM cpmk WHERE id=?');
        $s->execute([(int)$_GET['edit']]);
        $edit = $s->fetch();
    }
}
$detail = null;
if (!empty($_GET['detail'])) {
    if (validate_id($_GET['detail'])) {
        $s = $pdo->prepare('SELECT k.*, c.kode_cpl FROM cpmk k JOIN cpl c ON c.id=k.cpl_id WHERE k.id=?');
        $s->execute([(int)$_GET['detail']]);
        $detail = $s->fetch();
        if ($detail) {
            $st = $pdo->prepare('SELECT m.kode_mk, m.nama_mk, m.semester FROM cpmk_mk x JOIN mata_kuliah m ON m.id=x.mk_id WHERE x.cpmk_id=? ORDER BY m.semester, m.kode_mk');
            $st->execute([$detail['id']]);
            $detail['mk'] = $st->fetchAll();
        }
    }
}

$page_title = 'CPMK';
$page_subtitle = 'Capaian Pembelajaran Mata Kuliah';
$page_scripts = '';
if ($edit) $page_scripts .= '<script>new bootstrap.Modal(document.getElementById("modalCpmk")).show();</script>';
if ($detail) $page_scripts .= '<script>new bootstrap.Modal(document.getElementById("detailCpmk")).show();</script>';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h2><i class="bi bi-check2-circle me-1 text-primary"></i> Daftar CPMK</h2>
  <div class="d-flex gap-2 flex-wrap">
    <form class="d-flex" method="get">
      <select name="cpl" class="form-select form-select-sm me-1" data-autosubmit>
        <option value="0">Semua CPL</option>
        <?php foreach ($cpls as $c): ?><option value="<?= $c['id'] ?>" <?= $cplFilter === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['kode_cpl']) ?></option><?php endforeach; ?>
      </select>
      <input type="text" name="q" value="<?= e($q) ?>" class="form-control form-control-sm search-box" placeholder="Cari CPMK...">
      <button class="btn btn-sm btn-outline-secondary ms-1"><i class="bi bi-search"></i></button>
    </form>
    <?php if ($can): ?><a href="?edit=0" class="btn btn-sm btn-blue"><i class="bi bi-plus-lg"></i> Tambah CPMK</a><?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th style="width:110px">Kode</th><th style="width:80px">CPL</th><th>Deskripsi</th><th class="text-center" style="width:80px">MK</th><th style="width:110px" class="text-center">Aksi</th></tr></thead>
        <tbody>
          <?php if (!$rows): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-inbox"></i> Data tidak ditemukan.</div></td></tr><?php endif; ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><b><?= e($r['kode_cpmk']) ?></b></td>
              <td><span class="badge bg-primary"><?= e($r['kode_cpl']) ?></span></td>
              <td class="small"><?= e($r['deskripsi']) ?></td>
              <td class="text-center"><span class="badge bg-light text-dark border"><?= (int)$r['jml_mk'] ?></span></td>
              <td class="text-center">
                <a class="btn btn-sm btn-outline-secondary" href="?detail=<?= $r['id'] ?>"><i class="bi bi-eye"></i></a>
                <?php if ($can): ?>
                  <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $r['id'] ?>"><i class="bi bi-pencil"></i></a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Hapus <?= e($r['kode_cpmk']) ?>?')">
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
<div class="modal fade" id="modalCpmk" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="modal-header"><h5 class="modal-title"><?= $edit ? 'Edit' : 'Tambah' ?> CPMK</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Kode CPMK <span class="text-danger">*</span></label><input type="text" name="kode_cpmk" class="form-control" value="<?= e($edit['kode_cpmk'] ?? '') ?>" required></div>
          <div class="col-md-8"><label class="form-label">CPL <span class="text-danger">*</span></label><select name="cpl_id" class="form-select" required><option value="">-- pilih CPL --</option><?php foreach ($cpls as $c): ?><option value="<?= $c['id'] ?>" <?= (int)($edit['cpl_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['kode_cpl']) ?> - <?= e(mb_strimwidth($c['deskripsi'], 0, 60, '...')) ?></option><?php endforeach; ?></select></div>
          <div class="col-12"><label class="form-label">Deskripsi CPMK <span class="text-danger">*</span></label><textarea name="deskripsi" class="form-control" rows="4" required><?= e($edit['deskripsi'] ?? '') ?></textarea></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-navy"><i class="bi bi-save"></i> Simpan</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="modal fade" id="detailCpmk" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Detail CPMK</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <table class="table table-sm">
          <tr><th style="width:130px">Kode</th><td><?= e($detail['kode_cpmk'] ?? '') ?></td></tr>
          <tr><th>CPL</th><td><?= e($detail['kode_cpl'] ?? '') ?></td></tr>
          <tr><th>Deskripsi</th><td><?= e($detail['deskripsi'] ?? '') ?></td></tr>
          <tr><th>Mata Kuliah</th><td><?= !empty($detail['mk']) ? implode('<br>', array_map(fn($m) => e($m['kode_mk'] . ' - ' . $m['nama_mk'] . ' (Smt ' . $m['semester'] . ')'), $detail['mk'])) : '-' ?></td></tr>
        </table>
      </div>
      <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Tutup</button></div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
