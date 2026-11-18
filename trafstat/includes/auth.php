<?php
/**
 * includes/auth.php
 * Administrator authentication and authorization.
 */

/**
 * Hash of the current PHP session id, stored server-side so sessions can be
 * revoked centrally. Never store the raw session id in the database.
 */
function current_session_hash(): string
{
    return hash('sha256', session_id());
}

/**
 * Return the authenticated administrator, or null.
 * Verifies the account is active AND the session has not been revoked.
 */
function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }

    return db_one(
        "SELECT a.admin_id, a.username, a.full_name, a.email,
                a.account_status, a.must_change_password, a.last_login_at
           FROM administrators a
     INNER JOIN admin_sessions s ON s.admin_id = a.admin_id
          WHERE a.admin_id      = :admin_id
            AND s.session_hash  = :session_hash
            AND s.revoked_at    IS NULL
            AND a.account_status = 'active'
          LIMIT 1",
        [
            ':admin_id'     => $_SESSION['admin_id'],
            ':session_hash' => current_session_hash(),
        ]
    );
}

function is_logged_in(): bool
{
    return current_admin() !== null;
}

/**
 * Authorization gate for every administrative page and action.
 * Never rely on hiding links or buttons.
 */
function require_admin(): array
{
    $admin = current_admin();

    if ($admin === null) {
        $_SESSION = [];
        session_regenerate_id(true);
        set_flash('error', 'Please log in to continue.');
        redirect(url('admin/login.php'));
    }

    return $admin;
}

/**
 * Attempt a login. Returns ['ok' => bool, 'message' => string].
 */
