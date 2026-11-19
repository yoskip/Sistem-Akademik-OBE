# Audit Query SQL & Proteksi Prepared Statements (Mode A)
**Sistem Informasi Akademik Berbasis Outcome-Based Education (OBE)**  
**Universitas YARSI**

---

## 1. Metodologi Audit Query

Audit menyeluruh dilakukan terhadap seluruh pemanggilan database MySQL pada proyek Sistem Informasi Kurikulum OBE Universitas YARSI. Fokus pemeriksaan mencakup:
1. **Pencarian Pola Rentan:** Menemukan query SQL mentah (*raw queries*) yang menggabungkan variabel pengguna secara langsung melalui konkatenasi string (`"SELECT * FROM ... WHERE x = '$var'"`).
2. **Pengujian Dynamic Query:** Mengaudit klausa SQL dinamis yang tidak dapat diparameterisasi oleh PDO, seperti nama tabel (`FROM $table`), nama kolom (`UPDATE table SET $col = ?`), dan klausa pengurutan (`ORDER BY $col`).
3. **Pemisahan Instruksi dan Data:** Memastikan seluruh query DML (`SELECT`, `INSERT`, `UPDATE`, `DELETE`) menggunakan **PDO Prepared Statements** dengan *bound parameters*.

---

## 2. Perbandingan Query: Sebelum vs Sesudah Perbaikan

### 2.1. Query Autentikasi Login (`auth/login.php`)

* **Sebelum Perbaikan:**
  ```php
  // Rentan jika variabel $identity dievaluasi tanpa validasi atau prepared statement
  $stmt = db()->prepare("SELECT u.*, r.nama AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = '$identity'");
  $stmt->execute();
  ```
* **Sesudah Perbaikan:**
  ```php
  // 1. Validasi allowlist server-side terlebih dahulu
  if ($identity === '' || !validate_login_identity($identity)) {
      http_response_code(422);
      $error = 'Format NPM, username, atau email tidak valid.';
  } else {
      // 2. Prepared statement dengan parameter binding ganda (?)
      $stmt = db()->prepare(
          'SELECT u.*, r.nama AS role FROM users u
           JOIN roles r ON r.id = u.role_id
           WHERE (u.username = ? OR u.email = ?) LIMIT 1'
      );
      $stmt->execute([$identity, $identity]);
      $user = $stmt->fetch();
  }
  ```
* **Hasil Keamanan:** Payload seperti `' OR 1=1--` diperlakukan murni sebagai string literal. Struktur query tidak berubah dan tidak terjadi bypass autentikasi.

---

### 2.2. Query Dinamis Nama Tabel pada Dashboard (`dashboard/index.php`)

* **Sebelum Perbaikan:**
  ```php
  // Nama tabel diinterpolasi langsung ke string SQL
  $count = fn(string $table) => (int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
  ```
* **Sesudah Perbaikan (Allowlist Table Guard):**
  ```php
  // Proteksi allowlist tabel yang diizinkan dihitung
  $allowedTables = [
      'profil_lulusan', 'cpl', 'cpmk', 'sub_cpmk',
      'bahan_kajian', 'mata_kuliah', 'rps', 'users'
  ];
  $count = function(string $table) use ($pdo, $allowedTables): int {
      if (!in_array($table, $allowedTables, true)) {
          throw new InvalidArgumentException("Tabel '$table' tidak diizinkan.");
      }
      return (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
  };
  ```
* **Hasil Keamanan:** Mencegah injeksi nama tabel atau subquery yang berpotensi membocorkan skema sistem.

---

### 2.3. Query Referensi Mapping Tabel (`includes/functions.php` `id_map()`)

* **Sebelum Perbaikan:**
  ```php
  function id_map(string $table, string $kodeCol): array {
      $map = [];
      foreach (db()->query("SELECT id, $kodeCol FROM $table") as $row) {
          $map[$row[$kodeCol]] = (int)$row['id'];
      }
      return $map;
  }
  ```
