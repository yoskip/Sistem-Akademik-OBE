<?php
require_once __DIR__ . '/../includes/auth.php';
$pdo = db();
$can = can_manage_master();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$can) { deny_access(); }
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $kode = strtoupper(trim($_POST['kode_mk'] ?? ''));
        $nama = trim($_POST['nama_mk'] ?? '');
        $semester = trim($_POST['semester'] ?? '');
        $teori = (float)($_POST['sks_teori'] ?? 0);
        $praktik = (float)($_POST['praktik'] ?? 0);
        $lap = (float)($_POST['praktik_lapangan'] ?? 0);
        $kelompok = trim($_POST['kelompok'] ?? '');
        $internal = trim($_POST['kode_internal'] ?? '');

        if ($id < 0 || ($id > 0 && !validate_id($id))) {
            http_response_code(422);
            flash('danger', 'ID Mata Kuliah tidak valid.');
            redirect('index.php');
        }
        if (!validate_code($kode, 2, 30)) {
            http_response_code(422);
            flash('danger', 'Kode Mata Kuliah tidak valid (2-30 karakter alfanumerik/tanda hubung/garis bawah).');
            redirect('index.php');
        }
        if (!validate_name($nama, 2, 200)) {
            http_response_code(422);
            flash('danger', 'Nama Mata Kuliah tidak valid (2-200 karakter).');
            redirect('index.php');
        }
        if ($semester === '' || strlen($semester) > 10 || !preg_match('/^[a-zA-Z0-9\s\-]{1,10}$/', $semester)) {
            http_response_code(422);
            flash('danger', 'Semester tidak valid (1-10 karakter alfanumerik).');
            redirect('index.php');
        }
        if ($teori < 0 || $teori > 20 || $praktik < 0 || $praktik > 20 || $lap < 0 || $lap > 20) {
            http_response_code(422);
            flash('danger', 'Nilai SKS tidak valid (harus antara 0 - 20).');
            redirect('index.php');
        }
        if ($kelompok !== '' && (strlen($kelompok) > 60 || preg_match('/[\x00-\x1f\x7f<>&"\'`]/', $kelompok))) {
            http_response_code(422);
            flash('danger', 'Kelompok Mata Kuliah tidak valid.');
            redirect('index.php');
        }
        if ($internal !== '' && !validate_code($internal, 1, 20)) {
            http_response_code(422);
            flash('danger', 'Kode Internal tidak valid (maksimal 20 karakter alfanumerik).');
            redirect('index.php');
        }

        $sks = $teori + $praktik + $lap;
        try {
            if ($id > 0) {
                $pdo->prepare('UPDATE mata_kuliah SET kode_mk=?, nama_mk=?, sks=?, sks_teori=?, praktik=?, praktik_lapangan=?, semester=?, kelompok=?, kode_internal=? WHERE id=?')
                    ->execute([$kode, $nama, $sks, $teori, $praktik, $lap, $semester, $kelompok ?: null, $internal ?: null, $id]);
                log_activity('update', 'mata_kuliah', "MK $kode diperbarui");
                flash('success', 'Mata Kuliah diperbarui.');
            } else {
                $pdo->prepare('INSERT INTO mata_kuliah (kode_mk, nama_mk, sks, sks_teori, praktik, praktik_lapangan, semester, kelompok, kode_internal) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$kode, $nama, $sks, $teori, $praktik, $lap, $semester, $kelompok ?: null, $internal ?: null]);
                log_activity('create', 'mata_kuliah', "MK $kode ditambahkan");
                flash('success', 'Mata Kuliah ditambahkan.');
            }
        } catch (PDOException $e) { obe_log_database_failure($e);
            flash('danger', 'Gagal menyimpan: kode MK mungkin sudah dipakai.');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (!validate_id($id)) {
            http_response_code(422);
            flash('danger', 'ID Mata Kuliah tidak valid.');
            redirect('index.php');
        }
        $pdo->prepare('DELETE FROM mata_kuliah WHERE id=?')->execute([$id]);
        log_activity('delete', 'mata_kuliah', 'MK dihapus');
        flash('success', 'Mata Kuliah dihapus.');
    }
    redirect('index.php');
}

