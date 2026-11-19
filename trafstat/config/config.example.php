<?php
/**
 * TrafStat — environment configuration TEMPLATE.
 * Copy to config/config.php and edit.
 * config/config.php must NEVER be committed to source control.
 */

// ---------------------------------------------------------------------
// Environment detection (do not edit — copy this file as-is)
// ---------------------------------------------------------------------
require_once __DIR__ . '/environment.php';

// ---------------------------------------------------------------------
// Database
//
// Prefer environment variables where the host supports them
// (Railway, Render, DigitalOcean App Platform). Otherwise, edit
// the fallback strings below.
// ---------------------------------------------------------------------
define('DB_HOST', getenv('TRAFSTAT_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('TRAFSTAT_DB_PORT') ?: '3306');
define('DB_NAME', getenv('TRAFSTAT_DB_NAME') ?: 'trafstat_db');
define('DB_USER', getenv('TRAFSTAT_DB_USER') ?: 'trafstat_user');
define('DB_PASS', getenv('TRAFSTAT_DB_PASS') ?: 'REPLACE_WITH_STRONG_PASSWORD');

// ---------------------------------------------------------------------
// Application URL
//
// Leave APP_URL_OVERRIDE empty to auto-detect from the request.
// Set to an absolute URL only when auto-detection is wrong
// (e.g., behind a CDN that rewrites the Host header).
// ---------------------------------------------------------------------
define('APP_URL_OVERRIDE', '');
define('APP_URL', APP_URL_OVERRIDE !== ''
    ? rtrim(APP_URL_OVERRIDE, '/')
    : detect_app_url());

// Convenience: "/" or "/trafstat" — used for cookie scoping, etc.
define('APP_BASE_PATH', app_base_path_from_url(APP_URL));

// ---------------------------------------------------------------------
// Application
// ---------------------------------------------------------------------
define('APP_ENV',      getenv('TRAFSTAT_ENV')      ?: 'development');
define('APP_TIMEZONE', getenv('TRAFSTAT_TIMEZONE') ?: 'Asia/Manila');
define('APP_NAME',     'TrafStat');

// ---------------------------------------------------------------------
// Session
// ---------------------------------------------------------------------
define('SESSION_NAME',             'TRAFSTAT_SID');
define('SESSION_IDLE_TIMEOUT',     1800);
define('SESSION_ABSOLUTE_TIMEOUT', 28800);

// ---------------------------------------------------------------------
// Authentication
// ---------------------------------------------------------------------
define('MAX_LOGIN_ATTEMPTS',     5);
define('LOGIN_LOCKOUT_MINUTES',  15);
define('PASSWORD_MIN_LENGTH',    12);

// ---------------------------------------------------------------------
// Public statistics disclosure
// ---------------------------------------------------------------------
define('PUBLIC_SUPPRESSION_THRESHOLD', 5);