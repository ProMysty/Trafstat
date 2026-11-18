<?php
/**
 * violations.php — public violation category information.
 * Shows only categories with is_public = 1 AND a non-empty public_description.
 */
require_once __DIR__ . '/includes/init.php';

$publicCategories = db_all(
    "SELECT violation_name, public_description
       FROM violation_categories
      WHERE is_public = 1 AND status = 'active'
        AND public_description IS NOT NULL AND public_description <> ''
   ORDER BY violation_name ASC"
);

$pageTitle     = 'Violation Information';
$currentPublic = 'violations';
require __DIR__ . '/includes/public_header.php';
?>

<section class="public-hero" style="padding: 2.5rem 0;">
    <div class="public-hero-inner" style="padding-top:0;padding-bottom:0;">
        <span class="public-hero-eyebrow">Published by the office</span>
        <h1 style="font-size: clamp(1.6rem, 3vw, 2.2rem);">
            Traffic violation categories
        </h1>
        <p class="public-hero-lead" style="font-size:1rem;">
            Plain-language descriptions of violation categories the traffic
            management office has approved for public view.
        </p>
    </div>
</section>

<section class="public-section">
    <?php if (empty($publicCategories)): ?>
        <div class="public-empty">
            <i class="bi bi-tags"></i>
            <h3 style="font-size:1rem;font-weight:600;color:var(--traf-charcoal);margin:0.5rem 0;">
                No categories have been published yet
            </h3>
            <p style="margin:0;font-size:0.9rem;">
                The traffic management office has not yet approved any violation
                categories for public view. Please check back later.
            </p>
        </div>
    <?php else: ?>
        <div class="public-category-list">
            <?php foreach ($publicCategories as $c): ?>
                <div class="public-category-card">
                    <h3><?= e($c['violation_name']) ?></h3>
                    <p><?= e($c['public_description']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="public-notice mt-4">
            <i class="bi bi-info-circle me-1"></i>
            This page describes violation categories only. Specific incidents
            and violator information are not published.
            For questions about a specific recorded apprehension, contact the
            traffic management office directly.
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/public_footer.php'; ?>