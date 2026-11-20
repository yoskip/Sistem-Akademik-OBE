<?php
require_once __DIR__ . '/../includes/functions.php';
if (is_logged_in()) {
    log_activity('logout', 'auth', 'Pengguna keluar dari sistem');
    obe_logout_event();
}
send_no_cache_headers();
destroy_session_and_cookie();
redirect(BASE_URL . '/auth/login.php');
