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
        $mkId = (int)($_POST['mk_id'] ?? 0);
        $cpmkId = (int)($_POST['cpmk_id'] ?? 0);
        $skor = $_POST['skor_maks'] !== '' ? (float)$_POST['skor_maks'] : null;
        $total = $_POST['total'] !== '' ? (float)$_POST['total'] : null;
        if (!$mkId) { flash('danger', 'Mata kuliah wajib dipilih.'); }
        else {
            $m = $pdo->prepare('SELECT kode_mk FROM mata_kuliah WHERE id=?'); $m->execute([$mkId]); $mkRaw = (string)$m->fetchColumn();
            $kc = null; $cplId = null; $kcp = null;
            if ($cpmkId) { $c = $pdo->prepare('SELECT kode_cpmk, cpl_id FROM cpmk WHERE id=?'); $c->execute([$cpmkId]); $cr = $c->fetch(); $kc = $cr['kode_cpmk'] ?? null; $cplId = $cr['cpl_id'] ?? null; if ($cplId) { $q = $pdo->prepare('SELECT kode_cpl FROM cpl WHERE id=?'); $q->execute([$cplId]); $kcp = $q->fetchColumn(); } }
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE rumusan_akhir_mk SET mk_id=?, kode_mk_raw=?, cpmk_id=?, kode_cpmk_raw=?, cpl_id=?, kode_cpl_raw=?, skor_maks=?, total=? WHERE id=?')
                        ->execute([$mkId, $mkRaw, $cpmkId ?: null, $kc, $cplId, $kcp, $skor, $total, $id]);
                    log_activity('update', 'rumusan_mk', "Rumusan akhir MK $mkRaw diperbarui");
                    flash('success', 'Rumusan akhir MK diperbarui.');
                } else {
                    $pdo->prepare('INSERT INTO rumusan_akhir_mk (mk_id, kode_mk_raw, cpmk_id, kode_cpmk_raw, cpl_id, kode_cpl_raw, skor_maks, total) VALUES (?,?,?,?,?,?,?,?)')
                        ->execute([$mkId, $mkRaw, $cpmkId ?: null, $kc, $cplId, $kcp, $skor, $total]);
                    log_activity('create', 'rumusan_mk', "Rumusan akhir MK $mkRaw ditambahkan");
                    flash('success', 'Rumusan akhir MK ditambahkan.');
                }
            } catch (PDOException $e) { flash('danger', 'Gagal menyimpan rumusan akhir MK.'); }
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM rumusan_akhir_mk WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
        log_activity('delete', 'rumusan_mk', 'Rumusan akhir MK dihapus');
        flash('success', 'Data dihapus.');
    }
    redirect('rumusan_mk.php');
}

$mkFilter = (int)($_GET['mk'] ?? 0);
$where = $mkFilter ? 'WHERE r.mk_id = ?' : '';
$params = $mkFilter ? [$mkFilter] : [];
$s = $pdo->prepare("SELECT COUNT(*) FROM rumusan_akhir_mk r $where"); $s->execute($params); $total = (int)$s->fetchColumn();
[$limit, $offset, , $page, $pages] = paginate($total, 15);
$stmt = $pdo->prepare("SELECT r.*, m.nama_mk FROM rumusan_akhir_mk r LEFT JOIN mata_kuliah m ON m.id=r.mk_id $where ORDER BY r.kode_mk_raw, r.kode_cpmk_raw LIMIT $limit OFFSET $offset");
$stmt->execute($params); $rows = $stmt->fetchAll();
$mks = $pdo->query('SELECT * FROM mata_kuliah ORDER BY CAST(semester AS UNSIGNED), kode_mk')->fetchAll();
$cpmks = $pdo->query('SELECT k.*, c.kode_cpl FROM cpmk k JOIN cpl c ON c.id=k.cpl_id ORDER BY k.kode_cpmk')->fetchAll();

$edit = null;
if (!empty($_GET['edit'])) { $s = $pdo->prepare('SELECT * FROM rumusan_akhir_mk WHERE id=?'); $s->execute([(int)$_GET['edit']]); $edit = $s->fetch(); }

$page_title = 'Rumusan Akhir MK';
$page_subtitle = 'Rekap skor akhir CPMK per mata kuliah';
$page_scripts = $edit ? '<script>new bootstrap.Modal(document.getElementById("modalRm")).show();</script>' : '';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h2><i class="bi bi-file-earmark-text me-1 text-primary"></i> Rumusan Akhir MK</h2>
  <div class="d-flex gap-2 flex-wrap">
    <form method="get"><select name="mk" class="form-select form-select-sm" data-autosubmit style="min-width:260px"><option value="0">Semua Mata Kuliah</option><?php foreach ($mks as $m): ?><option value="<?= $m['id'] ?>" <?= $mkFilter === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['kode_mk']) ?> - <?= e(mb_strimwidth($m['nama_mk'], 0, 45, '...')) ?></option><?php endforeach; ?></select></form>
    <?php if ($can): ?><a href="?edit=0" class="btn btn-sm btn-blue"><i class="bi bi-plus-lg"></i> Tambah</a><?php endif; ?>
  </div>
</div>
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0 align-middle">
        <thead><tr><th>MK</th><th>CPMK</th><th>CPL</th><th class="text-center">Skor Maks</th><th class="text-center">Total</th><th style="width:100px" class="text-center">Aksi</th></tr></thead>
        <tbody>
          <?php if (!$rows): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-inbox"></i> Data tidak ditemukan.</div></td></tr><?php endif; ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><b><?= e($r['kode_mk_raw']) ?></b><div class="small text-muted"><?= e($r['nama_mk'] ?? '') ?></div></td>
              <td><span class="badge bg-primary"><?= e($r['kode_cpmk_raw'] ?? '-') ?></span></td>
              <td><span class="badge bg-light text-dark border"><?= e($r['kode_cpl_raw'] ?? '-') ?></span></td>
              <td class="text-center"><?= e($r['skor_maks'] ?? '-') ?></td>
              <td class="text-center"><?= e($r['total'] ?? '-') ?></td>
              <td class="text-center">
                <?php if ($can): ?>
                  <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $r['id'] ?>"><i class="bi bi-pencil"></i></a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Hapus data ini?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
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
<div class="modal fade" id="modalRm" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="modal-header"><h5 class="modal-title"><?= $edit ? 'Edit' : 'Tambah' ?> Rumusan Akhir MK</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Mata Kuliah <span class="text-danger">*</span></label><select name="mk_id" class="form-select" required><option value="">-- pilih --</option><?php foreach ($mks as $m): ?><option value="<?= $m['id'] ?>" <?= (int)($edit['mk_id'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['kode_mk']) ?> - <?= e($m['nama_mk']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label">CPMK</label><select name="cpmk_id" class="form-select"><option value="">-- opsional --</option><?php foreach ($cpmks as $c): ?><option value="<?= $c['id'] ?>" <?= (int)($edit['cpmk_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['kode_cpmk']) ?> (<?= e($c['kode_cpl']) ?>)</option><?php endforeach; ?></select></div>
        <div class="row g-3">
          <div class="col-6"><label class="form-label">Skor Maks</label><input type="number" step="0.01" name="skor_maks" class="form-control" value="<?= e($edit['skor_maks'] ?? '') ?>"></div>
          <div class="col-6"><label class="form-label">Total</label><input type="number" step="0.01" name="total" class="form-control" value="<?= e($edit['total'] ?? '') ?>"></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-navy"><i class="bi bi-save"></i> Simpan</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
