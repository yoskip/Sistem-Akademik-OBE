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
        $kode = strtoupper(trim($_POST['kode_pl'] ?? ''));
        $nama = trim($_POST['nama_pl'] ?? '');
        $desk = trim($_POST['deskripsi'] ?? '');
        if (!validate_code($kode, 2, 30)) {
            http_response_code(422);
            flash('danger', 'Format kode PL tidak sesuai (harus 2-30 karakter alfanumerik/tanda hubung).');
        } elseif (!validate_name($nama, 2, 100)) {
            http_response_code(422);
            flash('danger', 'Nama PL harus diisi dengan format yang benar (2-100 karakter).');
        } elseif ($desk !== '' && !validate_description($desk, 1, 1000)) {
            http_response_code(422);
            flash('danger', 'Deskripsi maksimal 1000 karakter.');
        } elseif ($id !== 0 && !validate_id($id)) {
            http_response_code(422);
            flash('danger', 'ID data tidak valid.');
        } else {
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE profil_lulusan SET kode_pl=?, nama_pl=?, deskripsi=? WHERE id=?')
                        ->execute([$kode, $nama, $desk ?: null, $id]);
                    log_activity('update', 'profil_lulusan', "PL $kode diperbarui");
                    flash('success', 'Profil Lulusan berhasil diperbarui.');
                } else {
                    $pdo->prepare('INSERT INTO profil_lulusan (kode_pl, nama_pl, deskripsi) VALUES (?,?,?)')
                        ->execute([$kode, $nama, $desk ?: null]);
                    log_activity('create', 'profil_lulusan', "PL $kode ditambahkan");
                    flash('success', 'Profil Lulusan berhasil ditambahkan.');
                }
            } catch (PDOException $e) {
                flash('danger', 'Gagal menyimpan: kode PL mungkin sudah dipakai.');
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        if (!validate_id($id)) {
            http_response_code(422);
            flash('danger', 'ID data tidak valid.');
        } else {
            $pdo->prepare('DELETE FROM profil_lulusan WHERE id=?')->execute([(int)$id]);
            log_activity('delete', 'profil_lulusan', "PL id $id dihapus");
            flash('success', 'Profil Lulusan dihapus.');
        }
    }
    redirect('index.php');
}

$q = trim($_GET['q'] ?? '');
$where = '';
$params = [];
if ($q !== '') {
    $q = mb_substr($q, 0, 100);
    $where = 'WHERE kode_pl LIKE ? OR nama_pl LIKE ? OR deskripsi LIKE ?';
    $params = ["%$q%", "%$q%", "%$q%"];
}
$total = (int)(function () use ($pdo, $where, $params) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM profil_lulusan $where");
    $s->execute($params);
    return $s->fetchColumn();
})();
[$limit, $offset, , $page, $pages] = paginate($total, 10);
$stmt = $pdo->prepare("SELECT * FROM profil_lulusan $where ORDER BY kode_pl LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$edit = null;
if (!empty($_GET['edit']) && $_GET['edit'] !== '0') {
    if (validate_id($_GET['edit'])) {
        $s = $pdo->prepare('SELECT * FROM profil_lulusan WHERE id=?');
        $s->execute([(int)$_GET['edit']]);
        $edit = $s->fetch();
    }
}
$detail = null;
if (!empty($_GET['detail'])) {
    if (validate_id($_GET['detail'])) {
        $s = $pdo->prepare('SELECT * FROM profil_lulusan WHERE id=?');
        $s->execute([(int)$_GET['detail']]);
        $detail = $s->fetch();
    }
}

$page_title = 'Profil Lulusan';
$page_subtitle = 'Data Profil Lulusan (PL) Program Studi';
$page_scripts = '';
if ($edit) $page_scripts .= '<script>new bootstrap.Modal(document.getElementById("modalPl")).show();</script>';
if ($detail) $page_scripts .= '<script>new bootstrap.Modal(document.getElementById("detailPl")).show();</script>';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h2><i class="bi bi-person-badge me-1 text-primary"></i> Daftar Profil Lulusan</h2>
  <div class="d-flex gap-2">
    <form class="d-flex search-box" method="get">
      <input type="text" name="q" value="<?= e($q) ?>" class="form-control form-control-sm" placeholder="Cari kode / nama / deskripsi...">
      <button class="btn btn-sm btn-outline-secondary ms-1"><i class="bi bi-search"></i></button>
    </form>
    <?php if ($can): ?>
      <a href="?edit=0" class="btn btn-sm btn-blue"><i class="bi bi-plus-lg"></i> Tambah PL</a>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr><th style="width:90px">Kode</th><th>Profil Lulusan</th><th style="width:110px" class="text-center">Aksi</th></tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="3"><div class="empty-state"><i class="bi bi-inbox"></i> Data tidak ditemukan.</div></td></tr>
          <?php endif; ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><span class="badge bg-primary"><?= e($r['kode_pl']) ?></span></td>
              <td><?= e($r['nama_pl']) ?><?php if ($r['deskripsi']): ?><div class="text-muted small"><?= e($r['deskripsi']) ?></div><?php endif; ?></td>
              <td class="text-center">
                <a class="btn btn-sm btn-outline-secondary" href="?detail=<?= $r['id'] ?>" title="Detail"><i class="bi bi-eye"></i></a>
                <?php if ($can): ?>
                  <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $r['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Hapus <?= e($r['kode_pl']) ?>?')">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
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
    <span class="text-muted small">Total <?= $total ?> data</span>
    <?= render_pagination($page, $pages) ?>
  </div>
</div>

<?php if ($can): ?>
<div class="modal fade" id="modalPl" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="modal-header">
        <h5 class="modal-title"><?= $edit ? 'Edit' : 'Tambah' ?> Profil Lulusan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Kode PL <span class="text-danger">*</span></label>
          <input type="text" name="kode_pl" class="form-control" value="<?= e($edit['kode_pl'] ?? '') ?>" placeholder="PL05" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Nama / Rumusan Profil Lulusan <span class="text-danger">*</span></label>
          <textarea name="nama_pl" class="form-control" rows="4" required><?= e($edit['nama_pl'] ?? '') ?></textarea>
        </div>
        <div class="mb-0">
          <label class="form-label">Deskripsi (opsional)</label>
          <textarea name="deskripsi" class="form-control" rows="2"><?= e($edit['deskripsi'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button class="btn btn-navy"><i class="bi bi-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="modal fade" id="detailPl" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Detail Profil Lulusan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <table class="table table-sm">
          <tr><th style="width:150px">Kode PL</th><td><?= e($detail['kode_pl'] ?? '') ?></td></tr>
          <tr><th>Nama / Rumusan</th><td><?= e($detail['nama_pl'] ?? '') ?></td></tr>
          <tr><th>Deskripsi</th><td><?= e($detail['deskripsi'] ?? '-') ?></td></tr>
        </table>
      </div>
      <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Tutup</button></div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
