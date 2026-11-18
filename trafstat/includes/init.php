<?php
/**
 * includes/init.php
 * Application bootstrap. Include this FIRST on every page.
 *
 *   require_once __DIR__ . '/../includes/init.php';
 */

require_once __DIR__ . '/../config/config.php';
date_default_timezone_set(APP_TIMEZONE);

if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/auth.php';

send_security_headers();
start_secure_session();