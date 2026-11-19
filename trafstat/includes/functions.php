<?php
/**
 * includes/functions.php
 * Shared helpers: redirects, flash messages, validation, formatting.
 */

function url(string $path = ''): string
{
    // Use the dynamically detected base path.
    // Returns "/assets/..." on root domains (Railway)
    // Returns "/trafstat/assets/..." on subdirectory installs (XAMPP)
    $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '/') ? APP_BASE_PATH : '';
    return $base . '/' . ltrim($path, '/');
}
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/* ------------------------------------------------------------------ */
/* Flash messages                                                      */
/* ------------------------------------------------------------------ */

function set_flash(string $type, string $message): void
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][$type][] = $message;
}

function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($flashes) ? $flashes : [];
}

/* ------------------------------------------------------------------ */
/* Input handling                                                      */
/* ------------------------------------------------------------------ */

/**
 * Trim a posted string and strip control characters.
 */
function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = trim($value);
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
}

function post_int(string $key): ?int
{
    $value = $_POST[$key] ?? null;
    if ($value === null || $value === '') {
        return null;
    }
    return filter_var($value, FILTER_VALIDATE_INT) === false
        ? null
        : (int) $value;
}

function is_valid_date(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function is_valid_time(string $value): bool
{
    $time = DateTime::createFromFormat('H:i', $value);
    return $time !== false && $time->format('H:i') === $value;
}

/* ------------------------------------------------------------------ */
/* Password policy                                                     */
/* ------------------------------------------------------------------ */

/**
 * Returns an array of human-readable policy violations (empty = valid).
 */
function password_policy_errors(string $password): array
{
    $errors = [];

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Contain at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Contain at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Contain at least one number.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Contain at least one special character.';
    }

    return $errors;
}

/* ------------------------------------------------------------------ */
/* Formatting                                                          */
/* ------------------------------------------------------------------ */

function format_date(?string $value, string $format = 'M j, Y'): string
{
    if (empty($value)) {
        return '—';
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? '—' : date($format, $timestamp);
}

function format_datetime(?string $value): string
{
    return format_date($value, 'M j, Y g:i A');
}

/* ------------------------------------------------------------------ */
/* Form state (redisplay after validation failure)                     */
/* ------------------------------------------------------------------ */

/**
 * Stash form errors + previous input, keyed to a specific edit target.
 * $editId is null for "create new" forms.
 */
function set_form_state(array $errors, array $oldInput, ?int $editId = null): void
{
    $_SESSION['form_state'] = [
        'errors'  => array_values($errors),
        'old'     => $oldInput,
        'edit_id' => $editId,
    ];
}

/**
 * Consume and clear the stored form state.
 * Returns ['errors' => array, 'old' => array, 'edit_id' => ?int].
 */
function take_form_state(): array
{
    $state = $_SESSION['form_state'] ?? [];
    unset($_SESSION['form_state']);

    return [
        'errors'  => is_array($state['errors']  ?? null) ? $state['errors']  : [],
        'old'     => is_array($state['old']     ?? null) ? $state['old']     : [],
        'edit_id' => isset($state['edit_id']) ? (int) $state['edit_id'] : null,
    ];
}