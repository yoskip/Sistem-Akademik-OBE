<?php
/**
 * Test tambahan - Logika idle timeout (30 menit) dan absolute timeout (8 jam).
 * Jalankan: php tests/session_timeout_test.php
 * Pengujian murni tanpa HTTP, tidak memerlukan akun atau data pengguna.
 */
require_once __DIR__ . '/../includes/functions.php';

$failures = 0;

function check(bool $ok, string $label): void
{
    global $failures;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

$now = 1700000000;

check(!session_is_expired($now, $now, $now), 'Session baru dengan waktu lengkap dianggap valid');
check(!session_is_expired($now, $now - SESSION_IDLE_TIMEOUT, $now), 'Tepat 30 menit tanpa aktivitas belum dianggap expired');
check(session_is_expired($now, $now - SESSION_IDLE_TIMEOUT - 1, $now), 'Lebih dari 30 menit tanpa aktivitas dianggap expired');
check(!session_is_expired($now - SESSION_ABSOLUTE_TIMEOUT, $now, $now), 'Tepat 8 jam sejak login belum dianggap expired');
check(session_is_expired($now - SESSION_ABSOLUTE_TIMEOUT - 1, $now, $now), 'Lebih dari 8 jam sejak login dianggap expired');
check(!session_is_expired($now - SESSION_ABSOLUTE_TIMEOUT + 10, $now, $now), 'Aktivitas baru tidak membatalkan absolute timeout yang belum terlampaui');
check(session_is_expired(0, $now, $now), 'Session tanpa waktu login dianggap expired');
check(session_is_expired($now, 0, $now), 'Session tanpa waktu aktivitas dianggap expired');

exit($failures === 0 ? 0 : 1);
