<?php
require_once __DIR__ . '/../includes/auth.php';
$pdo = db();
$indicators = ['ada_13' => '13', 'ada_15' => '15', 'ada_16' => '16', 'ada_17' => '17', 'ada_18' => '18', 'ada_18a' => '18a', 'ada_19' => '19', 'ada_20' => '20'];

$q = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$where = [];
$params = [];
if ($q !== '') { $where[] = '(kode_mk_raw LIKE ? OR nama_mk LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($statusFilter !== '') { $where[] = 'status = ?'; $params[] = $statusFilter; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

if (isset($_GET['export'])) {
    $stmt = $pdo->prepare("SELECT kode_mk_raw, nama_mk, semester, ada_13, ada_15, ada_16, ada_17, ada_18, ada_18a, ada_19, ada_20, jml_ada, status FROM inventaris_kelengkapan $whereSql ORDER BY CAST(semester AS UNSIGNED), kode_mk_raw");
    $stmt->execute($params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=inventaris_kelengkapan.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, array_merge(['Kode MK', 'Nama MK', 'Semester'], array_map(fn($x) => "Ada $x", $indicators), ['Jumlah', 'Status']));
    foreach ($stmt as $r) {
        fputcsv($out, array_merge([$r['kode_mk_raw'], $r['nama_mk'], $r['semester']], array_map(fn($f) => $r[$f] ? 'v' : '', array_keys($indicators)), [$r['jml_ada'], $r['status']]));
    }
    fclose($out);
    exit;
}

$s = $pdo->prepare("SELECT COUNT(*) FROM inventaris_kelengkapan $whereSql"); $s->execute($params); $total = (int)$s->fetchColumn();
[$limit, $offset, , $page, $pages] = paginate($total, 15);
$stmt = $pdo->prepare("SELECT * FROM inventaris_kelengkapan $whereSql ORDER BY CAST(semester AS UNSIGNED), kode_mk_raw LIMIT $limit OFFSET $offset");
$stmt->execute($params); $rows = $stmt->fetchAll();

$summary = ['Lengkap' => 0, 'Sebagian' => 0, 'Belum' => 0];
foreach ($pdo->query('SELECT status, COUNT(*) c FROM inventaris_kelengkapan GROUP BY status') as $r) $summary[$r['status']] = (int)$r['c'];
$sumTotal = array_sum($summary);
$persen = $sumTotal ? round($summary['Lengkap'] / $sumTotal * 100) : 0;

$page_title = 'Inventaris Kelengkapan MK';
$page_subtitle = 'Monitoring kelengkapan dokumen kurikulum tiap mata kuliah';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h2><i class="bi bi-clipboard-data me-1 text-primary"></i> Inventaris Kelengkapan MK</h2>
  <div class="d-flex gap-2 flex-wrap">
    <form class="d-flex" method="get">
      <select name="status" class="form-select form-select-sm me-1" data-autosubmit>
        <option value="">Semua Status</option>
        <?php foreach (['Lengkap', 'Sebagian', 'Belum'] as $st): ?><option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= $st ?></option><?php endforeach; ?>
      </select>
      <input type="text" name="q" value="<?= e($q) ?>" class="form-control form-control-sm search-box" placeholder="Cari MK...">
      <button class="btn btn-sm btn-outline-secondary ms-1"><i class="bi bi-search"></i></button>
    </form>
    <a href="?export=1&q=<?= urlencode($q) ?>&status=<?= urlencode($statusFilter) ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-download"></i> Export CSV</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card"><div class="icon bg-soft-green"><i class="bi bi-check2-circle"></i></div><div class="value"><?= $summary['Lengkap'] ?></div><div class="label">Lengkap</div><div class="accent bg-success"></div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card"><div class="icon bg-soft-orange"><i class="bi bi-hourglass-split"></i></div><div class="value"><?= $summary['Sebagian'] ?></div><div class="label">Sebagian</div><div class="accent bg-warning"></div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card"><div class="icon bg-soft-blue"><i class="bi bi-x-circle"></i></div><div class="value"><?= $summary['Belum'] ?></div><div class="label">Belum</div><div class="accent bg-danger"></div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card"><div class="icon bg-soft-navy"><i class="bi bi-percent"></i></div><div class="value"><?= $persen ?>%</div><div class="label">Kelengkapan</div><div class="accent bg-primary"></div></div>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0 align-middle">
        <thead><tr><th>Mata Kuliah</th><th class="text-center">Smt</th><?php foreach ($indicators as $x): ?><th class="text-center" style="width:48px"><?= e($x) ?></th><?php endforeach; ?><th class="text-center">Jml</th><th class="text-center">Status</th></tr></thead>
        <tbody>
          <?php if (!$rows): ?><tr><td colspan="<?= count($indicators) + 4 ?>"><div class="empty-state"><i class="bi bi-inbox"></i> Data tidak ditemukan.</div></td></tr><?php endif; ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><b><?= e($r['kode_mk_raw']) ?></b><div class="small text-muted"><?= e($r['nama_mk']) ?></div></td>
              <td class="text-center"><?= e($r['semester']) ?></td>
              <?php foreach (array_keys($indicators) as $f): ?><td class="text-center"><?= $r[$f] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<span class="text-muted">-</span>' ?></td><?php endforeach; ?>
              <td class="text-center"><b><?= (int)$r['jml_ada'] ?></b></td>
              <td class="text-center"><span class="status-chip status-<?= strtolower($r['status']) ?>"><?= e($r['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center"><span class="text-muted small">Total <?= $total ?> MK</span><?= render_pagination($page, $pages) ?></div>
</div>
<div class="form-text mt-2">Kolom angka merujuk pada sheet sumber: 13 (Pemenuhan CPL-CPMK-MK), 15 (Sub-CPMK), 16 (Teknik Penilaian), 17 (Tahap &amp; Mekanisme), 18/18a (Bobot), 19 (Rumusan Akhir MK), 20 (Rumusan Akhir CPL).</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
