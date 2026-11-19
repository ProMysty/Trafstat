<?php
/**
 * includes/header.php
 * Admin layout: document head, topbar, opens .admin-main and .admin-content.
 *
 * Required variables before include:
 *   $pageTitle   string
 *   $currentPage string  (sidebar active key)
 *   $admin       array   (from require_admin_ready())
 */
if (!defined('APP_NAME')) {
    exit('Direct access denied.');
}

$pageTitle   = $pageTitle   ?? 'Dashboard';
$currentPage = $currentPage ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/images/favicon.svg')) ?>">

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/animations.css')) ?>"></body>
</head>
<body>
<div class="admin-shell">

    <?php require __DIR__ . '/sidebar.php'; ?>

    <div class="sidebar-overlay" data-sidebar-overlay></div>

    <main class="admin-main">

        <header class="admin-topbar">
            <button type="button"
                    class="topbar-toggle"
                    data-sidebar-toggle
                    aria-label="Toggle navigation">
                <i class="bi bi-list"></i>
            </button>

            <h1 class="topbar-title"><?= e($pageTitle) ?></h1>

            <div class="topbar-account dropdown">
                <button type="button"
                        class="topbar-account-btn"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">
                    <span class="topbar-avatar" aria-hidden="true">
                        <?= e(strtoupper(substr($admin['full_name'], 0, 1))) ?>
                    </span>
                    <span class="topbar-account-info">
                        <span class="topbar-account-name"><?= e($admin['full_name']) ?></span>
                        <span class="topbar-account-role">Administrator</span>
                    </span>
                    <i class="bi bi-chevron-down"></i>
                </button>

                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="<?= e(url('admin/change_password.php')) ?>">
                            <i class="bi bi-key me-2"></i> Change password
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="post"
                              action="<?= e(url('admin/logout.php')) ?>"
                              class="dropdown-item-form">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i> Log out
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <div class="admin-content">