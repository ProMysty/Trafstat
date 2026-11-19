<?php
/**
 * config/environment.php
 * Zero-dependency environment detection. Works on local XAMPP, a bare
 * Droplet, a subdirectory install, or behind a reverse proxy without
 * editing any config value.
 *
 * Included by config/config.php BEFORE any constants are defined.
 */

/**
 * Detect the URL path where TrafStat is installed, purely from filesystem
 * and server variables.
 *
 *   /opt/lampp/htdocs/trafstat     with DOCUMENT_ROOT=/opt/lampp/htdocs → "/trafstat"
 *   /var/www/trafstat              with DOCUMENT_ROOT=/var/www          → "/trafstat"
 *   /var/www/html                  with DOCUMENT_ROOT=/var/www/html     → "/"
 *   /srv/app                       (out of DOCUMENT_ROOT)               → "/"
 */
function detect_app_base_path(): string
{
    if (PHP_SAPI === 'cli') {
        return '/';
    }

    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
    $appRoot = realpath(__DIR__ . '/..');

    if ($docRoot === '' || $appRoot === false) {
        return '/';
    }

    // Normalise slashes so Windows dev machines work too.
    $docRoot = str_replace('\\', '/', $docRoot);
    $appRoot = str_replace('\\', '/', $appRoot);

    if (!str_starts_with($appRoot, $docRoot)) {
        return '/';
    }

    $relative = substr($appRoot, strlen($docRoot));
    $relative = '/' . trim($relative, '/');

    return $relative === '/' ? '/' : $relative;
}

/**
 * Detect the full application URL from the current request.
 *
 * Handles:
 *   - HTTP and HTTPS
 *   - Reverse proxies (X-Forwarded-Proto, X-Forwarded-Host)
 *   - Subdirectory installs
 *   - Custom ports
 *   - CLI contexts (falls back to TRAFSTAT_URL env var, or a dev default)
 */
function detect_app_url(): string
{
    if (PHP_SAPI === 'cli') {
        // Only used by tools/create_admin.php and dev scripts.
        // For real CLI use set TRAFSTAT_URL=https://example.gov.ph
        return rtrim(getenv('TRAFSTAT_URL') ?: 'http://localhost/trafstat', '/');
    }

    // ---- Scheme ------------------------------------------------------
    $scheme = 'http';
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        $scheme = 'https';
    }
    if (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        $scheme = 'https';
    }

    // ---- Host --------------------------------------------------------
    $host = $_SERVER['HTTP_X_FORWARDED_HOST']
        ?? $_SERVER['HTTP_HOST']
        ?? $_SERVER['SERVER_NAME']
        ?? 'localhost';

    // Strip anything malicious from the Host header — never trust it raw.
    if (!preg_match('/^[A-Za-z0-9.\-]+(?::\d{1,5})?$/', $host)) {
        $host = 'localhost';
    }

    $basePath = detect_app_base_path();
    if ($basePath === '/') {
        $basePath = '';
    }

    return $scheme . '://' . $host . $basePath;
}

/**
 * Base path of the application URL — "/" or "/trafstat" etc.
 * Used for session cookie scoping.
 */
function app_base_path_from_url(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH) ?: '/';
    return rtrim($path, '/') ?: '/';
}