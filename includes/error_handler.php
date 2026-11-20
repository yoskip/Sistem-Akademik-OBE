<?php
require_once __DIR__ . '/security_log.php';

const OBE_GENERIC_ERROR_MESSAGE = 'Terjadi kesalahan pada sistem. Silakan coba kembali beberapa saat lagi. Jika masalah berlanjut, hubungi administrator.';

function obe_configure_error_handling(): void
{
    static $configured = false;
    if ($configured) {
        return;
    }
    $configured = true;

    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', obe_log_directory() . DIRECTORY_SEPARATOR . 'php_error.log');

    set_error_handler('obe_php_error_handler');
    set_exception_handler('obe_exception_handler');
    register_shutdown_function('obe_shutdown_handler');
}

function obe_incident_id(): string
{
    try {
        return 'ERR-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    } catch (Throwable $ignored) {
        return 'ERR-' . date('Ymd') . '-' . strtoupper(substr(md5((string)microtime(true)), 0, 6));
    }
}

function obe_exception_summary(Throwable $e): string
{
    return get_class($e) . ': ' . obe_sanitize_log_value($e->getMessage(), 180);
}

function obe_write_error_log(string $incidentId, Throwable|string $error, string $context): void
{
    static $busy = false;
    if ($busy) {
        return;
    }
    $busy = true;
    try {
        if ($error instanceof Throwable) {
            $detail = get_class($error) . ' di ' . basename($error->getFile()) . ':' . $error->getLine()
                . ' - ' . $error->getMessage();
        } else {
            $detail = (string)$error;
        }
        $line = json_encode([
            'time'        => date('c'),
            'incident_id' => $incidentId,
            'context'     => obe_sanitize_log_value($context, 60),
            'detail'      => obe_sanitize_log_value($detail, 800),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line !== false) {
            obe_write_log_line('php_error.log', $line . PHP_EOL);
        }
    } catch (Throwable $ignored) {
        // Jangan mengganggu penanganan error utama bila penulisan log gagal.
    } finally {
        $busy = false;
    }
}

function obe_render_generic_error(string $incidentId): void
{
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    if (PHP_SAPI === 'cli') {
        echo OBE_GENERIC_ERROR_MESSAGE . ' ID kejadian: ' . $incidentId . PHP_EOL;
        return;
    }
    $id = htmlspecialchars($incidentId, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Kesalahan Sistem</title></head><body style="font-family:sans-serif;padding:24px;max-width:720px">'
        . '<h3>Kesalahan Sistem</h3><p>' . OBE_GENERIC_ERROR_MESSAGE . '</p>'
        . '<p>ID kejadian: <code>' . $id . '</code></p></body></html>';
}

function obe_log_database_failure(Throwable $e, string $context = 'db_failure'): string
{
    $incidentId = obe_incident_id();
    obe_write_error_log($incidentId, $e, $context);
    security_event('db_failure', 'error', [
        'incident_id' => $incidentId,
        'detail'      => obe_exception_summary($e),
    ]);
    return $incidentId;
}

function obe_handle_exception(Throwable $e, string $context = 'exception', bool $render = true): string
{
    $incidentId = obe_incident_id();
    obe_write_error_log($incidentId, $e, $context);
    security_event($context === 'ldap_failure' ? 'ldap_failure' : 'application_error', 'error', [
        'incident_id' => $incidentId,
        'detail'      => obe_exception_summary($e),
    ]);
    if ($render) {
        obe_render_generic_error($incidentId);
    }
    return $incidentId;
}

function obe_exception_handler(Throwable $e): void
{
    obe_handle_exception($e, 'exception', true);
    exit(1);
}

function obe_php_error_handler(int $severity, string $message, string $file = '', int $line = 0): bool
{
    if (!(error_reporting() & $severity)) {
        return false;
    }

    if (in_array($severity, [E_ERROR, E_RECOVERABLE_ERROR, E_USER_ERROR], true)) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    if (in_array($severity, [E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE], true)) {
        $incidentId = obe_incident_id();
        $e = new ErrorException($message, 0, $severity, $file, $line);
        obe_write_error_log($incidentId, $e, 'php_error');
        security_event('php_error', 'error', [
            'incident_id' => $incidentId,
            'detail'      => obe_exception_summary($e),
        ]);
    }

    return true;
}

function obe_shutdown_handler(): void
{
    static $running = false;
    if ($running) {
        return;
    }
    $last = error_get_last();
    if ($last === null || !in_array($last['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    $running = true;
    obe_exception_handler(new ErrorException(
        (string)($last['message'] ?? 'Fatal error'),
        0,
        (int)($last['type'] ?? E_ERROR),
        (string)($last['file'] ?? ''),
        (int)($last['line'] ?? 0)
    ));
}
