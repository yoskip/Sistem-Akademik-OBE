<?php
require_once __DIR__ . '/../includes/auth.php';

$pdo = db();
$count = function (string $t) use ($pdo): int {
    $allowed = ['profil_lulusan', 'cpl', 'bahan_kajian', 'mata_kuliah', 'cpmk', 'sub_cpmk'];
    if (!in_array($t, $allowed, true)) {
        return 0;
    }
    return (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
};

$stats = [
    'pl'    => ['label' => 'Profil Lulusan', 'value' => $count('profil_lulusan'), 'icon' => 'bi-person-badge', 'soft' => 'bg-soft-blue'],
    'cpl'   => ['label' => 'CPL Program Studi', 'value' => $count('cpl'), 'icon' => 'bi-bullseye', 'soft' => 'bg-soft-navy'],
    'bk'    => ['label' => 'Bahan Kajian', 'value' => $count('bahan_kajian'), 'icon' => 'bi-journal-bookmark', 'soft' => 'bg-soft-teal'],
    'mk'    => ['label' => 'Mata Kuliah', 'value' => $count('mata_kuliah'), 'icon' => 'bi-journal-text', 'soft' => 'bg-soft-orange'],
    'cpmk'  => ['label' => 'CPMK', 'value' => $count('cpmk'), 'icon' => 'bi-check2-circle', 'soft' => 'bg-soft-purple'],
    'sub'   => ['label' => 'Sub-CPMK', 'value' => $count('sub_cpmk'), 'icon' => 'bi-list-check', 'soft' => 'bg-soft-green'],
];

$cplMk = $pdo->query(
    'SELECT c.kode_cpl, COUNT(cm.id) AS jml
     FROM cpl c LEFT JOIN cpl_mk cm ON cm.cpl_id = c.id
     GROUP BY c.id, c.kode_cpl ORDER BY c.kode_cpl'
)->fetchAll();

$statusRows = $pdo->query(
    'SELECT status, COUNT(*) AS jml FROM inventaris_kelengkapan GROUP BY status'
)->fetchAll();
$status = ['Lengkap' => 0, 'Sebagian' => 0, 'Belum' => 0];
foreach ($statusRows as $r) $status[$r['status']] = (int)$r['jml'];
$totalInv = array_sum($status);
$persen = $totalInv > 0 ? round($status['Lengkap'] / $totalInv * 100) : 0;

$recent = $pdo->query(
    'SELECT a.*, u.nama FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.id DESC LIMIT 8'
)->fetchAll();

$page_title = 'Dashboard';
$page_subtitle = 'Ringkasan kurikulum Outcome-Based Education';
$page_scripts = '<script>
document.addEventListener("DOMContentLoaded", function(){
  new Chart(document.getElementById("chartCpl"), {
    type: "bar",
    data: {
      labels: ' . json_encode(array_column($cplMk, 'kode_cpl')) . ',
      datasets: [{
        label: "Jumlah Mata Kuliah",
        data: ' . json_encode(array_map('intval', array_column($cplMk, 'jml'))) . ',
        backgroundColor: "#1f6feb", borderRadius: 6, maxBarThickness: 46
      }]
    },
    options: { plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true, ticks:{precision:0}}} }
  });
  new Chart(document.getElementById("chartKelengkapan"), {
    type: "doughnut",
    data: {
      labels: ["Lengkap","Sebagian","Belum"],
      datasets: [{ data: [' . $status['Lengkap'] . ',' . $status['Sebagian'] . ',' . $status['Belum'] . '],
        backgroundColor:["#198754","#f0ad4e","#dc3545"], borderWidth:0 }]
    },
    options: { cutout:"68%", plugins:{legend:{position:"bottom"}} }
  });
});
</script>';
include __DIR__ . '/../includes/header.php';
?>
<div class="row g-3 mb-4">
  <?php foreach ($stats as $s): ?>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="stat-card">
        <div class="icon <?= $s['soft'] ?>"><i class="bi <?= $s['icon'] ?>"></i></div>
        <div class="value"><?= number_format($s['value']) ?></div>
        <div class="label"><?= e($s['label']) ?></div>
        <div class="accent bg-primary"></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-bar-chart me-1"></i> Pemetaan CPL &rarr; Mata Kuliah</span>
        <span class="text-muted small">jumlah MK per CPL</span>
      </div>
      <div class="card-body"><canvas id="chartCpl" height="150"></canvas></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-pie-chart me-1"></i> Kelengkapan Data Kurikulum</div>
      <div class="card-body">
        <div class="d-flex align-items-center gap-3">
          <div style="width:170px"><canvas id="chartKelengkapan"></canvas></div>
          <div class="flex-grow-1">
            <div class="mb-2"><span class="status-chip status-lengkap">Lengkap</span> <b><?= $status['Lengkap'] ?></b> MK</div>
            <div class="mb-2"><span class="status-chip status-sebagian">Sebagian</span> <b><?= $status['Sebagian'] ?></b> MK</div>
            <div class="mb-3"><span class="status-chip status-belum">Belum</span> <b><?= $status['Belum'] ?></b> MK</div>
            <div class="fw-bold text-success fs-4"><?= $persen ?>%</div>
            <div class="text-muted small">dari <?= $totalInv ?> mata kuliah terdata</div>
          </div>
        </div>
        <a href="<?= BASE_URL ?>/monitoring/kelengkapan.php" class="btn btn-sm btn-outline-primary mt-3 w-100">
          Lihat Monitoring Kelengkapan <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-clock-history me-1"></i> Aktivitas Terbaru</div>
      <div class="card-body p-0">
        <?php if (!$recent): ?>
          <div class="empty-state"><i class="bi bi-inbox"></i> Belum ada aktivitas.</div>
        <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach ($recent as $r): ?>
              <div class="list-group-item d-flex gap-2 align-items-start">
                <div class="avatar sm mt-1"><?= e(strtoupper(substr($r['nama'] ?? 'S', 0, 1))) ?></div>
                <div class="flex-grow-1">
                  <div class="small"><b><?= e($r['nama'] ?? 'Sistem') ?></b> &middot; <?= e($r['aksi']) ?> <span class="text-muted">(<?= e($r['modul']) ?>)</span></div>
                  <div class="text-muted" style="font-size:.75rem"><?= e(obe_mask_display_text((string)($r['keterangan'] ?? ''))) ?></div>
                </div>
                <div class="text-muted" style="font-size:.72rem"><?= e(date('d/m H:i', strtotime($r['created_at']))) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-lightning-charge me-1"></i> Akses Cepat</div>
      <div class="card-body">
        <div class="row g-2">
          <?php
          $quick = [
              ['Profil Lulusan', 'profil_lulusan/index.php', 'bi-person-badge'],
              ['CPL', 'cpl/index.php', 'bi-bullseye'],
              ['Bahan Kajian', 'bahan_kajian/index.php', 'bi-journal-bookmark'],
              ['Mata Kuliah', 'mata_kuliah/index.php', 'bi-journal-text'],
              ['CPMK', 'cpmk/index.php', 'bi-check2-circle'],
              ['Sub-CPMK', 'sub_cpmk/index.php', 'bi-list-check'],
              ['Pemetaan CPL-PL', 'pemetaan/cpl_pl.php', 'bi-diagram-2'],
              ['RPS Dosen', 'rps/index.php', 'bi-journal-richtext'],
          ];
          foreach ($quick as $q): ?>
            <div class="col-6 col-md-3">
              <a class="btn btn-light border w-100 py-3 text-start" href="<?= BASE_URL ?>/<?= $q[1] ?>">
                <i class="bi <?= $q[2] ?> text-primary me-1"></i><br>
                <span class="small fw-semibold"><?= e($q[0]) ?></span>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
