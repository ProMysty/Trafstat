<?php
/**
 * TrafStat — environment configuration TEMPLATE.
 * Copy this file to config/config.php and fill in real values.
 * config/config.php must NEVER be committed to source control.
 */

// ---- Database -------------------------------------------------------
define('DB_HOST', 'mysql://${{MYSQLUSER}}:${{MYSQLPASSWORD}}@${{RAILWAY_TCP_PROXY_DOMAIN}}:${{RAILWAY_TCP_PROXY_PORT}}/${{MYSQLDATABASE}}');
define('DB_PORT', '3306');
define('DB_NAME', 'railway');
define('DB_USER', 'root');
define('DB_PASS', 'KVZiCiptvEjBjgqnLxCzZVXdHIDjdcpz');

// ---- Application ----------------------------------------------------
define('APP_ENV', 'development');                 // 'development' | 'production'
define('APP_URL', 'http://localhost/trafstat');   // no trailing slash
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