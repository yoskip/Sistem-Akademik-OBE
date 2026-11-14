-- =====================================================================
-- Sistem Informasi Kurikulum Berbasis OBE
-- Program Studi Teknik Informatika - Universitas YARSI
-- Database: db_obe  (MySQL / MariaDB)
-- Impor file ini melalui phpMyAdmin, lalu jalankan import/import_excel.php
-- untuk memuat seluruh data dari file Excel kurikulum.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS db_obe
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_obe;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS inventaris_kelengkapan;
DROP TABLE IF EXISTS rps;
DROP TABLE IF EXISTS rumusan_akhir_cpl;
DROP TABLE IF EXISTS rumusan_akhir_mk;
DROP TABLE IF EXISTS bobot_penilaian_alternatif;
DROP TABLE IF EXISTS bobot_penilaian;
DROP TABLE IF EXISTS mekanisme_penilaian;
DROP TABLE IF EXISTS teknik_penilaian_cpmk;
DROP TABLE IF EXISTS teknik_penilaian;
DROP TABLE IF EXISTS sub_cpmk;
DROP TABLE IF EXISTS cpmk_mk;
DROP TABLE IF EXISTS cpl_cpmk;
DROP TABLE IF EXISTS cpmk;
DROP TABLE IF EXISTS peta_pemenuhan_cpl;
DROP TABLE IF EXISTS pemetaan_cpl_versions;
DROP TABLE IF EXISTS cpl_bk_mk;
DROP TABLE IF EXISTS cpl_mk;
DROP TABLE IF EXISTS bahan_kajian_mk;
DROP TABLE IF EXISTS cpl_bahan_kajian;
DROP TABLE IF EXISTS mata_kuliah;
DROP TABLE IF EXISTS bahan_kajian;
DROP TABLE IF EXISTS pl_cpl;
DROP TABLE IF EXISTS cpl;
DROP TABLE IF EXISTS profil_lulusan;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------- roles
CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(30) NOT NULL UNIQUE,
  label VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

INSERT INTO roles (id, nama, label) VALUES
  (1, 'admin',   'Administrator'),
  (2, 'kaprodi', 'Ketua Program Studi'),
  (3, 'dosen',   'Dosen');

-- ---------------------------------------------------------------- users
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role_id INT NOT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Password: admin123 / kaprodi123 / dosen123
INSERT INTO users (nama, username, email, password, role_id) VALUES
  ('Administrator Sistem', 'admin', 'admin@yarsi.ac.id',
   '$2y$10$OxyGOX/fhPMjHNVeLZtP0eEyR2ESF.xAoTJ36DjvdseXzc9nno8P2', 1),
  ('Ketua Program Studi', 'kaprodi', 'kaprodi@yarsi.ac.id',
   '$2y$10$CzlD63X88wRGLSdSY42fPO9WKR1cjOwQpQrvVIdFh7whQif6.6aWy', 2),
  ('Dosen Pengampu', 'dosen', 'dosen@yarsi.ac.id',
   '$2y$10$3TVWLycNNON0jDhYBi07d.7qe6ifG4c7Fg9cLj2YHUM.r2ZBBLuBO', 3);

-- -------------------------------------------------------- profil_lulusan
CREATE TABLE profil_lulusan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode_pl VARCHAR(10) NOT NULL UNIQUE,
  nama_pl TEXT NOT NULL,
  deskripsi TEXT NULL
) ENGINE=InnoDB;

