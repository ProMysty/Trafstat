<?php
/**
 * admin/logout.php
 * POST + CSRF performs the logout; GET redirects back (prevents CSRF-logout
 * via an <img> tag or similar nuisance attack).
 */
require_once __DIR__ . '/../includes/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    perform_logout();
    // session is destroyed; start a fresh one so we can flash a message.
    start_secure_session();
    set_flash('success', 'You have been logged out.');
    redirect(url('admin/login.php'));
}

// GET — don't log the user out silently.
if (current_admin() !== null) {
    set_flash('info', 'To log out, use the Log out button in the account menu.');
    redirect(url('admin/dashboard.php'));
}

redirect(url('admin/login.php'));