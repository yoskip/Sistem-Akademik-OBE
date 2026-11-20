<?php
/**
 * Pencatatan event keamanan ke file internal.
 * Hanya field yang diizinkan yang ditulis; superglobals tidak pernah dicatat.
 */

function obe_log_directory(): string
{
    $dir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    @chmod($dir, 0700);
    return $dir;
}

function obe_write_log_line(string $file, string $line): void
{
    static $busy = false;
    if ($busy) {
        return;
    }
    $busy = true;
    try {
        @file_put_contents(obe_log_directory() . DIRECTORY_SEPARATOR . $file, $line, FILE_APPEND | LOCK_EX);
        @chmod(obe_log_directory() . DIRECTORY_SEPARATOR . $file, 0600);
    } catch (Throwable $ignored) {
        // Logging gagal tidak boleh memicu error lanjutan atau memberi info ke pengguna.
    } finally {
        $busy = false;
    }
}

function obe_sanitize_log_value(mixed $value, int $max = 240): string
{
    $text = (string)$value;
    $text = preg_replace('/[\r\n\t]+/', ' ', $text) ?? $text;
    $text = strip_tags($text);
    $text = preg_replace(
        '/\b(password|passwd|pwd|pass|token|secret|api[_-]?key|authorization|cookie|session|set-cookie|bindpw|binddn)\s*[:=]\s*\S+/i',
        '$1=[REDACTED]',
        $text
    ) ?? $text;
    $text = preg_replace('/\b[0-9]{10}\b/', '[NPM_MASKED]', $text) ?? $text;
    $text = preg_replace('/\b[a-f0-9]{64}\b/i', '[HASH]', $text) ?? $text;
    $text = preg_replace('/\b(SELECT|INSERT\s+INTO|UPDATE\s+\S+\s+SET|DELETE\s+FROM|SQLSTATE\[[^\]]*\])/i', '[SQL_DETAIL_REDACTED]', $text) ?? $text;
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;
    $text = trim($text);
    return mb_substr($text, 0, $max);
}

function obe_mask_identity(string $identity): string
{
    $identity = trim($identity);
    if (preg_match('/^[0-9]{10}$/', $identity)) {
        return substr($identity, 0, 4) . '***' . substr($identity, -3);
    }
    if (filter_var($identity, FILTER_VALIDATE_EMAIL)) {
        [$local, $domain] = explode('@', $identity, 2);
        return mb_substr($local, 0, 2) . '***@' . $domain;
    }
    if ($identity === '') {
        return '-';
    }
    return mb_substr($identity, 0, 2) . '***';
}

function obe_request_ip(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return obe_sanitize_log_value($ip, 45);
}

/**
 * @param array<string,mixed> $context
 */
function security_event(string $action, string $result, array $context = []): void
{
    $allowed = ['user_id', 'npm', 'identity', 'identity_hash', 'role', 'from_role', 'to_role', 'incident_id', 'detail', 'ip'];
    $record = [
        'time'     => date('c'),
        'ip'       => isset($context['ip']) ? obe_sanitize_log_value($context['ip'], 45) : obe_request_ip(),
        'action'   => obe_sanitize_log_value($action, 60),
        'result'   => obe_sanitize_log_value($result, 30),
    ];

    foreach ($allowed as $key) {
        if (!array_key_exists($key, $context)) {
            continue;
        }
        if ($key === 'user_id') {
            $record['user_id'] = (int)$context['user_id'];
            continue;
        }
        if ($key === 'ip' || $key === 'action' || $key === 'result') {
            continue;
        }
        if ($key === 'identity' || $key === 'npm') {
            $identity = (string)$context[$key];
            if ($identity !== '') {
                $record['npm_masked'] = obe_mask_identity($identity);
                $record['identity_hash'] = hash('sha256', strtolower(trim($identity)));
            }
            continue;
        }
        $record[$key] = obe_sanitize_log_value($context[$key]);
    }

    $json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return;
    }
    obe_write_log_line('security.log', $json . PHP_EOL);
}
