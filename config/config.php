<?php
declare(strict_types=1);

// Keep hosting-specific credentials in config/local.php.
// That file is intentionally excluded from Git.
$localConfig = [];
$localConfigFile = __DIR__ . '/local.php';
if (is_file($localConfigFile)) {
    $loadedConfig = require $localConfigFile;
    if (is_array($loadedConfig)) {
        $localConfig = $loadedConfig;
    }
}

define('APP_NAME', 'LAMAS');
define('BASE_URL', (string)($localConfig['base_url'] ?? '/lamas'));
define('DB_HOST', (string)($localConfig['db_host'] ?? (getenv('LAMAS_DB_HOST') ?: '127.0.0.1')));
define('DB_NAME', (string)($localConfig['db_name'] ?? (getenv('LAMAS_DB_NAME') ?: '')));
define('DB_USER', (string)($localConfig['db_user'] ?? (getenv('LAMAS_DB_USER') ?: '')));
define('DB_PASS', (string)($localConfig['db_pass'] ?? (getenv('LAMAS_DB_PASS') ?: '')));

date_default_timezone_set('Asia/Manila');

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax'
    ]);
    session_start();
}
