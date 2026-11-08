<?php
/**
 * Impor data kurikulum dari file Excel (xlsx) ke database db_obe.
 * Jalankan dari command line:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\obe\import\import_excel.php
 *
 * Skrip ini TIDAK mengubah/mengarang data. Nilai dipindahkan apa adanya
 * dari file Excel; ketidaksesuaian kode disimpan sebagai nilai mentah.
 */
require_once __DIR__ . '/../config/database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Skrip importer hanya boleh dijalankan dari command line.');
}

$file = __DIR__ . '/kurikulum.xlsx';
if (!is_file($file)) {
    exit("File kurikulum.xlsx tidak ditemukan di folder import/.\n");
}

$NS_MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
$NS_REL  = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

function col_index(string $ref): int
{
    if (!preg_match('/([A-Z]+)/', $ref, $m)) return 0;
    $s = $m[1];
    $n = 0;
    for ($i = 0; $i < strlen($s); $i++) {
        $n = $n * 26 + (ord($s[$i]) - 64);
    }
    return $n - 1;
}

function get(array $row, int $i, string $default = ''): string
{
    return trim((string)($row[$i] ?? $default));
}

function xlsx_load(string $file): array
{
    global $NS_MAIN, $NS_REL;
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) {
        throw new RuntimeException('Tidak dapat membuka file xlsx.');
    }

    $shared = [];
    $ss = $zip->getFromName('xl/sharedStrings.xml');
    if ($ss !== false) {
        $x = new SimpleXMLElement($ss);
        foreach ($x->si as $si) {
            if (isset($si->t)) {
                $shared[] = (string)$si->t;
            } else {
                $t = '';
                foreach ($si->r as $r) $t .= (string)$r->t;
                $shared[] = $t;
            }
        }
    }

    $wb = new SimpleXMLElement($zip->getFromName('xl/workbook.xml'));
    $wb->registerXPathNamespace('m', $NS_MAIN);
    $rels = new SimpleXMLElement($zip->getFromName('xl/_rels/workbook.xml.rels'));
    $relMap = [];
    foreach ($rels->Relationship as $rel) {
        $relMap[(string)$rel['Id']] = (string)$rel['Target'];
    }

    $sheets = [];
    foreach ($wb->xpath('//m:sheets/m:sheet') as $sh) {
        $name = (string)$sh['name'];
        $attrs = $sh->attributes($NS_REL);
        $rid = (string)$attrs['id'];
        if (!isset($relMap[$rid])) continue;
        $target = $relMap[$rid];
        if (strpos($target, 'xl/') !== 0) $target = 'xl/' . ltrim($target, '/');
        $sheets[$name] = $target;
    }

    $out = [];
    foreach ($sheets as $name => $target) {
        $xmlStr = $zip->getFromName($target);
        if ($xmlStr === false) { $out[$name] = []; continue; }
        $sx = new SimpleXMLElement($xmlStr);
        $sx->registerXPathNamespace('m', $NS_MAIN);
        $rows = [];
        foreach ($sx->xpath('//m:sheetData/m:row') as $row) {
            $row->registerXPathNamespace('m', $NS_MAIN);
            $cells = [];
            foreach ($row->xpath('m:c') as $c) {
                $ci = col_index((string)$c['r']);
                while (count($cells) < $ci) $cells[] = '';
                $type = (string)$c['t'];
                if ($type === 's') {
                    $val = $shared[(int)$c->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $val = (string)$c->is->t;
                } else {
                    $val = (string)$c->v;
                }
                $cells[$ci] = $val;
            }
            $rows[] = $cells;
        }
        $out[$name] = $rows;
    }
    $zip->close();
    return $out;
}

$S = xlsx_load($file);
echo "Membaca " . count($S) . " sheet.\n";

