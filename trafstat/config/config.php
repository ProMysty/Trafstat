<?php
/**
 * TrafStat — environment configuration TEMPLATE.
 * Copy this file to config/config.php and fill in real values.
 * config/config.php must NEVER be committed to source control.
 */

// ---- Database -------------------------------------------------------
define('DB_HOST', 'mysql.railway.internal');
define('DB_PORT', '3306');
define('DB_NAME', 'railway');
define('DB_USER', 'root');
define('DB_PASS', 'ryxXuOlooAgAazkdjhTluqfVEePNtSph');

// ---- Application ----------------------------------------------------
define('APP_ENV', 'development'); // 'development' | 'production'

$httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
if (!preg_match('/\A(?:[a-z0-9.-]+|\[[0-9a-f:]+\])(?::[0-9]{1,5})?\z/iD', $httpHost)) {
    throw new RuntimeException('Invalid HTTP host.');
}

$hostName = strtolower(preg_replace('/:[0-9]+\z/', '', $httpHost) ?? $httpHost);
$forwardedProto = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')[0]));
$scheme = in_array($forwardedProto, ['http', 'https'], true)
    ? $forwardedProto
    : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
$basePath = in_array($hostName, ['localhost', '127.0.0.1', '[::1]'], true)
    ? '/trafstat'
    : '';

define('APP_URL', $scheme . '://' . $httpHost . $basePath);
define('APP_TIMEZONE', 'Asia/Manila');
define('APP_NAME', 'TrafStat');

// ---- Session --------------------------------------------------------
define('SESSION_NAME', 'TRAFSTAT_SID');
define('SESSION_IDLE_TIMEOUT', 1800);      // 30 minutes of inactivity
define('SESSION_ABSOLUTE_TIMEOUT', 28800); // 8 hours maximum lifetime

// ---- Authentication -------------------------------------------------
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);
define('PASSWORD_MIN_LENGTH', 12);

// ---- Public statistics disclosure -----------------------------------
// Groups smaller than this are suppressed on public pages.
// Threshold MUST be confirmed with the office's Data Protection Officer.
define('PUBLIC_SUPPRESSION_THRESHOLD', 5);