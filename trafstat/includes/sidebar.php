<?php
/**
 * includes/sidebar.php
 * Left navigation. Highlights $currentPage; never used for authorization.
 */
if (!defined('APP_NAME')) {
    exit('Direct access denied.');
}

$navGroups = [
    null => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'speedometer2', 'file' => 'admin/dashboard.php'],
    ],
    'Records' => [
        ['key' => 'violators',            'label' => 'Violators',            'icon' => 'person-vcard',  'file' => 'admin/violators.php'],
        ['key' => 'vehicles',             'label' => 'Vehicles',             'icon' => 'truck',         'file' => 'admin/vehicles.php'],
        ['key' => 'apprehensions',        'label' => 'Apprehensions',        'icon' => 'journal-text',  'file' => 'admin/apprehensions.php'],
        ['key' => 'violation_categories', 'label' => 'Violation Categories', 'icon' => 'tags',          'file' => 'admin/violation_categories.php'],
    ],
    'Insights' => [
        ['key' => 'violation_history', 'label' => 'Violation History', 'icon' => 'clock-history', 'file' => 'admin/violation_history.php'],
        ['key' => 'analytics',         'label' => 'Analytics',         'icon' => 'bar-chart',     'file' => 'admin/analytics.php'],
        ['key' => 'hotspot_map',       'label' => 'Hotspot Map',       'icon' => 'geo-alt',       'file' => 'admin/hotspot_map.php'],
    ],
    'Administration' => [
        ['key' => 'reports',       'label' => 'Reports',        'icon' => 'file-earmark-text', 'file' => 'admin/reports.php'],
        ['key' => 'activity_logs', 'label' => 'Activity Logs',  'icon' => 'shield-lock',       'file' => 'admin/activity_logs.php'],
    ],
];
?>
<aside class="admin-sidebar" data-sidebar>
    <div class="sidebar-brand">
        <span class="sidebar-brand-mark">T</span>
        <div class="sidebar-brand-text">
            <span class="sidebar-brand-name"><?= e(APP_NAME) ?></span>
            <span class="sidebar-brand-sub">Administration</span>
        </div>
    </div>

    <nav class="sidebar-nav" aria-label="Primary">
        <?php foreach ($navGroups as $groupLabel => $items): ?>
            <?php if ($groupLabel !== null): ?>
                <div class="sidebar-group-label"><?= e($groupLabel) ?></div>
            <?php endif; ?>
            <ul class="sidebar-list">
                <?php foreach ($items as $item):
                    $isActive = ($currentPage === $item['key']); ?>
                    <li>
                        <a href="<?= e(url($item['file'])) ?>"
                           class="sidebar-link<?= $isActive ? ' is-active' : '' ?>"
                           <?= $isActive ? 'aria-current="page"' : '' ?>>
                            <i class="bi bi-<?= e($item['icon']) ?>"></i>
                            <span><?= e($item['label']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <small>
            <?= e(APP_NAME) ?> &middot; <?= e(date('Y')) ?><br>
            Pagadian City
        </small>
    </div>
</aside>