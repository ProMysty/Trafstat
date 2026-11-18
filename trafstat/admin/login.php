<?php
/**
 * admin/login.php
 * Administrator authentication.
 * Public page — do NOT call require_admin() here.
 */
require_once __DIR__ . '/../includes/init.php';

// Already logged in? Go straight through.
$existing = current_admin();
if ($existing !== null) {
    redirect(!empty($existing['must_change_password'])
        ? url('admin/change_password.php')
        : url('admin/dashboard.php'));
}

$errors      = [];
$oldUsername = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!csrf_verify($submittedToken)) {
        $errors[] = 'Your security token expired. Please reload the page and try again.';
    } else {
        $oldUsername = post_string('username');
        $password    = $_POST['password'] ?? '';

        $result = attempt_login($oldUsername, $password);

        if ($result['ok']) {
            $admin = current_admin();
            if ($admin !== null && !empty($admin['must_change_password'])) {
                set_flash('info', 'For your security, please set a new password before continuing.');
                redirect(url('admin/change_password.php'));
            }
            set_flash('success', 'Welcome back, ' . ($admin['full_name'] ?? 'Administrator') . '.');
            redirect(url('admin/dashboard.php'));
        }

        $errors[] = $result['message'];
    }
}

$flashes = take_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Administrator Login — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
</head>
<body>
<main class="auth-page">
    <div class="auth-card">

        <div class="auth-brand">
            <span class="auth-brand-mark">T</span>
            <div>
                <div class="auth-brand-name"><?= e(APP_NAME) ?></div>
                <div class="auth-brand-sub">Administration</div>
            </div>
        </div>

        <h1 class="auth-title">Administrator Login</h1>
        <p class="auth-subtitle">
            Authorized personnel of the Pagadian City traffic management office only.
        </p>

        <?php foreach ($flashes as $type => $messages):
            $type = in_array($type, ['success','error','warning','info'], true) ? $type : 'info';
            foreach ((array) $messages as $message): ?>
                <div class="alert alert-<?= e($type) ?>"><?= e($message) ?></div>
            <?php endforeach;
        endforeach; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $message): ?>
                    <div><?= e($message) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('admin/login.php')) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text"
                       class="form-control"
                       id="username"
                       name="username"
                       value="<?= e($oldUsername) ?>"
                       autocomplete="username"
                       autocapitalize="off"
                       spellcheck="false"
                       required
                       autofocus>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="password-wrap">
                    <input type="password"
                           class="form-control"
                           id="password"
                           name="password"
                           autocomplete="current-password"
                           required>
                    <button type="button"
                            class="password-toggle"
                            data-toggle-password="password"
                            aria-label="Show or hide password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
            </button>
        </form>

        <div class="auth-footer">
            <?= e(APP_NAME) ?> &middot; <?= e(date('Y')) ?> &middot; Pagadian City
        </div>
    </div>
</main>

<script src="<?= e(url('assets/js/main.js')) ?>"></script>
</body>
</html>