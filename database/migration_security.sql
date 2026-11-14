-- =====================================================================
-- Migrasi keamanan autentikasi untuk database db_obe yang sudah berjalan.
-- Jalankan melalui phpMyAdmin (tab SQL) atau:
--   mysql -u root db_obe < database/migration_security.sql
--
-- Aman dijalankan berulang (CREATE TABLE IF NOT EXISTS).
-- Tidak menghapus atau mengubah data pengguna.
-- =====================================================================

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  identity_hash CHAR(64) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_attempt_identity (identity_hash, attempted_at),
  KEY idx_login_attempt_ip (ip, attempted_at)
) ENGINE=InnoDB;
