<?php
require_once __DIR__ . '/../includes/error_handler.php';
obe_configure_error_handling();

/**
 * Konfigurasi koneksi database + konstanta aplikasi.
 * Nilai dapat dioverride lewat environment variable server (Apache/PHP-FPM),
 * misalnya SetEnv DB_USER / DB_PASS. Jangan menaruh password produksi di repo.
 * Default di bawah adalah kredensial lokal XAMPP (tanpa password).
 */
function env_value(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value === false && isset($_SERVER[$key])) {
        $value = $_SERVER[$key];
    }
    return ($value === false || $value === '') ? $default : (string)$value;
}

define('DB_HOST', env_value('DB_HOST', '127.0.0.1'));
define('DB_NAME', env_value('DB_NAME', 'db_obe'));
define('DB_USER', env_value('DB_USER', 'root'));
define('DB_PASS', env_value('DB_PASS', ''));

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
            $incidentId = obe_log_database_failure($e, 'db_connection');
            obe_render_generic_error($incidentId);
            exit(1);
        }
    }
    return $pdo;
}
