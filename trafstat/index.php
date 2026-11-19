<?php
/**
 * index.php — public homepage.
 */
require_once __DIR__ . '/includes/init.php';

/* Aggregate, non-identifying counters. */
$totalActiveApprehensions = (int) db_value(
    "SELECT COUNT(*) FROM apprehension_records WHERE record_status = 'active'"
);
$totalActiveViolators = (int) db_value(
    "SELECT COUNT(*) FROM traffic_violators WHERE record_status = 'active'"
);
$totalActiveCategories = (int) db_value(
    "SELECT COUNT(*) FROM violation_categories
      WHERE status = 'active' AND is_public = 1
        AND public_description IS NOT NULL AND public_description <> ''"
);
$currentYear = (int) date('Y');
$thisYearApprehensions = (int) db_value(
    "SELECT COUNT(*) FROM apprehension_records
      WHERE record_status = 'active' AND YEAR(apprehension_date) = ?",
    [$currentYear]
);

/* Recent public categories (up to 4). */
$publicCategories = db_all(
    "SELECT violation_name, public_description
       FROM violation_categories
      WHERE is_public = 1 AND status = 'active'
        AND public_description IS NOT NULL AND public_description <> ''
   ORDER BY violation_name ASC
      LIMIT 4"
);

$pageTitle     = 'Home';
$currentPublic = 'home';
require __DIR__ . '/includes/public_header.php';
?>

<section class="public-hero">
    <div class="public-hero-inner">
        <span class="public-hero-eyebrow">Pagadian City &middot; Traffic Management</span>
        <h1>
            Traffic violation records,<br>
            <span>clearly and responsibly.</span>
        </h1>
        <p class="public-hero-lead">
            <?= e(APP_NAME) ?> is the official records and analytics system of
            the Pagadian City traffic management office. The public side shows
            approved aggregated statistics — never personal information.
        </p>
        <div class="public-hero-actions">
            <a href="<?= e(url('statistics.php')) ?>" class="btn btn-primary">
                <i class="bi bi-bar-chart me-1"></i> View public statistics
            </a>
            <a href="<?= e(url('violations.php')) ?>" class="btn btn-outline-secondary">
                <i class="bi bi-tags me-1"></i> Browse violation types
            </a>
        </div>
    </div>
</section>

<section class="public-section">
    <div class="public-section-header" data-reveal>
        <div class="public-section-eyebrow">Approved aggregates</div>
        <h2>Traffic figures at a glance</h2>
        <p class="public-section-lead">
            Every figure below is aggregated across active records and
            published under the office's privacy rules. Small groups are
            suppressed to protect individual privacy.
        </p>
    </div>

    <div class="public-stat-grid" data-reveal-stagger>
        <div class="public-stat-card" data-reveal>
            <div class="public-stat-value"><?= number_format($totalActiveApprehensions) ?></div>
            <div class="public-stat-label">Total recorded apprehensions</div>
        </div>
        <div class="public-stat-card" data-reveal>
            <div class="public-stat-value"><?= number_format($totalActiveViolators) ?></div>
            <div class="public-stat-label">Active violators on record</div>
        </div>
        <div class="public-stat-card" data-reveal>
            <div class="public-stat-value"><?= number_format($thisYearApprehensions) ?></div>
            <div class="public-stat-label">Recorded in <?= e((string) $currentYear) ?></div>
        </div>
        <div class="public-stat-card" data-reveal>
            <div class="public-stat-value"><?= number_format($totalActiveCategories) ?></div>
            <div class="public-stat-label">Published violation categories</div>
        </div>
    </div>
</section>

<section class="public-section" style="background:var(--traf-gray-bg);max-width:none;">
    <div style="max-width:var(--pub-max-width);margin:0 auto;padding:0 0;">
        <div class="public-section-header" data-reveal>
            <div class="public-section-eyebrow">What TrafStat provides</div>
            <h2>Explore the system</h2>
        </div>

        <div class="public-card-grid" data-reveal-stagger>
            <a href="<?= e(url('statistics.php')) ?>" class="public-feature-card" data-reveal>
                <div class="public-feature-icon"><i class="bi bi-graph-up-arrow"></i></div>
                <h3>Public statistics</h3>
                <p>Monthly trends, category distribution, yearly comparisons.
                   Aggregated figures only — no individual data.</p>
            </a>
            <a href="<?= e(url('violations.php')) ?>" class="public-feature-card" data-reveal>
                <div class="public-feature-icon"><i class="bi bi-tags"></i></div>
                <h3>Violation information</h3>
                <p>Plain-language descriptions of traffic violation categories
                   published by the office.</p>
            </a>
            <a href="<?= e(url('about.php')) ?>" class="public-feature-card" data-reveal>
                <div class="public-feature-icon"><i class="bi bi-info-circle"></i></div>
                <h3>About TrafStat</h3>
                <p>Purpose, objectives, coverage, and privacy notice for the
                   Pagadian City traffic records system.</p>
            </a>
        </div>
    </div>
</section>

<?php if (!empty($publicCategories)): ?>
<section class="public-section">
    <div class="public-section-header" data-reveal>
        <div class="public-section-eyebrow">Approved by the office</div>
        <h2>Recently published violation types</h2>
        <p class="public-section-lead">
            These descriptions have been reviewed and approved for public view
            by the traffic management office.
        </p>
    </div>

    <div class="public-category-list" data-reveal-stagger>
        <?php foreach ($publicCategories as $c): ?>
            <div class="public-category-card" data-reveal>
                <h3><?= e($c['violation_name']) ?></h3>
                <p><?= e($c['public_description']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="text-center mt-4" data-reveal>
        <a href="<?= e(url('violations.php')) ?>" class="btn btn-outline-primary">
            See all published categories
        </a>
    </div>
</section>
<?php endif; ?>

<section class="public-section" style="padding-top:0;">
    <div class="public-notice" data-reveal>
        <i class="bi bi-shield-check me-1"></i>
        <strong>Privacy:</strong> this public website never displays individual
        violator names, addresses, contact numbers, plate numbers, apprehension
        histories, or incident-level coordinates. Statistical figures are
        aggregated and small groups are suppressed per the office's Data
        Protection Officer guidance.
    </div>
</section>

<?php require __DIR__ . '/includes/public_footer.php'; ?>