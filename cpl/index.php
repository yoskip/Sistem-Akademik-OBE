<?php
require_once __DIR__ . '/../includes/auth.php';
$pdo = db();
$can = can_manage_master();
$domains = ['S' => 'Sikap (S)', 'P' => 'Pengetahuan (P)', 'KU' => 'Keterampilan Umum (KU)', 'KK' => 'Keterampilan Khusus (KK)'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$can) { http_response_code(403); die('Akses ditolak'); }
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $kode = strtoupper(trim($_POST['kode_cpl'] ?? ''));
        $desk = trim($_POST['deskripsi'] ?? '');
        $domain = $_POST['domain'] ?? '';
        if ($kode === '' || $desk === '' || !isset($domains[$domain])) {
            flash('danger', 'Kode, deskripsi, dan domain wajib diisi dengan benar.');
        } else {
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE cpl SET kode_cpl=?, deskripsi=?, domain=? WHERE id=?')->execute([$kode, $desk, $domain, $id]);
                    log_activity('update', 'cpl', "CPL $kode diperbarui");
                    flash('success', 'CPL berhasil diperbarui.');
                } else {
                    $pdo->prepare('INSERT INTO cpl (kode_cpl, deskripsi, domain) VALUES (?,?,?)')->execute([$kode, $desk, $domain]);
                    log_activity('create', 'cpl', "CPL $kode ditambahkan");
                    flash('success', 'CPL berhasil ditambahkan.');
                }
            } catch (PDOException $e) {
                flash('danger', 'Gagal menyimpan: kode CPL mungkin sudah dipakai.');
            }
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM cpl WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
        log_activity('delete', 'cpl', 'CPL dihapus');
        flash('success', 'CPL dihapus.');
    }
    redirect('index.php');
}

$q = trim($_GET['q'] ?? '');
$dom = trim($_GET['domain'] ?? '');
$where = [];
$params = [];
if ($q !== '') { $where[] = '(kode_cpl LIKE ? OR deskripsi LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($dom !== '') { $where[] = 'domain = ?'; $params[] = $dom; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$s = $pdo->prepare("SELECT COUNT(*) FROM cpl $whereSql");
$s->execute($params);
$total = (int)$s->fetchColumn();
[$limit, $offset, , $page, $pages] = paginate($total, 10);
$stmt = $pdo->prepare("SELECT * FROM cpl $whereSql ORDER BY kode_cpl LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$edit = null;
if (!empty($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM cpl WHERE id=?'); $s->execute([(int)$_GET['edit']]); $edit = $s->fetch();
}
$detail = null;
if (!empty($_GET['detail'])) {
    $s = $pdo->prepare('SELECT * FROM cpl WHERE id=?'); $s->execute([(int)$_GET['detail']]); $detail = $s->fetch();
}

$domainBadge = ['S' => 'success', 'P' => 'primary', 'KU' => 'warning', 'KK' => 'purple'];
$page_title = 'CPL Program Studi';
$page_subtitle = 'Capaian Pembelajaran Lulusan (CPL01 - CPL10)';
$page_scripts = '';
if ($edit) $page_scripts .= '<script>new bootstrap.Modal(document.getElementById("modalCpl")).show();</script>';
if ($detail) $page_scripts .= '<script>new bootstrap.Modal(document.getElementById("detailCpl")).show();</script>';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h2><i class="bi bi-bullseye me-1 text-primary"></i> Daftar CPL</h2>
  <div class="d-flex gap-2 flex-wrap">
    <form class="d-flex" method="get">
      <select name="domain" class="form-select form-select-sm me-1" data-autosubmit>
        <option value="">Semua Domain</option>
        <?php foreach ($domains as $k => $v): ?><option value="<?= $k ?>" <?= $dom === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select>
      <input type="text" name="q" value="<?= e($q) ?>" class="form-control form-control-sm search-box" placeholder="Cari CPL...">
      <button class="btn btn-sm btn-outline-secondary ms-1"><i class="bi bi-search"></i></button>
    </form>
    <?php if ($can): ?><a href="?edit=0" class="btn btn-sm btn-blue"><i class="bi bi-plus-lg"></i> Tambah CPL</a><?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th style="width:90px">Kode</th><th>Deskripsi CPL</th><th style="width:90px" class="text-center">Domain</th><th style="width:110px" class="text-center">Aksi</th></tr></thead>
        <tbody>
          <?php if (!$rows): ?><tr><td colspan="4"><div class="empty-state"><i class="bi bi-inbox"></i> Data tidak ditemukan.</div></td></tr><?php endif; ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><span class="badge bg-primary"><?= e($r['kode_cpl']) ?></span></td>
              <td><?= e($r['deskripsi']) ?></td>
              <td class="text-center"><span class="badge bg-<?= $domainBadge[$r['domain']] ?? 'secondary' ?>"><?= e($r['domain']) ?></span></td>
              <td class="text-center">
                <a class="btn btn-sm btn-outline-secondary" href="?detail=<?= $r['id'] ?>"><i class="bi bi-eye"></i></a>
                <?php if ($can): ?>
                  <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $r['id'] ?>"><i class="bi bi-pencil"></i></a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Hapus <?= e($r['kode_cpl']) ?>?')">
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
<div class="modal fade" id="modalCpl" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="modal-header"><h5 class="modal-title"><?= $edit ? 'Edit' : 'Tambah' ?> CPL</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Kode CPL <span class="text-danger">*</span></label>
            <input type="text" name="kode_cpl" class="form-control" value="<?= e($edit['kode_cpl'] ?? '') ?>" placeholder="CPL11" required>
          </div>
          <div class="col-md-8">
            <label class="form-label">Domain <span class="text-danger">*</span></label>
            <select name="domain" class="form-select" required>
              <option value="">-- Pilih domain --</option>
              <?php foreach ($domains as $k => $v): ?><option value="<?= $k ?>" <?= ($edit['domain'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Deskripsi CPL <span class="text-danger">*</span></label>
            <textarea name="deskripsi" class="form-control" rows="4" required><?= e($edit['deskripsi'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-navy"><i class="bi bi-save"></i> Simpan</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="modal fade" id="detailCpl" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Detail CPL</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <table class="table table-sm">
          <tr><th style="width:140px">Kode CPL</th><td><?= e($detail['kode_cpl'] ?? '') ?></td></tr>
          <tr><th>Domain</th><td><?= e($domains[$detail['domain'] ?? ''] ?? '-') ?></td></tr>
          <tr><th>Deskripsi</th><td><?= e($detail['deskripsi'] ?? '') ?></td></tr>
        </table>
      </div>
      <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Tutup</button></div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
