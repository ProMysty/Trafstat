<?php
/**
 * includes/public_header.php
 * Public website layout: <head>, nav bar, opens <main>.
 *
 * Required variables before include:
 *   $pageTitle      string
 *   $currentPublic  string   'home' | 'statistics' | 'violations' | 'about'
 */
if (!defined('APP_NAME')) {
    exit('Direct access denied.');
}

$pageTitle     = $pageTitle     ?? 'Home';
$currentPublic = $currentPublic ?? 'home';

/* Consume any admin-only flash messages so they don't leak onto the public site. */
take_flashes();

$navItems = [
    'home'       => ['label' => 'Home',            'file' => 'index.php'],
    'statistics' => ['label' => 'Statistics',      'file' => 'statistics.php'],
    'violations' => ['label' => 'Violation Info',  'file' => 'violations.php'],
    'about'      => ['label' => 'About',           'file' => 'about.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="TrafStat — Traffic Violation Records and Statistical Analytics for Pagadian City.">
    <title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>
    
    <script nonce="<?= e(csp_nonce()) ?>">
    (function () {
        try {
            var reduced = window.matchMedia
                && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (!reduced) {
                document.documentElement.classList.add('js-ready');
            }
        } catch (e) {}
    })();
    </script>

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/public.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/public.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/animations.css')) ?>"></body>
</head>
<body class="public-body">

<header class="public-nav">
    <div class="public-nav-inner">
        <a href="<?= e(url('index.php')) ?>" class="public-brand">
            <?= trafstat_mark('public-brand-mark', 40, false) ?>
            <span class="public-brand-text">
                <span class="public-brand-name"><?= e(APP_NAME) ?></span>
                <span class="public-brand-tag">Pagadian City</span>
            </span>
        </a>

        <button type="button"
                class="public-nav-toggle"
                data-public-nav-toggle
                aria-label="Toggle navigation">
            <i class="bi bi-list"></i>
        </button>

        <nav class="public-nav-links" data-public-nav aria-label="Primary">
            <?php foreach ($navItems as $key => $item):
                $isActive = ($currentPublic === $key);
            ?>
                <a href="<?= e(url($item['file'])) ?>"
                   class="public-nav-link<?= $isActive ? ' active' : '' ?>"
                   <?= $isActive ? 'aria-current="page"' : '' ?>>
                    <?= e($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>

<main>