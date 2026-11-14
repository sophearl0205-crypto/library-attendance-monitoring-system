<?php
declare(strict_types=1);

define('APP_NAME', 'LAMAS');
define('BASE_URL', '/lamas'); // Change if your folder name differs.
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'library_attendance_db');
define('DB_USER', 'root');
define('DB_PASS', '');

date_default_timezone_set('Asia/Manila');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax'
    ]);
    session_start();
}
