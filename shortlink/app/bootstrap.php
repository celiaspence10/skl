<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

load_env(base_path('.env'));
ensure_storage_dirs();

date_default_timezone_set('Asia/Phnom_Penh');

ini_set('session.cookie_httponly', '1');
if (is_https()) {
    ini_set('session.cookie_secure', '1');
}
ini_set('session.cookie_samesite', 'Lax');

if (session_status() === PHP_SESSION_NONE) {
    session_name('shortlink_session');
    session_start();
}

$errorLog = storage_path('logs/app.log');
ini_set('log_errors', '1');
ini_set('error_log', $errorLog);

if (!env('APP_DEBUG', false)) {
    ini_set('display_errors', '0');
}

set_exception_handler('handle_exception');