INSERT INTO profil_lulusan (kode_pl, nama_pl, deskripsi) VALUES
('PL01', 'Lulusan mampu bertindak sebagai profesional pembelajar sepanjang hayat yang memiliki  karateristik Smart, Compassionate & Reliable', NULL),
('PL02', 'Lulusan mampu mengidentifikasi pengetahuan, prinsip, standar, dan langkah-langkah yang perlu dilakukan, dalam disiplin computing atau bidang relevan lainnya, untuk mendesain solusi teknologi informasi sesuai dengan kebutuhan pengguna di dalam atau luar negeri.', NULL),
('PL03', 'Lulusan mampu berpikir logis, kritis serta sistematis dalam memanfaatkan pengetahuan yang dimilikinya dalam menyelesaikan masalah nyata.', NULL),
('PL04', 'Lulusan mampu mendesain, mengevaluasi, dan mengimplementasikan solusi computing sesuai kebutuhan pengguna dalam bidang sistem terdistribusi, rekayasa perangkat lunak, atau kecerdasan buatan dengan mempertimbangkan performa dan keamanan yang berstandar nasional dan internasional', NULL);

-- ------------------------------------------------------------------ cpl
CREATE TABLE cpl (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode_cpl VARCHAR(10) NOT NULL UNIQUE,
  deskripsi TEXT NOT NULL,
  domain VARCHAR(5) NOT NULL
) ENGINE=InnoDB;

INSERT INTO cpl (kode_cpl, deskripsi, domain) VALUES
('CPL01', 'Menjadi muslim yang Smart: cerdas, profesional, pandai (fathonah); Compasionate: menyampaikan (tabligh), berakhlak baik, bersyukur, pemaaf, sabar, santun, dan bijak; dan Reliable: bertanggung jawab, kuat, jujur, benar, dan dapat dipercaya (shiddiq dan amanah)', 'S'),
('CPL02', 'Mampu mencari dan menentukan pengetahuan, algoritma, prinsip, standar, dan langkah-langkah computing untuk memecahkan permasalahan yang dihadapi pengguna di dalam atau luar negeri', 'P'),
('CPL03', 'Mampu mengidentifikasi langkah-langkah yang dibutuhkan untuk mendesain solusi teknologi informasi dan mengelola pengerjaannya dengan mempertimbangkan berbagai bidang ilmu terkait.', 'P'),
('CPL04', 'Memahami konsep teoritis informatika dan cara mentransfer pengetahuannya ke domain permasalahan yang berbeda.', 'P'),
('CPL05', 'Mampu memelihara dan mengembangkan jaringan kerja dengan pembimbing, kolega, sejawat baik di dalam maupun luar lembaganya dengan menjunjung akhlak seorang muslim', 'KU'),
('CPL06', 'Mampu menunjukkan kinerja terukur yang terdokumentasi dalam memahami dan mendesain solusi dari suatu permasalahan dengan menggunakan prinsip-prinsip saintifik berasaskan ruhul Islam', 'KU'),
('CPL07', 'Mampu menerapkan keterampilan dasar sebagai seorang muslim yang merupakan bagian dari kewarganegaraan global', 'KU'),
('CPL08', 'Mampu mengimplementasikan kebutuhan computing dengan mempertimbangkan berbagai metode/algoritma yang sesuai', 'KK'),
('CPL09', 'Mampu merancang, menganalisis, dan mengevaluasi antarmuka dan aplikasi interaktif dengan mempertimbangkan kebutuhan pengguna serta berbagai disiplin keilmuan terkait.', 'KK'),
('CPL10', 'Mampu mendesain dan mengevaluasi solusi computing multi-platform yang memenuhi kebutuhan pengguna di dalam atau luar negeri.', 'KK');