function attempt_login(string $username, string $password): array
{
    $username = trim($username);
    $ip       = client_ip();

    if ($username === '' || $password === '') {
        return ['ok' => false, 'message' => 'Please enter both username and password.'];
    }

    $windowStart = date('Y-m-d H:i:s', time() - (LOGIN_LOCKOUT_MINUTES * 60));

    $recentFailures = (int) db_value(
        'SELECT COUNT(*)
           FROM login_attempts
          WHERE was_successful = 0
            AND (username = :username OR ip_address = :ip)
            AND created_at >= :window_start',
        [
            ':username'     => $username,
            ':ip'           => $ip,
            ':window_start' => $windowStart,
        ]
    );

    if ($recentFailures >= MAX_LOGIN_ATTEMPTS) {
        record_login_attempt($username, $ip, false);
        log_activity(
            null,
            'LOGIN_BLOCKED',
            'Login blocked by rate limit for username "' . $username . '".',
            'login_attempts',
            null,
            ['reason' => 'rate_limit']
        );
        return [
            'ok'      => false,
            'message' => 'Too many failed login attempts. Please try again after '
                         . LOGIN_LOCKOUT_MINUTES . ' minutes.',
        ];
    }

    $admin = db_one(
        'SELECT * FROM administrators WHERE username = :username LIMIT 1',
        [':username' => $username]
    );

    $genericError = 'Invalid username or password.';

    if ($admin === null) {
        record_login_attempt($username, $ip, false);
        log_activity(
            null,
            'LOGIN_FAILED',
            'Failed login attempt for unknown username "' . $username . '".',
            null,
            null,
            ['reason' => 'unknown_user']
        );
        return ['ok' => false, 'message' => $genericError];
    }

    $adminId = (int) $admin['admin_id'];

    if ($admin['account_status'] !== 'active') {
        record_login_attempt($username, $ip, false);
        log_activity($adminId, 'LOGIN_FAILED', 'Login attempted on a non-active account.',
            'administrators', $adminId, ['reason' => 'account_status']);
        return ['ok' => false, 'message' => 'This account is not currently active. Contact the system administrator.'];
    }

    if (!empty($admin['locked_until']) && strtotime($admin['locked_until']) > time()) {
        record_login_attempt($username, $ip, false);
        log_activity($adminId, 'LOGIN_FAILED', 'Login attempted on a locked account.',
            'administrators', $adminId, ['reason' => 'locked']);
        return ['ok' => false, 'message' => 'This account is temporarily locked. Please try again later.'];
    }

    if (!password_verify($password, $admin['password_hash'])) {
        $attempts = (int) $admin['failed_login_attempts'] + 1;

        $lockedUntil = null;
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $lockedUntil = date('Y-m-d H:i:s', time() + (LOGIN_LOCKOUT_MINUTES * 60));
        }

        db_execute(
            'UPDATE administrators
                SET failed_login_attempts = :attempts,
                    locked_until = :locked_until
              WHERE admin_id = :admin_id',
            [
                ':attempts'     => $attempts,
                ':locked_until' => $lockedUntil,
                ':admin_id'     => $adminId,
            ]
        );

        record_login_attempt($username, $ip, false);
        log_activity($adminId, 'LOGIN_FAILED', 'Incorrect password supplied.',
            'administrators', $adminId, ['failed_attempts' => $attempts]);

        return ['ok' => false, 'message' => $genericError];
    }

    // ---- Success -----------------------------------------------------
    if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
        db_execute(
            'UPDATE administrators SET password_hash = :hash WHERE admin_id = :admin_id',
            [':hash' => password_hash($password, PASSWORD_DEFAULT), ':admin_id' => $adminId]
        );
    }

    session_regenerate_id(true);

    $_SESSION['admin_id']      = $adminId;
    $_SESSION['created_at']    = time();
    $_SESSION['last_activity'] = time();
    unset($_SESSION['csrf_token']); // fresh token for the new session

    db_execute(
        'UPDATE administrators
            SET failed_login_attempts = 0,
                locked_until = NULL,
                last_login_at = NOW()
          WHERE admin_id = :admin_id',
        [':admin_id' => $adminId]
    );

    db_execute(
        'INSERT INTO admin_sessions (session_hash, admin_id, ip_address, user_agent)
         VALUES (:session_hash, :admin_id, :ip, :user_agent)',
        [
            ':session_hash' => current_session_hash(),
            ':admin_id'     => $adminId,
            ':ip'           => $ip,
            ':user_agent'   => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]
    );

    record_login_attempt($username, $ip, true);
    log_activity($adminId, 'LOGIN_SUCCESS', 'Administrator logged in successfully.');

    return ['ok' => true, 'message' => 'Login successful.'];
}

/**
 * Terminate the current administrator session.
 */
function perform_logout(): void
{
    $admin = current_admin();

    if ($admin !== null) {
        log_activity((int) $admin['admin_id'], 'LOGOUT', 'Administrator logged out.');
        db_execute(
            'UPDATE admin_sessions SET revoked_at = NOW() WHERE session_hash = :hash',
            [':hash' => current_session_hash()]
        );
    }

    destroy_session();
}

/**
 * Record an authentication attempt for rate limiting and monitoring.
 */
function record_login_attempt(string $username, string $ip, bool $successful): void
{
    try {
        db_execute(
            'INSERT INTO login_attempts (username, ip_address, was_successful)
             VALUES (:username, :ip, :successful)',
            [
                ':username'   => substr($username, 0, 50),
                ':ip'         => $ip,
                ':successful' => $successful ? 1 : 0,
            ]
        );
    } catch (Throwable $exception) {
        error_log('[TrafStat][AUTH] ' . $exception->getMessage());
    }
}

/**
 * Gate for every authenticated admin page.
 * Enforces login AND the mandatory first-login password change.
 *
 * Exceptions: change_password.php and logout.php must call require_admin()
 * directly to avoid a redirect loop.
 */
function require_admin_ready(): array
{
    $admin = require_admin();

    if (!empty($admin['must_change_password'])) {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if ($script !== 'change_password.php' && $script !== 'logout.php') {
            set_flash('info', 'Please set a new password before continuing.');
            redirect(url('admin/change_password.php'));
        }
    }

    return $admin;
}