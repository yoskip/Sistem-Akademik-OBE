<?php
require_once __DIR__ . '/../includes/auth.php';
$pdo = db();
$can = can_manage_master();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$can) { http_response_code(403); die('Akses ditolak'); }
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $kode = strtoupper(trim($_POST['kode_bk'] ?? ''));
        $nama = trim($_POST['nama_bk'] ?? '');
        $desk = trim($_POST['deskripsi'] ?? '');
        $acuan = trim($_POST['acuan'] ?? '');
        if ($kode === '' || $nama === '') {
            flash('danger', 'Kode dan nama Bahan Kajian wajib diisi.');
        } else {
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE bahan_kajian SET kode_bk=?, nama_bk=?, deskripsi=?, acuan=? WHERE id=?')
                        ->execute([$kode, $nama, $desk ?: null, $acuan ?: null, $id]);
                    log_activity('update', 'bahan_kajian', "BK $kode diperbarui");
                    flash('success', 'Bahan Kajian diperbarui.');
                } else {
                    $pdo->prepare('INSERT INTO bahan_kajian (kode_bk, nama_bk, deskripsi, acuan) VALUES (?,?,?,?)')
                        ->execute([$kode, $nama, $desk ?: null, $acuan ?: null]);
                    log_activity('create', 'bahan_kajian', "BK $kode ditambahkan");
                    flash('success', 'Bahan Kajian ditambahkan.');
                }
            } catch (PDOException $e) {
                flash('danger', 'Gagal menyimpan: kode BK mungkin sudah dipakai.');
            }
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM bahan_kajian WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
        log_activity('delete', 'bahan_kajian', 'BK dihapus');
        flash('success', 'Bahan Kajian dihapus.');
    }
    redirect('index.php');
}

$q = trim($_GET['q'] ?? '');
$where = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE kode_bk LIKE ? OR nama_bk LIKE ? OR deskripsi LIKE ?';
    $params = ["%$q%", "%$q%", "%$q%"];
}
$s = $pdo->prepare("SELECT COUNT(*) FROM bahan_kajian $where"); $s->execute($params); $total = (int)$s->fetchColumn();
[$limit, $offset, , $page, $pages] = paginate($total, 12);
$stmt = $pdo->prepare("SELECT * FROM bahan_kajian $where ORDER BY kode_bk LIMIT $limit OFFSET $offset"); $stmt->execute($params); $rows = $stmt->fetchAll();

$edit = null;
if (!empty($_GET['edit'])) { $s = $pdo->prepare('SELECT * FROM bahan_kajian WHERE id=?'); $s->execute([(int)$_GET['edit']]); $edit = $s->fetch(); }
$detail = null;
if (!empty($_GET['detail'])) { $s = $pdo->prepare('SELECT * FROM bahan_kajian WHERE id=?'); $s->execute([(int)$_GET['detail']]); $detail = $s->fetch(); }

$page_title = 'Bahan Kajian';
$page_subtitle = 'Daftar Bahan Kajian Kurikulum';
$page_scripts = '';
if ($edit) $page_scripts .= '<script>new bootstrap.Modal(document.getElementById("modalBk")).show();</script>';
if ($detail) $page_scripts .= '<script>new bootstrap.Modal(document.getElementById("detailBk")).show();</script>';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h2><i class="bi bi-journal-bookmark me-1 text-primary"></i> Daftar Bahan Kajian</h2>
  <div class="d-flex gap-2">
    <form class="d-flex search-box" method="get">
      <input type="text" name="q" value="<?= e($q) ?>" class="form-control form-control-sm" placeholder="Cari bahan kajian...">
      <button class="btn btn-sm btn-outline-secondary ms-1"><i class="bi bi-search"></i></button>
    </form>
    <?php if ($can): ?><a href="?edit=0" class="btn btn-sm btn-blue"><i class="bi bi-plus-lg"></i> Tambah BK</a><?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th style="width:90px">Kode</th><th>Nama Bahan Kajian</th><th>Acuan</th><th style="width:110px" class="text-center">Aksi</th></tr></thead>
        <tbody>
          <?php if (!$rows): ?><tr><td colspan="4"><div class="empty-state"><i class="bi bi-inbox"></i> Data tidak ditemukan.</div></td></tr><?php endif; ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><span class="badge bg-primary"><?= e($r['kode_bk']) ?></span></td>
              <td><?= e($r['nama_bk']) ?><?php if ($r['deskripsi']): ?><div class="text-muted small"><?= e($r['deskripsi']) ?></div><?php endif; ?></td>
              <td><?= $r['acuan'] ? '<span class="badge bg-light text-dark border">' . e($r['acuan']) . '</span>' : '<span class="text-muted">-</span>' ?></td>
              <td class="text-center">
                <a class="btn btn-sm btn-outline-secondary" href="?detail=<?= $r['id'] ?>"><i class="bi bi-eye"></i></a>
                <?php if ($can): ?>
                  <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $r['id'] ?>"><i class="bi bi-pencil"></i></a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Hapus <?= e($r['kode_bk']) ?>?')">
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
<div class="modal fade" id="modalBk" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="modal-header"><h5 class="modal-title"><?= $edit ? 'Edit' : 'Tambah' ?> Bahan Kajian</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Kode BK <span class="text-danger">*</span></label><input type="text" name="kode_bk" class="form-control" value="<?= e($edit['kode_bk'] ?? '') ?>" placeholder="BK28" required></div>
          <div class="col-md-8"><label class="form-label">Acuan (opsional)</label><input type="text" name="acuan" class="form-control" value="<?= e($edit['acuan'] ?? '') ?>" placeholder="CC2020"></div>
          <div class="col-12"><label class="form-label">Nama Bahan Kajian <span class="text-danger">*</span></label><input type="text" name="nama_bk" class="form-control" value="<?= e($edit['nama_bk'] ?? '') ?>" required></div>
          <div class="col-12"><label class="form-label">Deskripsi (opsional)</label><textarea name="deskripsi" class="form-control" rows="3"><?= e($edit['deskripsi'] ?? '') ?></textarea></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-navy"><i class="bi bi-save"></i> Simpan</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="modal fade" id="detailBk" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Detail Bahan Kajian</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <table class="table table-sm">
          <tr><th style="width:120px">Kode BK</th><td><?= e($detail['kode_bk'] ?? '') ?></td></tr>
          <tr><th>Nama</th><td><?= e($detail['nama_bk'] ?? '') ?></td></tr>
          <tr><th>Acuan</th><td><?= e($detail['acuan'] ?? '-') ?></td></tr>
          <tr><th>Deskripsi</th><td><?= e($detail['deskripsi'] ?? '-') ?></td></tr>
        </table>
      </div>
      <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Tutup</button></div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