$pdo = db();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$tables = [
    'pl_cpl', 'cpl_bahan_kajian', 'bahan_kajian_mk', 'cpl_mk', 'cpl_bk_mk',
    'peta_pemenuhan_cpl', 'cpl_cpmk', 'cpmk_mk', 'sub_cpmk',
    'teknik_penilaian_cpmk', 'mekanisme_penilaian', 'bobot_penilaian',
    'bobot_penilaian_alternatif', 'rumusan_akhir_mk', 'rumusan_akhir_cpl',
    'rps', 'inventaris_kelengkapan', 'cpmk', 'mata_kuliah',
    'bahan_kajian', 'profil_lulusan', 'cpl',
];
foreach ($tables as $t) {
    $pdo->exec("TRUNCATE TABLE $t");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

/* ---------------- 1. Profil Lulusan ---------------- */
$ins = $pdo->prepare('INSERT INTO profil_lulusan (kode_pl, nama_pl) VALUES (?,?)');
foreach ($S['1. Profil Lulusan'] ?? [] as $i => $r) {
    if ($i === 0) continue;
    $kode = get($r, 1);
    if (!preg_match('/^PL\d+/', $kode)) continue;
    $ins->execute([$kode, get($r, 2)]);
}

/* ---------------- 2. CPL ---------------- */
$ins = $pdo->prepare('INSERT INTO cpl (kode_cpl, deskripsi, domain) VALUES (?,?,?)');
foreach ($S['2. CPL Prodi'] ?? [] as $i => $r) {
    if ($i === 0) continue;
    $kode = get($r, 1);
    if (!preg_match('/^CPL\d+$/', $kode)) continue;
    $ins->execute([$kode, get($r, 2), get($r, 3)]);
}

/* ---------------- 4. Bahan Kajian ---------------- */
$ins = $pdo->prepare('INSERT INTO bahan_kajian (kode_bk, nama_bk, acuan) VALUES (?,?,?)');
foreach ($S['4. Bahan Kajian'] ?? [] as $i => $r) {
    if ($i === 0) continue;
    $kode = get($r, 1);
    if (!preg_match('/^BK\d+$/', $kode)) continue;
    $acuan = get($r, 3);
    $ins->execute([$kode, get($r, 2), $acuan !== '' ? $acuan : null]);
}

/* ---------------- 9. Daftar Mata Kuliah ---------------- */
$mkStmt = $pdo->prepare(
    'INSERT INTO mata_kuliah (kode_mk, nama_mk, sks, sks_teori, praktik, praktik_lapangan, semester, kode_internal)
     VALUES (?,?,?,?,?,?,?,?)'
);
foreach ($S['9. Daftar Mata Kuliah'] ?? [] as $i => $r) {
    if ($i === 0) continue;
    $kode = get($r, 1);
    if ($kode === '') continue;
    $teori = (float)get($r, 4);
    $praktik = (float)get($r, 5);
    $lap = (float)get($r, 6);
    $sks = $teori + $praktik + $lap;
    $internal = get($r, 7);
    $mkStmt->execute([$kode, get($r, 2), $sks, $teori, $praktik, $lap, get($r, 3), $internal !== '' ? $internal : null]);
}

/* ---------------- 10. Organisasi MK -> kelompok ---------------- */
$upd = $pdo->prepare('UPDATE mata_kuliah SET kelompok = ? WHERE kode_mk = ?');
foreach ($S['10. Organisasi Mata Kuliah'] ?? [] as $i => $r) {
    if ($i === 0) continue;
    if (!is_numeric(get($r, 0))) continue;
    $kelompok = get($r, 1);
    $kode = get($r, 2);
    if ($kode !== '' && $kelompok !== '') {
        $upd->execute([$kelompok, $kode]);
    }
}

// Peta id
$cplMap = [];
foreach ($pdo->query('SELECT id, kode_cpl FROM cpl') as $x) $cplMap[$x['kode_cpl']] = (int)$x['id'];
$plMap = [];
foreach ($pdo->query('SELECT id, kode_pl FROM profil_lulusan') as $x) $plMap[$x['kode_pl']] = (int)$x['id'];
$bkMap = [];
$bkNameMap = [];
foreach ($pdo->query('SELECT id, kode_bk, nama_bk FROM bahan_kajian') as $x) {
    $bkMap[$x['kode_bk']] = (int)$x['id'];
    $bkNameMap[$x['nama_bk']] = (int)$x['id'];
}
$mkMap = [];
foreach ($pdo->query('SELECT id, kode_mk FROM mata_kuliah') as $x) $mkMap[$x['kode_mk']] = (int)$x['id'];

$cplId = fn($k) => $cplMap[$k] ?? null;
$mkId  = fn($k) => $mkMap[$k] ?? null;

/* ---------------- 3. CPL - Profil Lulusan ---------------- */
$ins = $pdo->prepare('INSERT IGNORE INTO pl_cpl (cpl_id, pl_id) VALUES (?,?)');
foreach ($S['3. CPL - Profil Lulusan'] ?? [] as $i => $r) {
    if ($i === 0) continue;
    $cpl = get($r, 0);
    if (!isset($cplMap[$cpl])) continue;
    for ($c = 1; $c <= 4; $c++) {
        if (strtolower(get($r, $c)) === 'v' && isset($plMap['PL0' . $c])) {
            $ins->execute([$cplMap[$cpl], $plMap['PL0' . $c]]);
        }
    }
}

/* ---------------- 5. CPL - Bahan Kajian ---------------- */
$ins = $pdo->prepare('INSERT IGNORE INTO cpl_bahan_kajian (cpl_id, bahan_kajian_id) VALUES (?,?)');
foreach ($S['5. CPL - Bahan Kajian'] ?? [] as $i => $r) {
    if ($i === 0) continue;
    $bkName = get($r, 0);
    if (!isset($bkNameMap[$bkName])) continue;
    for ($c = 1; $c <= 10; $c++) {
        $cpl = 'CPL' . str_pad((string)$c, 2, '0', STR_PAD_LEFT);
        if (strtolower(get($r, $c)) === 'v' && isset($cplMap[$cpl])) {
            $ins->execute([$cplMap[$cpl], $bkNameMap[$bkName]]);
        }
    }
}

/* ---------------- 6. Bahan Kajian - MK ---------------- */
$ins = $pdo->prepare('INSERT IGNORE INTO bahan_kajian_mk (bahan_kajian_id, mk_id) VALUES (?,?)');
foreach ($S['6. Bahan Kajian - MK'] ?? [] as $i => $r) {
    if ($i === 0) continue;
    $kode = get($r, 0);
    if (!isset($mkMap[$kode])) continue;
    for ($c = 2; $c <= 28; $c++) {
        $bk = 'BK' . str_pad((string)($c - 1), 2, '0', STR_PAD_LEFT);
        if (strtolower(get($r, $c)) === 'v' && isset($bkMap[$bk])) {
            $ins->execute([$bkMap[$bk], $mkMap[$kode]]);
        }
    }
}

/* ---------------- 7. CPL - MK ---------------- */
$cplHeader = [];
foreach ($S['7. CPL - MK'] ?? [] as $r) {
    foreach ($r as $ci => $v) {
        if (preg_match('/^CPL\d+$/', trim((string)$v))) $cplHeader[$ci] = trim((string)$v);
    }
    if ($cplHeader) break;
}
$ins = $pdo->prepare('INSERT IGNORE INTO cpl_mk (cpl_id, mk_id) VALUES (?,?)');
foreach ($S['7. CPL - MK'] ?? [] as $i => $r) {
    if ($i < 2) continue;
    $kode = get($r, 1);
    if (!isset($mkMap[$kode])) continue;
    foreach ($cplHeader as $ci => $cpl) {
        if (strtolower(get($r, $ci)) === 'v' && isset($cplMap[$cpl])) {
            $ins->execute([$cplMap[$cpl], $mkMap[$kode]]);
        }
    }
}

/* ---------------- 8. CPL - BK - MK ---------------- */
$cplHeader = [];
foreach ($S['8. CPL - BK - MK'] ?? [] as $r) {
    foreach ($r as $ci => $v) {
        if (preg_match('/^CPL\d+$/', trim((string)$v))) $cplHeader[$ci] = trim((string)$v);
    }
    if ($cplHeader) break;
}
$ins = $pdo->prepare('INSERT IGNORE INTO cpl_bk_mk (cpl_id, bahan_kajian_id, mk_id, kode_mk_raw) VALUES (?,?,?,?)');
foreach ($S['8. CPL - BK - MK'] ?? [] as $i => $r) {
    if ($i === 0) continue;
    $bk = get($r, 0);
    if (!preg_match('/^BK\d+$/', $bk) || !isset($bkMap[$bk])) continue;
    foreach ($cplHeader as $ci => $cpl) {
        $cell = get($r, $ci);
        if ($cell === '' || !isset($cplMap[$cpl])) continue;
        foreach (preg_split('/\s*,\s*/', $cell) as $raw) {
            $raw = trim($raw);
            if ($raw === '') continue;
            $ins->execute([$cplMap[$cpl], $bkMap[$bk], $mkMap[$raw] ?? null, $raw]);
        }
    }
}

/* ---------------- 11 v1. Peta Pemenuhan CPL (Kode) ---------------- */
$verRow = $pdo->query("SELECT id FROM pemetaan_cpl_versions WHERE versi='v1'")->fetch();
if (!$verRow) {
    $pdo->exec("INSERT INTO pemetaan_cpl_versions (versi, keterangan) VALUES ('v1','Peta Pemenuhan CPL (Kode)')");
    $verRow = $pdo->query("SELECT id FROM pemetaan_cpl_versions WHERE versi='v1'")->fetch();
}
$v1 = (int)$verRow['id'];
$ins = $pdo->prepare('INSERT INTO peta_pemenuhan_cpl (version_id, cpl_id, semester, kode_mk_raw, mk_id) VALUES (?,?,?,?,?)');
foreach ($S['11. Peta Pemenuhan CPL (Kode)'] ?? [] as $i => $r) {
    if ($i === 0) continue;
    $cpl = get($r, 0);
    if (!isset($cplMap[$cpl])) continue;
    for ($c = 2; $c <= 9; $c++) {
        $cell = get($r, $c);
        if ($cell === '') continue;
        $sem = 'Semester ' . ($c - 1);
        foreach (preg_split('/\s*,\s*/', $cell) as $raw) {
            $raw = trim($raw);
            if ($raw === '') continue;
            $ins->execute([$v1, $cplMap[$cpl], $sem, $raw, $mkMap[$raw] ?? null]);
        }
    }
}

/* ---------------- 11 v2. Peta Pemenuhan CPL (v2) ---------------- */
$verRow = $pdo->query("SELECT id FROM pemetaan_cpl_versions WHERE versi='v2'")->fetch();
if (!$verRow) {
    $pdo->exec("INSERT INTO pemetaan_cpl_versions (versi, keterangan) VALUES ('v2','Peta Pemenuhan CPL (v2)')");
    $verRow = $pdo->query("SELECT id FROM pemetaan_cpl_versions WHERE versi='v2'")->fetch();
}
$v2 = (int)$verRow['id'];
$ins = $pdo->prepare('INSERT INTO peta_pemenuhan_cpl (version_id, cpl_id, semester, keterangan) VALUES (?,?,?,?)');
$currentCpl = null;
$semesters = ['1', '2', '3', '4', '5', '6', '7', '8'];
foreach ($S['11. Peta Pemenuhan CPL (v2)'] ?? [] as $r) {
    $first = get($r, 0);
    if ($first === 'CPL') { $currentCpl = null; continue; }
    if (preg_match('/^CPL\d+$/', $first) && isset($cplMap[$first])) {
        $currentCpl = $cplMap[$first];
        continue;
    }
    if (!is_numeric($first) && $first !== '') continue;
    // baris data lanjutan untuk cpl terakhir
    if ($currentCpl === null) continue;
    for ($c = 1; $c <= 8; $c++) {
        $val = get($r, $c);
        if ($val === '' || is_numeric($val)) continue;
        $ins->execute([$v2, $currentCpl, $semesters[$c - 1], $val]);
    }
}

/* ---------------- 12. CPMK ---------------- */
$ins = $pdo->prepare('INSERT INTO cpmk (kode_cpmk, cpl_id, deskripsi) VALUES (?,?,?)');
foreach ($S['12. CPMK'] ?? [] as $i => $r) {
    if ($i === 0) continue;
    $kode = get($r, 1);
    if ($kode === '') continue;
    $ins->execute([$kode, $cplId(get($r, 0)), get($r, 2)]);
}
$cpmkMap = [];
foreach ($pdo->query('SELECT id, kode_cpmk FROM cpmk') as $x) $cpmkMap[$x['kode_cpmk']] = (int)$x['id'];

$pdo->exec('INSERT IGNORE INTO cpl_cpmk (cpl_id, cpmk_id)
            SELECT cpl_id, id FROM cpmk WHERE cpl_id IS NOT NULL');

/* ---------------- 13. Pemenuhan CPL-CPMK-MK ---------------- */
$ins = $pdo->prepare('INSERT IGNORE INTO cpmk_mk (cpmk_id, mk_id, semester) VALUES (?,?,?)');
foreach ($S['13. Pemenuhan CPL-CPMK-MK'] ?? [] as $i => $r) {
    if ($i === 0) continue;
    $cpmk = get($r, 1);
    if (!isset($cpmkMap[$cpmk])) continue;
    for ($c = 3; $c <= 10; $c++) {
        $cell = get($r, $c);
        if ($cell === '') continue;
        $sem = 'Semester ' . ($c - 2);
        foreach (preg_split('/\s*,\s*/', $cell) as $raw) {
            $raw = trim($raw);
            if ($raw === '' || !isset($mkMap[$raw])) continue;
            $ins->execute([$cpmkMap[$cpmk], $mkMap[$raw], $sem]);
        }
    }
}

/* ---------------- 15. Sub-CPMK ---------------- */
$ins = $pdo->prepare('INSERT INTO sub_cpmk (kode_sub, mk_id, cpmk_id, cpl_id, deskripsi) VALUES (?,?,?,?,?)');
foreach ($S['15. Sub-CPMK'] ?? [] as $i => $r) {
    if ($i === 0) continue;
    $kodeMk = get($r, 0);
    $desc = get($r, 4);
    if ($kodeMk === '' && $desc === '') continue;
    $ins->execute([
        null,
        $mkId($kodeMk),
        $cpmkMap[get($r, 3)] ?? null,
        $cplId(get($r, 2)),
        $desc,
    ]);
}

/* ---------------- 16. Teknik Penilaian CPMK ---------------- */
$ins = $pdo->prepare(
    'INSERT INTO teknik_penilaian_cpmk
     (mk_id, kode_mk_raw, cpmk_id, kode_cpmk_raw, cpl_id, kode_cpl_raw,
      mbkm, partisipasi, observasi, unjuk_kerja, tes_uts, tes_uas, tes_lisan)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
);
foreach ($S['16. Teknik Penilaian CPMK'] ?? [] as $i => $r) {
    if ($i === 0) continue;
    $kodeMk = get($r, 0);
    if ($kodeMk === '') continue;
    $ins->execute([
        $mkId($kodeMk), $kodeMk, $cpmkMap[get($r, 3)] ?? null, get($r, 3),
        $cplId(get($r, 2)), get($r, 2),
        get($r, 4) ?: null, get($r, 5) ?: null, get($r, 6) ?: null,
        get($r, 7) ?: null, get($r, 8) ?: null, get($r, 9) ?: null, get($r, 10) ?: null,
    ]);
}

/* ---------------- 17. Tahap & Mekanisme Penilaian ---------------- */
$ins = $pdo->prepare(
    'INSERT INTO mekanisme_penilaian
     (mk_id, kode_mk_raw, cpmk_id, kode_cpmk_raw, cpl_id, kode_cpl_raw, tahap, teknik, instrumen, kriteria, bobot)
     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
);
foreach ($S['17. Tahap dan Mekanisme Penilai'] ?? [] as $i => $r) {
    if ($i === 0) continue;
    $kodeMk = get($r, 0);
    if ($kodeMk === '') continue;
    $ins->execute([
        $mkId($kodeMk), $kodeMk, $cpmkMap[get($r, 3)] ?? null, get($r, 3),
        $cplId(get($r, 2)), get($r, 2),
        get($r, 4) ?: null, get($r, 5) ?: null, get($r, 6) ?: null,
        get($r, 7) ?: null, get($r, 8) ?: null,
    ]);
}

/* ---------------- 18 / 18a. Bobot Penilaian ---------------- */
function import_bobot(PDO $pdo, array $rows, string $table, string $versi, array $mkMap, array $cpmkMap, array $cplMap): void
{
    $ins = $pdo->prepare(
        "INSERT INTO $table
         (versi, mk_id, kode_mk_raw, cpmk_id, kode_cpmk_raw, cpl_id, kode_cpl_raw,
          mbkm, partisipasi, observasi, unjuk_kerja, tes_uts, tes_uas, tes_lisan, total)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    foreach ($rows as $i => $r) {
        if ($i === 0) continue;
        $kodeMk = get($r, 0);
        if ($kodeMk === '') continue;
        $ins->execute([
            $versi, $mkMap[$kodeMk] ?? null, $kodeMk,
            $cpmkMap[get($r, 3)] ?? null, get($r, 3),
            $cplMap[get($r, 2)] ?? null, get($r, 2),
            get($r, 4) ?: null, get($r, 5) ?: null, get($r, 6) ?: null,
            get($r, 7) ?: null, get($r, 8) ?: null, get($r, 9) ?: null,
            get($r, 10) ?: null, get($r, 11) ?: null,
        ]);
    }
}
import_bobot($pdo, $S['18. Bobot Penilaian'] ?? [], 'bobot_penilaian', '18', $mkMap, $cpmkMap, $cplMap);
import_bobot($pdo, $S['18a. Bobot Penilaian'] ?? [], 'bobot_penilaian_alternatif', '18a', $mkMap, $cpmkMap, $cplMap);

/* ---------------- 19 / 20. Rumusan Akhir ---------------- */
function import_rumusan(PDO $pdo, array $rows, string $table, array $mkMap, array $cpmkMap, array $cplMap): void
{
    $ins = $pdo->prepare(
        "INSERT INTO $table
         (mk_id, kode_mk_raw, cpmk_id, kode_cpmk_raw, cpl_id, kode_cpl_raw, skor_maks, total)
         VALUES (?,?,?,?,?,?,?,?)"
    );
    foreach ($rows as $i => $r) {
        if ($i === 0) continue;
        $kodeMk = get($r, 0);
        if ($kodeMk === '') continue;
        $ins->execute([
            $mkMap[$kodeMk] ?? null, $kodeMk,
            $cpmkMap[get($r, 3)] ?? null, get($r, 3),
            $cplMap[get($r, 2)] ?? null, get($r, 2),
            get($r, 4) ?: null, get($r, 5) ?: null,
        ]);
    }
}
import_rumusan($pdo, $S['19. Rumusan Akhir MK'] ?? [], 'rumusan_akhir_mk', $mkMap, $cpmkMap, $cplMap);
import_rumusan($pdo, $S['20. Rumusan Akhir CPL'] ?? [], 'rumusan_akhir_cpl', $mkMap, $cpmkMap, $cplMap);

/* ---------------- Inventaris Kelengkapan MK ---------------- */
$ins = $pdo->prepare(
    'INSERT INTO inventaris_kelengkapan
     (mk_id, kode_mk_raw, nama_mk, semester, ada_13, ada_15, ada_16, ada_17, ada_18, ada_18a, ada_19, ada_20, jml_ada, status)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
);
foreach ($S['Inventaris Kelengkapan MK'] ?? [] as $i => $r) {
    if ($i === 0) continue;
    $kodeMk = get($r, 1);
    if ($kodeMk === '') continue;
    $jml = (int)get($r, 12);
    $status = get($r, 13);
    $ins->execute([
        $mkMap[$kodeMk] ?? null, $kodeMk, get($r, 2), get($r, 3),
        get($r, 4) ?: null, get($r, 5) ?: null, get($r, 6) ?: null, get($r, 7) ?: null,
        get($r, 8) ?: null, get($r, 9) ?: null, get($r, 10) ?: null, get($r, 11) ?: null,
        $jml, $status !== '' ? $status : 'Belum',
    ]);
}

/* ---------------- RPS Dosen (Rekap) ---------------- */
$ins = $pdo->prepare(
    'INSERT INTO rps (mk_id, kode_mk_raw, semester, dosen, cpl, cpmk, bobot, teknik, catatan, urutan)
     VALUES (?,?,?,?,?,?,?,?,?,?)'
);
$cur = ['kode' => '', 'smt' => '', 'dosen' => '', 'cpl' => '', 'catatan' => ''];
$urutan = 0;
foreach ($S['RPS Dosen (Rekap)'] ?? [] as $i => $r) {
    if ($i === 0) continue;
    if (get($r, 0) !== '' && is_numeric(get($r, 0))) {
        $cur = [
            'kode'    => get($r, 1),
            'smt'     => get($r, 3),
            'dosen'   => get($r, 4),
            'cpl'     => get($r, 5),
            'catatan' => get($r, 9),
        ];
    }
    if ($cur['kode'] === '') continue;
    $ins->execute([
        $mkMap[$cur['kode']] ?? null, $cur['kode'], $cur['smt'],
        $cur['dosen'] !== '' ? $cur['dosen'] : null,
        $cur['cpl'] !== '' ? $cur['cpl'] : null,
        get($r, 6) ?: null, get($r, 7) ?: null, get($r, 8) ?: null,
        $cur['catatan'] !== '' ? $cur['catatan'] : null,
        ++$urutan,
    ]);
}

/* ---------------- Ringkasan ---------------- */
echo "Impor selesai.\n";
foreach (['profil_lulusan', 'cpl', 'bahan_kajian', 'mata_kuliah', 'cpmk', 'sub_cpmk',
          'cpl_mk', 'bahan_kajian_mk', 'cpl_bahan_kajian', 'pl_cpl', 'cpl_bk_mk',
          'peta_pemenuhan_cpl', 'cpmk_mk', 'teknik_penilaian_cpmk', 'mekanisme_penilaian',
          'bobot_penilaian', 'bobot_penilaian_alternatif', 'rumusan_akhir_mk',
          'rumusan_akhir_cpl', 'rps', 'inventaris_kelengkapan'] as $t) {
    $n = (int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    printf("  %-28s %5d baris\n", $t, $n);
}
