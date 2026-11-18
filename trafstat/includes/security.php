<?php

/**
 * Per-request CSP nonce. Generated once, reused everywhere via static.
 * Modern browsers only execute inline <script nonce="..."> tags whose
 * nonce matches the one advertised in Content-Security-Policy.
 */
function csp_nonce(): string
{
    static $nonce = null;
    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(16));
    }
    return $nonce;
}

/**
 * includes/security.php
 * Security headers, session hardening, escaping helpers.
 */

/**
 * Send baseline security headers. Call before any output.
 */

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(self), camera=(), microphone=()');
    header('Cross-Origin-Opener-Policy: same-origin');

    $nonce = csp_nonce();

    // Adjust these CDN entries when additional libraries are introduced.
    $csp = "default-src 'self'; "
         . "img-src 'self' data: https://*.tile.openstreetmap.org https://unpkg.com; "
         . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com; "
         . "script-src 'self' https://cdn.jsdelivr.net https://unpkg.com 'nonce-{$nonce}'; "
         . "font-src 'self' https://cdn.jsdelivr.net; "
         . "connect-src 'self'; "
         . "frame-ancestors 'none'; "
         . "base-uri 'self'; "
         . "form-action 'self'";

    header('Content-Security-Policy: ' . $csp);

    if (APP_ENV === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Start the hardened PHP session with idle and absolute timeouts.
 */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $secure = (APP_ENV === 'production') || $https;

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) SESSION_ABSOLUTE_TIMEOUT);

    session_start();

    $now = time();

    $idleExpired = isset($_SESSION['last_activity'])
        && ($now - (int) $_SESSION['last_activity']) > SESSION_IDLE_TIMEOUT;

    $absoluteExpired = isset($_SESSION['created_at'])
        && ($now - (int) $_SESSION['created_at']) > SESSION_ABSOLUTE_TIMEOUT;

    if ($idleExpired || $absoluteExpired) {
        expire_current_session(
            $idleExpired
                ? 'Your session expired due to inactivity. Please log in again.'
                : 'Your session reached its maximum duration. Please log in again.'
        );
        return;
    }

    $_SESSION['last_activity'] = $now;

    if (!isset($_SESSION['created_at'])) {
        $_SESSION['created_at'] = $now;
    }
}

/**
 * Clear authentication state but keep the session usable for a flash message.
 */
function expire_current_session(string $reason): void
{
    $_SESSION = [];
    session_regenerate_id(true);

    $_SESSION['created_at']    = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['flash']['error'][] = $reason;
}

/**
 * Fully destroy the session and its cookie.
 */
function destroy_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * HTML-escape a value for safe output. Use for EVERY dynamic output.
 */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Client IP address. X-Forwarded-For is deliberately ignored unless a
 * trusted reverse proxy is configured later.
 */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}