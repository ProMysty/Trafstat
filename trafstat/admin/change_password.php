<?php
/**
 * admin/change_password.php
 * Self-service password change, also used to satisfy the mandatory
 * first-login password change.
 *
 * Uses require_admin() (not require_admin_ready) so the mandatory-change
 * redirect does not loop back to this page.
 */
require_once __DIR__ . '/../includes/init.php';

$admin = require_admin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stored = db_one(
        'SELECT password_hash FROM administrators WHERE admin_id = :id',
        [':id' => $admin['admin_id']]
    );

    if ($stored === null) {
        $errors[] = 'Your administrator record could not be found. Please log in again.';
    } elseif ($current === '' || $new === '' || $confirm === '') {
        $errors[] = 'All fields are required.';
    } elseif (!password_verify($current, $stored['password_hash'])) {
        $errors[] = 'Your current password is incorrect.';
    } elseif ($new !== $confirm) {
        $errors[] = 'The new password and confirmation do not match.';
    } elseif (password_verify($new, $stored['password_hash'])) {
        $errors[] = 'The new password must be different from your current password.';
    } else {
        $policyErrors = password_policy_errors($new);

        if (!empty($policyErrors)) {
            $errors[] = 'The new password does not meet the required policy:';
            foreach ($policyErrors as $p) {
                $errors[] = '• ' . $p;
            }
        } else {
            db_execute(
                'UPDATE administrators
                    SET password_hash = :hash,
                        must_change_password = 0,
                        password_changed_at = NOW()
                  WHERE admin_id = :id',
                [
                    ':hash' => password_hash($new, PASSWORD_DEFAULT),
                    ':id'   => $admin['admin_id'],
                ]
            );

            // Invalidate every other active session for this account.
            db_execute(
                'UPDATE admin_sessions
                    SET revoked_at = NOW()
                  WHERE admin_id = :id
                    AND session_hash <> :hash
                    AND revoked_at IS NULL',
                [
                    ':id'   => $admin['admin_id'],
                    ':hash' => current_session_hash(),
                ]
            );

            log_activity(
                (int) $admin['admin_id'],
                'PASSWORD_CHANGED',
                'Administrator changed their own password. Other sessions were revoked.'
            );

            set_flash('success', 'Your password has been updated successfully.');
            redirect(url('admin/dashboard.php'));
        }
    }
}

$pageTitle   = 'Change Password';
$currentPage = 'change_password';
require __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8 col-xl-6">
        <div class="card">
            <div class="card-body p-4">

                <h2 class="section-title mb-1">Change your password</h2>
                <p class="text-muted-sm mb-4">
                    Your account is individually identified in activity logs.
                    Choose a strong, unique password you do not use anywhere else.
                </p>

                <?php if (!empty($admin['must_change_password'])): ?>
                    <div class="alert alert-warning">
                        <strong>Password change required.</strong>
                        Your account was provisioned with a temporary password.
                        Please set a new password before continuing.
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $message): ?>
                            <div><?= e($message) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= e(url('admin/change_password.php')) ?>" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current password</label>
                        <div class="password-wrap">
                            <input type="password"
                                   class="form-control"
                                   id="current_password"
                                   name="current_password"
                                   autocomplete="current-password"
                                   required>
                            <button type="button"
                                    class="password-toggle"
                                    data-toggle-password="current_password"
                                    aria-label="Show or hide password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">New password</label>
                        <div class="password-wrap">
                            <input type="password"
                                   class="form-control"
                                   id="new_password"
                                   name="new_password"
                                   autocomplete="new-password"
                                   minlength="<?= e(PASSWORD_MIN_LENGTH) ?>"
                                   required>
                            <button type="button"
                                    class="password-toggle"
                                    data-toggle-password="new_password"
                                    aria-label="Show or hide password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            Minimum <?= e(PASSWORD_MIN_LENGTH) ?> characters, with uppercase,
                            lowercase, number and special character.
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm new password</label>
                        <div class="password-wrap">
                            <input type="password"
                                   class="form-control"
                                   id="confirm_password"
                                   name="confirm_password"
                                   autocomplete="new-password"
                                   required>
                            <button type="button"
                                    class="password-toggle"
                                    data-toggle-password="confirm_password"
                                    aria-label="Show or hide password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2 me-1"></i> Update password
                        </button>
                        <?php if (empty($admin['must_change_password'])): ?>
                            <a href="<?= e(url('admin/dashboard.php')) ?>" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>