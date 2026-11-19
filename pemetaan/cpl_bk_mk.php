<?php
require_once __DIR__ . '/../includes/auth.php';
$pdo = db();
$can = can_manage_master();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$can) { http_response_code(403); die('Akses ditolak'); }
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $cplId = (int)($_POST['cpl_id'] ?? 0);
        $bkId = (int)($_POST['bahan_kajian_id'] ?? 0);
        $mkId = (int)($_POST['mk_id'] ?? 0);
        if (!validate_id($cplId) || !validate_id($bkId) || !validate_id($mkId)) {
            http_response_code(422);
            flash('danger', 'CPL, Bahan Kajian, dan Mata Kuliah wajib dipilih dengan ID valid.');
            redirect('cpl_bk_mk.php');
        }

        $kode = $pdo->prepare('SELECT kode_mk FROM mata_kuliah WHERE id=?');
        $kode->execute([$mkId]);
        $raw = (string)$kode->fetchColumn();
        if (!$raw) {
            http_response_code(422);
            flash('danger', 'Mata kuliah tidak ditemukan.');
            redirect('cpl_bk_mk.php');
        }

        $pdo->prepare('INSERT INTO cpl_bk_mk (cpl_id, bahan_kajian_id, mk_id, kode_mk_raw) VALUES (?,?,?,?)')
            ->execute([$cplId, $bkId, $mkId, $raw]);
        log_activity('create', 'pemetaan', "CPL-BK-MK ditambahkan ($raw)");
        flash('success', 'Relasi CPL - BK - MK ditambahkan.');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (!validate_id($id)) {
            http_response_code(422);
            flash('danger', 'ID relasi tidak valid.');
            redirect('cpl_bk_mk.php');
        }
        $pdo->prepare('DELETE FROM cpl_bk_mk WHERE id=?')->execute([$id]);
        log_activity('delete', 'pemetaan', 'Relasi CPL-BK-MK dihapus');
        flash('success', 'Relasi dihapus.');
    }
    redirect('cpl_bk_mk.php');
}

$q = trim($_GET['q'] ?? '');
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(c.kode_cpl LIKE ? OR b.kode_bk LIKE ? OR b.nama_bk LIKE ? OR m.nama_mk LIKE ? OR x.kode_mk_raw LIKE ?)';
    $params = array_fill(0, 5, "%$q%");
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$fromSql = "FROM cpl_bk_mk x
        JOIN cpl c ON c.id = x.cpl_id
        JOIN bahan_kajian b ON b.id = x.bahan_kajian_id
        LEFT JOIN mata_kuliah m ON m.id = x.mk_id
        $whereSql";
$s = $pdo->prepare("SELECT COUNT(*) $fromSql"); $s->execute($params); $total = (int)$s->fetchColumn();
[$limit, $offset, , $page, $pages] = paginate($total, 15);
$stmt = $pdo->prepare("SELECT x.*, c.kode_cpl, b.kode_bk, b.nama_bk, m.nama_mk, m.kode_mk $fromSql ORDER BY c.kode_cpl, b.kode_bk, x.kode_mk_raw LIMIT $limit OFFSET $offset");
$stmt->execute($params); $rows = $stmt->fetchAll();
$cpls = $pdo->query('SELECT * FROM cpl ORDER BY kode_cpl')->fetchAll();
$bks = $pdo->query('SELECT * FROM bahan_kajian ORDER BY kode_bk')->fetchAll();
$mks = $pdo->query('SELECT * FROM mata_kuliah ORDER BY CAST(semester AS UNSIGNED), kode_mk')->fetchAll();

$page_title = 'Pemetaan CPL - BK - MK';
$page_subtitle = 'Keterkaitan CPL, Bahan Kajian, dan Mata Kuliah';
$page_scripts = '<script>new bootstrap.Modal(document.getElementById("modalAdd")).show();</script>';
if (empty($_GET['add']) && !isset($_GET['add'])) $page_scripts = '';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h2><i class="bi bi-diagram-3 me-1 text-primary"></i> CPL &harr; BK &harr; MK</h2>
  <div class="d-flex gap-2">
    <form class="d-flex search-box" method="get">
      <input type="text" name="q" value="<?= e($q) ?>" class="form-control form-control-sm" placeholder="Cari CPL / BK / MK...">
      <button class="btn btn-sm btn-outline-secondary ms-1"><i class="bi bi-search"></i></button>
    </form>
    <?php if ($can): ?><a href="?add=1" class="btn btn-sm btn-blue"><i class="bi bi-plus-lg"></i> Tambah Relasi</a><?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>CPL</th><th>Bahan Kajian</th><th>Mata Kuliah</th><th style="width:80px" class="text-center">Aksi</th></tr></thead>
        <tbody>
          <?php if (!$rows): ?><tr><td colspan="4"><div class="empty-state"><i class="bi bi-inbox"></i> Data tidak ditemukan.</div></td></tr><?php endif; ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><span class="badge bg-primary"><?= e($r['kode_cpl']) ?></span></td>
              <td><span class="badge bg-light text-dark border"><?= e($r['kode_bk']) ?></span> <span class="small"><?= e($r['nama_bk']) ?></span></td>
              <td><b><?= e($r['kode_mk_raw']) ?></b><?php if ($r['nama_mk']): ?> <span class="small text-muted"><?= e($r['nama_mk']) ?></span><?php endif; ?></td>
              <td class="text-center">
                <?php if ($can): ?>
                  <form method="post" onsubmit="return confirm('Hapus relasi ini?')">
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
    <span class="text-muted small">Total <?= $total ?> relasi</span><?= render_pagination($page, $pages) ?>
  </div>
</div>

<?php if ($can): ?>
<div class="modal fade" id="modalAdd" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save">
      <div class="modal-header"><h5 class="modal-title">Tambah Relasi CPL - BK - MK</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">CPL</label><select name="cpl_id" class="form-select" required><option value="">-- pilih --</option><?php foreach ($cpls as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['kode_cpl']) ?> - <?= e(mb_strimwidth($c['deskripsi'], 0, 70, '...')) ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label">Bahan Kajian</label><select name="bahan_kajian_id" class="form-select" required><option value="">-- pilih --</option><?php foreach ($bks as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['kode_bk']) ?> - <?= e($b['nama_bk']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-0"><label class="form-label">Mata Kuliah</label><select name="mk_id" class="form-select" required><option value="">-- pilih --</option><?php foreach ($mks as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['kode_mk']) ?> - <?= e($m['nama_mk']) ?> (Smt <?= e($m['semester']) ?>)</option><?php endforeach; ?></select></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-navy"><i class="bi bi-save"></i> Simpan</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
