<?php
/**
 * Konfigurasi koneksi database + konstanta aplikasi.
 * Sesuaikan DB_USER / DB_PASS bila XAMPP Anda memakai password.
 */
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'db_obe');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'SI Kurikulum OBE');
define('APP_PRODI', 'Teknik Informatika - Universitas YARSI');
define('BASE_URL', '/obe');

date_default_timezone_set('Asia/Jakarta');

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Detail error tidak ditampilkan ke pengguna agar tidak membocorkan
            // konfigurasi server/database.
            http_response_code(500);
            die('<div style="font-family:sans-serif;padding:24px">'
                . '<h3>Koneksi database gagal</h3>'
                . '<p>Pastikan MySQL di XAMPP berjalan dan database <b>db_obe</b> sudah diimpor.</p></div>');
        }
    }
    return $pdo;
}