-- --------------------------------------------------------- pl_cpl (CPL-PL)
CREATE TABLE pl_cpl (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cpl_id INT NOT NULL,
  pl_id INT NOT NULL,
  UNIQUE KEY uq_pl_cpl (cpl_id, pl_id),
  CONSTRAINT fk_plcpl_cpl FOREIGN KEY (cpl_id) REFERENCES cpl(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_plcpl_pl FOREIGN KEY (pl_id) REFERENCES profil_lulusan(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- --------------------------------------------------------- bahan_kajian
CREATE TABLE bahan_kajian (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode_bk VARCHAR(10) NOT NULL UNIQUE,
  nama_bk VARCHAR(150) NOT NULL,
  deskripsi TEXT NULL,
  acuan VARCHAR(50) NULL
) ENGINE=InnoDB;

INSERT INTO bahan_kajian (kode_bk, nama_bk, deskripsi, acuan) VALUES
('BK01','Social Issues and Professional Practice',NULL,'CC2020'),
('BK02','Project Management',NULL,NULL),
('BK03','User Experience Design',NULL,NULL),
('BK04','Data and Information Management',NULL,NULL),
('BK05','Parallel and Distributed Computing',NULL,'CC2020'),
('BK06','Computer Networks',NULL,'CC2020'),
('BK07','Software Design',NULL,NULL),
('BK08','Operating Systems',NULL,'CC2020'),
('BK09','Data Structures, Algorithms and Complexity',NULL,NULL),
('BK10','Programming Languages',NULL,'CC2020'),
('BK11','Programming Fundamentals',NULL,NULL),
('BK12','Computing Systems Fundamentals',NULL,'CC2020'),
('BK13','Architecture and Organization',NULL,NULL),
('BK14','Graphics and Visualization',NULL,NULL),
('BK15','Intelligent Systems',NULL,'CC2020'),
('BK16','Platform-based Development',NULL,'CC2020'),
('BK17','Cyber Security',NULL,NULL),
('BK18','Computational Science',NULL,NULL),
('BK19','Discrete Structures',NULL,NULL),
('BK20','Human-Computer Interaction',NULL,NULL),
('BK21','Software Development Fundamentals',NULL,'CC2020'),
('BK22','Software Engineering',NULL,'CC2020'),
('BK23','Systems Analysis & Design',NULL,NULL),
('BK24','Software Quality, Verification and Validation',NULL,NULL),
('BK25','Software Modeling and Analysis',NULL,NULL),
('BK26','Pengembangan Diri',NULL,NULL),
('BK27','Metodologi Penelitian',NULL,NULL);

-- ------------------------------------------------- cpl_bahan_kajian (CPL-BK)
CREATE TABLE cpl_bahan_kajian (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cpl_id INT NOT NULL,
  bahan_kajian_id INT NOT NULL,
  UNIQUE KEY uq_cpl_bk (cpl_id, bahan_kajian_id),
  CONSTRAINT fk_cplbk_cpl FOREIGN KEY (cpl_id) REFERENCES cpl(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cplbk_bk FOREIGN KEY (bahan_kajian_id) REFERENCES bahan_kajian(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------- mata_kuliah
CREATE TABLE mata_kuliah (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode_mk VARCHAR(30) NOT NULL UNIQUE,
  nama_mk VARCHAR(200) NOT NULL,
  sks DECIMAL(5,2) NOT NULL DEFAULT 0,
  sks_teori DECIMAL(5,2) NOT NULL DEFAULT 0,
  praktik DECIMAL(5,2) NOT NULL DEFAULT 0,
  praktik_lapangan DECIMAL(5,2) NOT NULL DEFAULT 0,
  semester VARCHAR(10) NOT NULL,
  kelompok VARCHAR(60) NULL,
  kode_internal VARCHAR(20) NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------- bahan_kajian_mk (BK-MK)
CREATE TABLE bahan_kajian_mk (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bahan_kajian_id INT NOT NULL,
  mk_id INT NOT NULL,
  UNIQUE KEY uq_bk_mk (bahan_kajian_id, mk_id),
  CONSTRAINT fk_bkmk_bk FOREIGN KEY (bahan_kajian_id) REFERENCES bahan_kajian(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_bkmk_mk FOREIGN KEY (mk_id) REFERENCES mata_kuliah(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------------- cpl_mk
CREATE TABLE cpl_mk (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cpl_id INT NOT NULL,
  mk_id INT NOT NULL,
  UNIQUE KEY uq_cpl_mk (cpl_id, mk_id),
  CONSTRAINT fk_cplmk_cpl FOREIGN KEY (cpl_id) REFERENCES cpl(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cplmk_mk FOREIGN KEY (mk_id) REFERENCES mata_kuliah(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------- cpl_bk_mk
CREATE TABLE cpl_bk_mk (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cpl_id INT NOT NULL,
  bahan_kajian_id INT NOT NULL,
  mk_id INT NULL,
  kode_mk_raw VARCHAR(40) NOT NULL,
  UNIQUE KEY uq_cpl_bk_mk (cpl_id, bahan_kajian_id, kode_mk_raw),
  CONSTRAINT fk_cplbkmk_cpl FOREIGN KEY (cpl_id) REFERENCES cpl(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cplbkmk_bk FOREIGN KEY (bahan_kajian_id) REFERENCES bahan_kajian(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cplbkmk_mk FOREIGN KEY (mk_id) REFERENCES mata_kuliah(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------- pemetaan_cpl_versions & peta
CREATE TABLE pemetaan_cpl_versions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  versi VARCHAR(20) NOT NULL UNIQUE,
  keterangan TEXT NULL
) ENGINE=InnoDB;

INSERT INTO pemetaan_cpl_versions (versi, keterangan) VALUES
('v1', 'Peta Pemenuhan CPL (Kode) - faithful copy'),
('v2', 'Peta Pemenuhan CPL (v2) - faithful copy');

CREATE TABLE peta_pemenuhan_cpl (
  id INT AUTO_INCREMENT PRIMARY KEY,
  version_id INT NOT NULL,
  cpl_id INT NULL,
  semester VARCHAR(15) NULL,
  kode_mk_raw VARCHAR(255) NULL,
  mk_id INT NULL,
  keterangan TEXT NULL,
  CONSTRAINT fk_peta_ver FOREIGN KEY (version_id) REFERENCES pemetaan_cpl_versions(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_peta_cpl FOREIGN KEY (cpl_id) REFERENCES cpl(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_peta_mk FOREIGN KEY (mk_id) REFERENCES mata_kuliah(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- cpmk
CREATE TABLE cpmk (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode_cpmk VARCHAR(20) NOT NULL UNIQUE,
  cpl_id INT NULL,
  deskripsi TEXT NOT NULL,
  CONSTRAINT fk_cpmk_cpl FOREIGN KEY (cpl_id) REFERENCES cpl(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE cpl_cpmk (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cpl_id INT NOT NULL,
  cpmk_id INT NOT NULL,
  UNIQUE KEY uq_cpl_cpmk (cpl_id, cpmk_id),
  CONSTRAINT fk_cplcpmk_cpl FOREIGN KEY (cpl_id) REFERENCES cpl(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cplcpmk_cpmk FOREIGN KEY (cpmk_id) REFERENCES cpmk(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------- cpmk_mk (pemenuhan CPL-CPMK-MK)
CREATE TABLE cpmk_mk (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cpmk_id INT NOT NULL,
  mk_id INT NOT NULL,
  semester VARCHAR(15) NULL,
  UNIQUE KEY uq_cpmk_mk (cpmk_id, mk_id),
  CONSTRAINT fk_cpmkmk_cpmk FOREIGN KEY (cpmk_id) REFERENCES cpmk(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cpmkmk_mk FOREIGN KEY (mk_id) REFERENCES mata_kuliah(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------- sub_cpmk
CREATE TABLE sub_cpmk (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode_sub VARCHAR(30) NULL,
  mk_id INT NULL,
  cpmk_id INT NULL,
  cpl_id INT NULL,
  deskripsi TEXT NOT NULL,
  CONSTRAINT fk_sub_mk FOREIGN KEY (mk_id) REFERENCES mata_kuliah(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_sub_cpmk FOREIGN KEY (cpmk_id) REFERENCES cpmk(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_sub_cpl FOREIGN KEY (cpl_id) REFERENCES cpl(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------ teknik_penilaian
CREATE TABLE teknik_penilaian (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO teknik_penilaian (nama) VALUES
('MBKM'),('Partisipasi (Kehadiran / Quiz)'),('Observasi (Praktek / Tugas)'),
('Unjuk Kerja (Presentasi)'),('Tes Tulis (UTS)'),('Tes Tulis (UAS)'),
('Tes Lisan (Tugas Kelompok)');

-- ------------------------------------------------ teknik_penilaian_cpmk
CREATE TABLE teknik_penilaian_cpmk (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mk_id INT NULL,
  kode_mk_raw VARCHAR(40) NOT NULL,
  cpmk_id INT NULL,
  kode_cpmk_raw VARCHAR(40) NOT NULL,
  cpl_id INT NULL,
  kode_cpl_raw VARCHAR(20) NULL,
  mbkm VARCHAR(5) NULL,
  partisipasi VARCHAR(5) NULL,
  observasi VARCHAR(5) NULL,
  unjuk_kerja VARCHAR(5) NULL,
  tes_uts VARCHAR(5) NULL,
  tes_uas VARCHAR(5) NULL,
  tes_lisan VARCHAR(5) NULL,
  CONSTRAINT fk_tpc_mk FOREIGN KEY (mk_id) REFERENCES mata_kuliah(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_tpc_cpmk FOREIGN KEY (cpmk_id) REFERENCES cpmk(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_tpc_cpl FOREIGN KEY (cpl_id) REFERENCES cpl(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------- mekanisme_penilaian
CREATE TABLE mekanisme_penilaian (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mk_id INT NULL,
  kode_mk_raw VARCHAR(40) NOT NULL,
  cpmk_id INT NULL,
  kode_cpmk_raw VARCHAR(40) NULL,
  cpl_id INT NULL,
  kode_cpl_raw VARCHAR(20) NULL,
  tahap TEXT NULL,
  teknik TEXT NULL,
  instrumen TEXT NULL,
  kriteria TEXT NULL,
  bobot VARCHAR(20) NULL,
  CONSTRAINT fk_mek_mk FOREIGN KEY (mk_id) REFERENCES mata_kuliah(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_mek_cpmk FOREIGN KEY (cpmk_id) REFERENCES cpmk(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_mek_cpl FOREIGN KEY (cpl_id) REFERENCES cpl(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------- bobot_penilaian
CREATE TABLE bobot_penilaian (
  id INT AUTO_INCREMENT PRIMARY KEY,
  versi VARCHAR(10) NOT NULL DEFAULT '18',
  mk_id INT NULL,
  kode_mk_raw VARCHAR(40) NOT NULL,
  cpmk_id INT NULL,
  kode_cpmk_raw VARCHAR(120) NULL,
  cpl_id INT NULL,
  kode_cpl_raw VARCHAR(20) NULL,
  mbkm VARCHAR(10) NULL,
  partisipasi VARCHAR(10) NULL,
  observasi VARCHAR(10) NULL,
  unjuk_kerja VARCHAR(10) NULL,
  tes_uts VARCHAR(10) NULL,
  tes_uas VARCHAR(10) NULL,
  tes_lisan VARCHAR(10) NULL,
  total VARCHAR(10) NULL,
  CONSTRAINT fk_bobot_mk FOREIGN KEY (mk_id) REFERENCES mata_kuliah(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_bobot_cpmk FOREIGN KEY (cpmk_id) REFERENCES cpmk(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_bobot_cpl FOREIGN KEY (cpl_id) REFERENCES cpl(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE bobot_penilaian_alternatif (
  id INT AUTO_INCREMENT PRIMARY KEY,
  versi VARCHAR(10) NOT NULL DEFAULT '18a',
  mk_id INT NULL,
  kode_mk_raw VARCHAR(40) NOT NULL,
  cpmk_id INT NULL,
  kode_cpmk_raw VARCHAR(120) NULL,
  cpl_id INT NULL,
  kode_cpl_raw VARCHAR(20) NULL,
  mbkm VARCHAR(10) NULL,
  partisipasi VARCHAR(10) NULL,
  observasi VARCHAR(10) NULL,
  unjuk_kerja VARCHAR(10) NULL,
  tes_uts VARCHAR(10) NULL,
  tes_uas VARCHAR(10) NULL,
  tes_lisan VARCHAR(10) NULL,
  total VARCHAR(10) NULL,
  CONSTRAINT fk_bobalt_mk FOREIGN KEY (mk_id) REFERENCES mata_kuliah(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_bobalt_cpmk FOREIGN KEY (cpmk_id) REFERENCES cpmk(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_bobalt_cpl FOREIGN KEY (cpl_id) REFERENCES cpl(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------- rumusan_akhir_mk
CREATE TABLE rumusan_akhir_mk (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mk_id INT NULL,
  kode_mk_raw VARCHAR(40) NOT NULL,
  cpmk_id INT NULL,
  kode_cpmk_raw VARCHAR(40) NULL,
  cpl_id INT NULL,
  kode_cpl_raw VARCHAR(20) NULL,
  skor_maks VARCHAR(15) NULL,
  total VARCHAR(15) NULL,
  CONSTRAINT fk_ramk_mk FOREIGN KEY (mk_id) REFERENCES mata_kuliah(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ramk_cpmk FOREIGN KEY (cpmk_id) REFERENCES cpmk(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ramk_cpl FOREIGN KEY (cpl_id) REFERENCES cpl(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------- rumusan_akhir_cpl
CREATE TABLE rumusan_akhir_cpl (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mk_id INT NULL,
  kode_mk_raw VARCHAR(40) NOT NULL,
  cpmk_id INT NULL,
  kode_cpmk_raw VARCHAR(40) NULL,
  cpl_id INT NULL,
  kode_cpl_raw VARCHAR(20) NULL,
  skor_maks VARCHAR(15) NULL,
  total VARCHAR(15) NULL,
  CONSTRAINT fk_racpl_mk FOREIGN KEY (mk_id) REFERENCES mata_kuliah(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_racpl_cpmk FOREIGN KEY (cpmk_id) REFERENCES cpmk(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_racpl_cpl FOREIGN KEY (cpl_id) REFERENCES cpl(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------------ rps
CREATE TABLE rps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mk_id INT NULL,
  kode_mk_raw VARCHAR(40) NOT NULL,
  semester VARCHAR(15) NULL,
  dosen VARCHAR(150) NULL,
  cpl TEXT NULL,
  cpmk VARCHAR(40) NULL,
  bobot VARCHAR(20) NULL,
  teknik TEXT NULL,
  catatan TEXT NULL,
  urutan INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_rps_mk FOREIGN KEY (mk_id) REFERENCES mata_kuliah(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------- inventaris_kelengkapan
CREATE TABLE inventaris_kelengkapan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mk_id INT NULL,
  kode_mk_raw VARCHAR(40) NOT NULL,
  nama_mk VARCHAR(200) NULL,
  semester VARCHAR(15) NULL,
  ada_13 VARCHAR(5) NULL,
  ada_15 VARCHAR(5) NULL,
  ada_16 VARCHAR(5) NULL,
  ada_17 VARCHAR(5) NULL,
  ada_18 VARCHAR(5) NULL,
  ada_18a VARCHAR(5) NULL,
  ada_19 VARCHAR(5) NULL,
  ada_20 VARCHAR(5) NULL,
  jml_ada INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'Belum',
  CONSTRAINT fk_inv_mk FOREIGN KEY (mk_id) REFERENCES mata_kuliah(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- --------------------------------------------------------- login_attempts
-- Pencatatan percobaan login gagal untuk rate limiting (tanpa password).
CREATE TABLE login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  identity_hash CHAR(64) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_attempt_identity (identity_hash, attempted_at),
  KEY idx_login_attempt_ip (ip, attempted_at)
) ENGINE=InnoDB;

-- ------------------------------------------------------------- audit_logs
CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  aksi VARCHAR(50) NOT NULL,
  modul VARCHAR(50) NULL,
  keterangan TEXT NULL,
  ip VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;