* **Sesudah Perbaikan (Strict Table & Column Pair Allowlist):**
  ```php
  function id_map(string $table, string $kodeCol): array {
      $allowed = [
          'cpl'            => 'kode_cpl',
          'profil_lulusan' => 'kode_pl',
          'bahan_kajian'   => 'kode_bk',
          'mata_kuliah'    => 'kode_mk',
          'cpmk'           => 'kode_cpmk',
      ];
      if (!isset($allowed[$table]) || $allowed[$table] !== $kodeCol) {
          throw new InvalidArgumentException("Tabel atau kolom referensi tidak diizinkan: $table.$kodeCol");
      }

      $map = [];
      $stmt = db()->prepare("SELECT id, `$kodeCol` FROM `$table`");
      $stmt->execute();
      foreach ($stmt->fetchAll() as $row) {
          $map[$row[$kodeCol]] = (int)$row['id'];
      }
      return $map;
  }
  ```
* **Hasil Keamanan:** Nilai `$table` dan `$kodeCol` hanya dapat berasal dari pasangan yang sah pada kurikulum OBE.

---

### 2.4. Query Pembaruan Kolom Dinamis pada Penilaian (`penilaian/bobot.php` & `penilaian/teknik.php`)

* **Sebelum Perbaikan:**
  ```php
  // Kunci form POST langsung dirangkai ke query UPDATE tanpa verifikasi nama kolom
  $sets = [];
  $params = [];
  foreach ($_POST['cols'] as $col => $val) {
      $sets[] = "$col = ?";
      $params[] = $val;
  }
  $sql = "UPDATE penilaian_bobot SET " . implode(', ', $sets) . " WHERE id = ?";
  ```
* **Sesudah Perbaikan (Strict Column Allowlist Verification):**
  ```php
  // Daftar kolom sah pada tabel penilaian
  $allowedBobotCols = [
      'cpl_1', 'cpl_2', 'cpl_3', 'cpl_4',
      'cpl_5', 'cpl_6', 'cpl_7', 'cpl_8', 'total'
  ];
  $sets = [];
  $params = [];
  foreach ($submittedCols as $col => $val) {
      if (!in_array($col, $allowedBobotCols, true)) {
          http_response_code(422);
          flash('danger', "Kolom penilaian '$col' tidak sah.");
          redirect('bobot.php');
      }
      $sets[] = "`$col` = ?";
      $params[] = is_numeric($val) ? (float)$val : null;
  }
  $params[] = $id;
  $stmt = $pdo->prepare("UPDATE penilaian_bobot SET " . implode(', ', $sets) . " WHERE id = ?");
  $stmt->execute($params);
  ```
* **Hasil Keamanan:** Mencegah injeksi nama kolom sembarang untuk memodifikasi baris atau kolom sensitif lain dalam database.

---

### 2.5. Query Pencarian / Filter URL (`profil_lulusan`, `cpl`, `mata_kuliah`, `bahan_kajian`)

* **Sebelum Perbaikan:**
  ```php
  // Rentan jika parameter q digabung langsung
  $q = $_GET['q'];
  $stmt = $pdo->query("SELECT * FROM cpl WHERE deskripsi LIKE '%$q%'");
  ```
* **Sesudah Perbaikan:**
  ```php
  $q = trim($_GET['q'] ?? '');
  $where = '';
  $params = [];
  if ($q !== '') {
      $q = mb_substr($q, 0, 100);
      $where = 'WHERE kode_cpl LIKE ? OR deskripsi LIKE ?';
      $params = ["%$q%", "%$q%"];
  }
  $stmt = $pdo->prepare("SELECT * FROM cpl $where ORDER BY kode_cpl LIMIT $limit OFFSET $offset");
  $stmt->execute($params);
  ```
* **Hasil Keamanan:** Karakter `%`, `_`, atau kutipan dari input pencarian diperlakukan sebagai nilai string aman dalam placeholder `?`.

---

## 3. Pembuktian Prepared Statements Menggunakan PDO

PDO dikonfigurasi dengan opsi keamanan berikut pada koneksi database (`config/database.php`):
1. `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` (Mencegah eksekusi senyap yang gagal).
2. `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC` (Mencegah paparan struktur objek internal).
3. Parameter binding memisahkan fase parsing SQL di MySQL Engine dari fase pengiriman data, sehingga payload injeksi tidak pernah dapat mengubah pohon sintaks (*Abstract Syntax Tree*) query.
