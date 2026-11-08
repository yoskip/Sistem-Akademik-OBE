<?php
require_once __DIR__ . '/../includes/auth.php';
$pdo = db();
$can = can_manage_master();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$can) { http_response_code(403); die('Akses ditolak'); }
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $versionId = (int)($_POST['version_id'] ?? 0);
        $cplId = (int)($_POST['cpl_id'] ?? 0);
        $mkId = (int)($_POST['mk_id'] ?? 0);
        $semester = trim($_POST['semester'] ?? '');
        $ket = trim($_POST['keterangan'] ?? '');
        if (!$versionId || !$cplId || !$mkId || $semester === '') {
            flash('danger', 'Versi, CPL, Mata Kuliah, dan Semester wajib diisi.');
        } else {
            $k = $pdo->prepare('SELECT kode_mk FROM mata_kuliah WHERE id=?');
            $k->execute([$mkId]);
            $raw = (string)$k->fetchColumn();
            $pdo->prepare('INSERT INTO peta_pemenuhan_cpl (version_id, cpl_id, semester, kode_mk_raw, mk_id, keterangan) VALUES (?,?,?,?,?,?)')
                ->execute([$versionId, $cplId, $semester, $raw, $mkId, $ket ?: null]);
            log_activity('create', 'pemetaan', "Peta CPL ditambahkan ($raw / $semester)");
            flash('success', 'Baris peta pemenuhan ditambahkan.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM peta_pemenuhan_cpl WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
        log_activity('delete', 'pemetaan', 'Baris peta pemenuhan dihapus');
        flash('success', 'Baris dihapus.');
    }
    redirect('peta_cpl.php?versi=' . (int)($_POST['version_id'] ?? 1));
}

$versions = $pdo->query('SELECT * FROM pemetaan_cpl_versions ORDER BY id')->fetchAll();
$cpls = $pdo->query('SELECT * FROM cpl ORDER BY kode_cpl')->fetchAll();
$mks = $pdo->query('SELECT * FROM mata_kuliah ORDER BY CAST(semester AS UNSIGNED), kode_mk')->fetchAll();
$verId = (int)($_GET['versi'] ?? ($versions[0]['id'] ?? 0));
$cplFilter = (int)($_GET['cpl'] ?? 0);

$where = ['x.version_id = ?'];
$params = [$verId];
if ($cplFilter) { $where[] = 'x.cpl_id = ?'; $params[] = $cplFilter; }
$whereSql = 'WHERE ' . implode(' AND ', $where);
$s = $pdo->prepare("SELECT COUNT(*) FROM peta_pemenuhan_cpl x $whereSql");
$s->execute($params); $total = (int)$s->fetchColumn();
[$limit, $offset, , $page, $pages] = paginate($total, 20);
$stmt = $pdo->prepare("SELECT x.*, c.kode_cpl, m.nama_mk FROM peta_pemenuhan_cpl x
    JOIN cpl c ON c.id = x.cpl_id LEFT JOIN mata_kuliah m ON m.id = x.mk_id
    $whereSql ORDER BY c.kode_cpl, CAST(x.semester AS UNSIGNED), x.kode_mk_raw LIMIT $limit OFFSET $offset");
$stmt->execute($params); $rows = $stmt->fetchAll();

$page_title = 'Peta Pemenuhan CPL';
$page_subtitle = 'Sebaran mata kuliah pemenuhan CPL per semester';
$page_scripts = '';
if (isset($_GET['add'])) $page_scripts = '<script>new bootstrap.Modal(document.getElementById("modalAdd")).show();</script>';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h2><i class="bi bi-table me-1 text-primary"></i> Peta Pemenuhan CPL</h2>
  <div class="d-flex gap-2 flex-wrap align-items-center">
    <form class="d-flex align-items-center" method="get">
      <select name="versi" class="form-select form-select-sm me-1" data-autosubmit>
        <?php foreach ($versions as $v): ?><option value="<?= $v['id'] ?>" <?= $verId === (int)$v['id'] ? 'selected' : '' ?>><?= e($v['versi']) ?></option><?php endforeach; ?>
      </select>
      <select name="cpl" class="form-select form-select-sm me-1" data-autosubmit>
        <option value="0">Semua CPL</option>
        <?php foreach ($cpls as $c): ?><option value="<?= $c['id'] ?>" <?= $cplFilter === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['kode_cpl']) ?></option><?php endforeach; ?>
      </select>
    </form>
    <?php if ($can): ?><a href="?versi=<?= $verId ?>&add=1" class="btn btn-sm btn-blue"><i class="bi bi-plus-lg"></i> Tambah</a><?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>CPL</th><th class="text-center">Semester</th><th>Mata Kuliah</th><th>Keterangan</th><th style="width:70px" class="text-center">Aksi</th></tr></thead>
        <tbody>
          <?php if (!$rows): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-inbox"></i> Belum ada data pada versi ini.</div></td></tr><?php endif; ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><span class="badge bg-primary"><?= e($r['kode_cpl']) ?></span></td>
              <td class="text-center"><?= e($r['semester']) ?></td>
              <td><b><?= e($r['kode_mk_raw']) ?></b><?php if ($r['nama_mk']): ?> <span class="small text-muted"><?= e($r['nama_mk']) ?></span><?php endif; ?></td>
              <td class="small text-muted"><?= e($r['keterangan'] ?? '-') ?></td>
              <td class="text-center">
                <?php if ($can): ?>
                  <form method="post" onsubmit="return confirm('Hapus baris ini?')">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="version_id" value="<?= $verId ?>">
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
    <span class="text-muted small">Total <?= $total ?> baris</span><?= render_pagination($page, $pages) ?>
  </div>
</div>

<?php if ($can): ?>
<div class="modal fade" id="modalAdd" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save">
      <div class="modal-header"><h5 class="modal-title">Tambah Baris Peta CPL</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Versi</label><select name="version_id" class="form-select" required><?php foreach ($versions as $v): ?><option value="<?= $v['id'] ?>" <?= $verId === (int)$v['id'] ? 'selected' : '' ?>><?= e($v['versi']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label">CPL</label><select name="cpl_id" class="form-select" required><option value="">-- pilih --</option><?php foreach ($cpls as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['kode_cpl']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label">Mata Kuliah</label><select name="mk_id" class="form-select" required><option value="">-- pilih --</option><?php foreach ($mks as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['kode_mk']) ?> - <?= e($m['nama_mk']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label">Semester</label><input type="text" name="semester" class="form-control" placeholder="1" required></div>
        <div class="mb-0"><label class="form-label">Keterangan</label><input type="text" name="keterangan" class="form-control"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-navy"><i class="bi bi-save"></i> Simpan</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