$q = trim($_GET['q'] ?? '');
$sem = trim($_GET['semester'] ?? '');
$where = [];
$params = [];
if ($q !== '') { $where[] = '(kode_mk LIKE ? OR nama_mk LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($sem !== '') { $where[] = 'semester = ?'; $params[] = $sem; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$s = $pdo->prepare("SELECT COUNT(*) FROM mata_kuliah $whereSql"); $s->execute($params); $total = (int)$s->fetchColumn();
[$limit, $offset, , $page, $pages] = paginate($total, 12);
$stmt = $pdo->prepare("SELECT * FROM mata_kuliah $whereSql ORDER BY CAST(semester AS UNSIGNED), kode_mk LIMIT $limit OFFSET $offset");
$stmt->execute($params); $rows = $stmt->fetchAll();
$semesters = $pdo->query('SELECT DISTINCT semester FROM mata_kuliah ORDER BY CAST(semester AS UNSIGNED)')->fetchAll(PDO::FETCH_COLUMN);

$edit = null;
if (!empty($_GET['edit'])) { $s = $pdo->prepare('SELECT * FROM mata_kuliah WHERE id=?'); $s->execute([(int)$_GET['edit']]); $edit = $s->fetch(); }
$detail = null;
if (!empty($_GET['detail'])) {
    $s = $pdo->prepare('SELECT * FROM mata_kuliah WHERE id=?'); $s->execute([(int)$_GET['detail']]); $detail = $s->fetch();
    if ($detail) {
        $st = $pdo->prepare('SELECT b.kode_bk, b.nama_bk FROM bahan_kajian_mk bm JOIN bahan_kajian b ON b.id=bm.bahan_kajian_id WHERE bm.mk_id=? ORDER BY b.kode_bk');
        $st->execute([$detail['id']]); $detail['bk'] = $st->fetchAll();
        $st = $pdo->prepare('SELECT c.kode_cpl FROM cpl_mk cm JOIN cpl c ON c.id=cm.cpl_id WHERE cm.mk_id=? ORDER BY c.kode_cpl');
        $st->execute([$detail['id']]); $detail['cpl'] = $st->fetchAll(PDO::FETCH_COLUMN);
    }
}

$page_title = 'Mata Kuliah';
$page_subtitle = 'Daftar Mata Kuliah Kurikulum 2022';
$page_scripts = '';
if ($edit) $page_scripts .= '<script>new bootstrap.Modal(document.getElementById("modalMk")).show();</script>';
if ($detail) $page_scripts .= '<script>new bootstrap.Modal(document.getElementById("detailMk")).show();</script>';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h2><i class="bi bi-journal-text me-1 text-primary"></i> Daftar Mata Kuliah</h2>
  <div class="d-flex gap-2 flex-wrap">
    <form class="d-flex" method="get">
      <select name="semester" class="form-select form-select-sm me-1" data-autosubmit>
        <option value="">Semua Semester</option>
        <?php foreach ($semesters as $sm): ?><option value="<?= e($sm) ?>" <?= $sem === $sm ? 'selected' : '' ?>>Semester <?= e($sm) ?></option><?php endforeach; ?>
      </select>
      <input type="text" name="q" value="<?= e($q) ?>" class="form-control form-control-sm search-box" placeholder="Cari kode / nama MK...">
      <button class="btn btn-sm btn-outline-secondary ms-1"><i class="bi bi-search"></i></button>
    </form>
    <?php if ($can): ?><a href="?edit=0" class="btn btn-sm btn-blue"><i class="bi bi-plus-lg"></i> Tambah MK</a><?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th style="width:50px">No</th><th>Kode MK</th><th>Nama Mata Kuliah</th><th class="text-center">SKS</th><th class="text-center">Semester</th><th>Kelompok</th><th style="width:110px" class="text-center">Aksi</th></tr></thead>
        <tbody>
          <?php if (!$rows): ?><tr><td colspan="7"><div class="empty-state"><i class="bi bi-inbox"></i> Data tidak ditemukan.</div></td></tr><?php endif; ?>
          <?php foreach ($rows as $i => $r): ?>
            <tr>
              <td><?= $offset + $i + 1 ?></td>
              <td><span class="fw-semibold"><?= e($r['kode_mk']) ?></span><?php if ($r['kode_internal']): ?><div class="text-muted small"><?= e($r['kode_internal']) ?></div><?php endif; ?></td>
              <td><?= e($r['nama_mk']) ?></td>
              <td class="text-center"><span class="badge bg-light text-dark border"><?= e(rtrim(rtrim(number_format((float)$r['sks'], 2, '.', ''), '0'), '.')) ?></span></td>
              <td class="text-center"><?= e($r['semester']) ?></td>
              <td><span class="small"><?= e($r['kelompok'] ?? '-') ?></span></td>
              <td class="text-center">
                <a class="btn btn-sm btn-outline-secondary" href="?detail=<?= $r['id'] ?>"><i class="bi bi-eye"></i></a>
                <?php if ($can): ?>
                  <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $r['id'] ?>"><i class="bi bi-pencil"></i></a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Hapus <?= e($r['kode_mk']) ?>?')">
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
<div class="modal fade" id="modalMk" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="modal-header"><h5 class="modal-title"><?= $edit ? 'Edit' : 'Tambah' ?> Mata Kuliah</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Kode MK <span class="text-danger">*</span></label><input type="text" name="kode_mk" class="form-control" value="<?= e($edit['kode_mk'] ?? '') ?>" required></div>
          <div class="col-md-6"><label class="form-label">Nama Mata Kuliah <span class="text-danger">*</span></label><input type="text" name="nama_mk" class="form-control" value="<?= e($edit['nama_mk'] ?? '') ?>" required></div>
          <div class="col-md-3"><label class="form-label">SKS Teori</label><input type="number" step="0.5" min="0" name="sks_teori" class="form-control" value="<?= e($edit['sks_teori'] ?? '0') ?>"></div>
          <div class="col-md-3"><label class="form-label">Praktik</label><input type="number" step="0.5" min="0" name="praktik" class="form-control" value="<?= e($edit['praktik'] ?? '0') ?>"></div>
          <div class="col-md-3"><label class="form-label">Praktik Lapangan</label><input type="number" step="0.5" min="0" name="praktik_lapangan" class="form-control" value="<?= e($edit['praktik_lapangan'] ?? '0') ?>"></div>
          <div class="col-md-3"><label class="form-label">Semester <span class="text-danger">*</span></label><input type="text" name="semester" class="form-control" value="<?= e($edit['semester'] ?? '') ?>" placeholder="1 / Smt 5" required></div>
          <div class="col-md-6"><label class="form-label">Kelompok</label><input type="text" name="kelompok" class="form-control" value="<?= e($edit['kelompok'] ?? '') ?>" placeholder="Wajib Umum (KU) / Keahlian Prodi (KK)"></div>
          <div class="col-md-6"><label class="form-label">Kode Internal</label><input type="text" name="kode_internal" class="form-control" value="<?= e($edit['kode_internal'] ?? '') ?>" placeholder="MK01"></div>
        </div>
        <div class="form-text mt-2">Total SKS dihitung otomatis dari SKS Teori + Praktik + Praktik Lapangan.</div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-navy"><i class="bi bi-save"></i> Simpan</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="modal fade" id="detailMk" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Detail Mata Kuliah</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <table class="table table-sm">
          <tr><th style="width:160px">Kode MK</th><td><?= e($detail['kode_mk'] ?? '') ?></td></tr>
          <tr><th>Nama</th><td><?= e($detail['nama_mk'] ?? '') ?></td></tr>
          <tr><th>SKS</th><td><?= e($detail['sks'] ?? '') ?> (Teori <?= e($detail['sks_teori'] ?? 0) ?>, Praktik <?= e($detail['praktik'] ?? 0) ?>, Lapangan <?= e($detail['praktik_lapangan'] ?? 0) ?>)</td></tr>
          <tr><th>Semester</th><td><?= e($detail['semester'] ?? '') ?></td></tr>
          <tr><th>Kelompok</th><td><?= e($detail['kelompok'] ?? '-') ?></td></tr>
          <tr><th>Kode Internal</th><td><?= e($detail['kode_internal'] ?? '-') ?></td></tr>
          <tr><th>Bahan Kajian</th><td><?= $detail && $detail['bk'] ? implode(', ', array_map(fn($x) => e($x['kode_bk'] . ' - ' . $x['nama_bk']), $detail['bk'])) : '-' ?></td></tr>
          <tr><th>CPL Terkait</th><td><?= $detail && $detail['cpl'] ? implode(', ', array_map('e', $detail['cpl'])) : '-' ?></td></tr>
        </table>
      </div>
      <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Tutup</button></div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
