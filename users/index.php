<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);
$pdo = db();
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama'] ?? '');
        $username = strtolower(trim($_POST['username'] ?? ''));
        $email = trim($_POST['email'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $password = (string)($_POST['password'] ?? '');
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        if ($nama === '' || $username === '' || !$roleId) {
            flash('danger', 'Nama, username, dan role wajib diisi.');
        } elseif ($id === 0 && strlen($password) < 6) {
            flash('danger', 'Password minimal 6 karakter.');
        } else {
            try {
                if ($id > 0) {
                    if ($id === (int)$me['id']) { $aktif = 1; }
                    if ($password !== '') {
                        $pdo->prepare('UPDATE users SET nama=?, username=?, email=?, password=?, role_id=?, aktif=? WHERE id=?')
                            ->execute([$nama, $username, $email ?: null, hash_password($password), $roleId, $aktif, $id]);
                    } else {
                        $pdo->prepare('UPDATE users SET nama=?, username=?, email=?, role_id=?, aktif=? WHERE id=?')
                            ->execute([$nama, $username, $email ?: null, $roleId, $aktif, $id]);
                    }
                    log_activity('update', 'users', "Pengguna $username diperbarui");
                    flash('success', 'Pengguna diperbarui.');
                } else {
                    $pdo->prepare('INSERT INTO users (nama, username, email, password, role_id, aktif) VALUES (?,?,?,?,?,?)')
                        ->execute([$nama, $username, $email ?: null, hash_password($password), $roleId, $aktif]);
                    log_activity('create', 'users', "Pengguna $username ditambahkan");
                    flash('success', 'Pengguna ditambahkan.');
                }
            } catch (PDOException $e) {
                flash('danger', 'Gagal menyimpan: username mungkin sudah dipakai.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$me['id']) {
            flash('danger', 'Anda tidak dapat menghapus akun sendiri.');
        } else {
            $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
            log_activity('delete', 'users', "Pengguna id $id dihapus");
            flash('success', 'Pengguna dihapus.');
        }
    }
    redirect('index.php');
}

$q = trim($_GET['q'] ?? '');
$where = '';
$params = [];
if ($q !== '') { $where = 'WHERE u.nama LIKE ? OR u.username LIKE ? OR u.email LIKE ?'; $params = array_fill(0, 3, "%$q%"); }
$s = $pdo->prepare("SELECT COUNT(*) FROM users u $where"); $s->execute($params); $total = (int)$s->fetchColumn();
[$limit, $offset, , $page, $pages] = paginate($total, 10);
$stmt = $pdo->prepare("SELECT u.*, r.nama AS role FROM users u JOIN roles r ON r.id=u.role_id $where ORDER BY u.nama LIMIT $limit OFFSET $offset");
$stmt->execute($params); $rows = $stmt->fetchAll();
$roles = $pdo->query('SELECT * FROM roles ORDER BY id')->fetchAll();

$edit = null;
if (!empty($_GET['edit'])) { $s = $pdo->prepare('SELECT * FROM users WHERE id=?'); $s->execute([(int)$_GET['edit']]); $edit = $s->fetch(); }

$page_title = 'Manajemen Pengguna';
$page_subtitle = 'Kelola akun dan hak akses pengguna';
$page_scripts = $edit ? '<script>new bootstrap.Modal(document.getElementById("modalUser")).show();</script>' : '';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h2><i class="bi bi-people me-1 text-primary"></i> Manajemen Pengguna</h2>
  <div class="d-flex gap-2">
    <form class="d-flex search-box" method="get">
      <input type="text" name="q" value="<?= e($q) ?>" class="form-control form-control-sm" placeholder="Cari nama / username...">
      <button class="btn btn-sm btn-outline-secondary ms-1"><i class="bi bi-search"></i></button>
    </form>
    <a href="?edit=0" class="btn btn-sm btn-blue"><i class="bi bi-plus-lg"></i> Tambah Pengguna</a>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead><tr><th>Nama</th><th>Username</th><th>Email</th><th class="text-center">Role</th><th class="text-center">Status</th><th style="width:110px" class="text-center">Aksi</th></tr></thead>
        <tbody>
          <?php if (!$rows): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-inbox"></i> Data tidak ditemukan.</div></td></tr><?php endif; ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><div class="d-flex align-items-center gap-2"><div class="avatar sm"><?= e(strtoupper(substr($r['nama'], 0, 1))) ?></div><b><?= e($r['nama']) ?></b><?php if ((int)$r['id'] === (int)$me['id']): ?><span class="badge bg-light text-dark border">Anda</span><?php endif; ?></div></td>
              <td><?= e($r['username']) ?></td>
              <td class="small text-muted"><?= e($r['email'] ?? '-') ?></td>
              <td class="text-center"><span class="badge bg-<?= badge_for_role($r['role']) ?>"><?= e(ucfirst($r['role'])) ?></span></td>
              <td class="text-center"><?= $r['aktif'] ? '<span class="status-chip status-lengkap">Aktif</span>' : '<span class="status-chip status-belum">Nonaktif</span>' ?></td>
              <td class="text-center">
                <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $r['id'] ?>"><i class="bi bi-pencil"></i></a>
                <?php if ((int)$r['id'] !== (int)$me['id']): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('Hapus pengguna <?= e($r['username']) ?>?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center"><span class="text-muted small">Total <?= $total ?> pengguna</span><?= render_pagination($page, $pages) ?></div>
</div>

<div class="modal fade" id="modalUser" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="modal-header"><h5 class="modal-title"><?= $edit ? 'Edit' : 'Tambah' ?> Pengguna</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Nama Lengkap <span class="text-danger">*</span></label><input type="text" name="nama" class="form-control" value="<?= e($edit['nama'] ?? '') ?>" required></div>
        <div class="mb-3"><label class="form-label">Username <span class="text-danger">*</span></label><input type="text" name="username" class="form-control" value="<?= e($edit['username'] ?? '') ?>" required></div>
        <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($edit['email'] ?? '') ?>"></div>
        <div class="mb-3"><label class="form-label">Role <span class="text-danger">*</span></label><select name="role_id" class="form-select" required><option value="">-- pilih --</option><?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>" <?= (int)($edit['role_id'] ?? 0) === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['label']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label">Password <?= $edit ? '<span class="text-muted small">(kosongkan jika tidak diubah)</span>' : '<span class="text-danger">*</span>' ?></label><input type="password" name="password" class="form-control" <?= $edit ? '' : 'required' ?> minlength="6"></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="aktif" id="aktif" value="1" <?= (!$edit || $edit['aktif']) ? 'checked' : '' ?>><label class="form-check-label" for="aktif">Aktif</label></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-navy"><i class="bi bi-save"></i> Simpan</button></div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
