<?php
/**
 * Shared bootstrap: configuration, session, error handling, helpers.
 */

declare(strict_types=1);

define('RP_ROOT', dirname(__DIR__));
define('RP_MIN_PHP', '8.0.0');

if (version_compare(PHP_VERSION, RP_MIN_PHP, '<')) {
    http_response_code(500);
    exit('Request Portal requires PHP ' . RP_MIN_PHP . ' or newer. Current: ' . PHP_VERSION);
}

$configFile = RP_ROOT . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('config.php not found. Copy config.sample.php to config.php and fill in the database credentials.');
}

/** @var array<string,mixed> $RP_CONFIG */
$RP_CONFIG = require $configFile;
if (!is_array($RP_CONFIG)) {
    http_response_code(500);
    exit('config.php must return an array.');
}

$GLOBALS['RP_CONFIG'] = $RP_CONFIG;

/**
 * Read a configuration value using dot notation: rp_config('db.host').
 */
function rp_config(string $path, mixed $default = null): mixed
{
    $value = $GLOBALS['RP_CONFIG'];
    foreach (explode('.', $path) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

date_default_timezone_set((string) rp_config('app.timezone', 'UTC'));

if (rp_config('app.debug', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/db.php';

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_name((string) rp_config('security.session_name', 'rp_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => rp_base_url() !== '' ? rp_base_url() : '/',
        'secure'   => rp_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

rp_init_language();
