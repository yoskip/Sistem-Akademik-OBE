<?php
require_once __DIR__ . '/includes/functions.php';
if (is_logged_in()) {
    redirect(BASE_URL . '/dashboard/index.php');
}
redirect(BASE_URL . '/auth/login.php');
