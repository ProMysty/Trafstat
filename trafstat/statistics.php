<?php
/**
 * statistics.php — public aggregated statistics.
 *
 * Suppression rules:
 *   - Any bucket (month / category / year) with fewer than
 *     PUBLIC_SUPPRESSION_THRESHOLD records is treated as below-threshold.
 *   - For monthly and yearly charts, below-threshold buckets are set to null
 *     so Chart.js renders a gap (not a zero) — visually signalling "not shown".
 *   - For category breakdowns, below-threshold categories are merged into
 *     a single "Other (below threshold)" bucket whose own count is shown only
 *     if it too passes the threshold.
 *   - Total active apprehensions and total active violators are aggregated
 *     counts and are shown exactly (no small-group inference possible).
 */
require_once __DIR__ . '/includes/init.php';

$threshold = (int) PUBLIC_SUPPRESSION_THRESHOLD;

/* Years with active data, for the year selector. */
$availableYears = db_all(
    "SELECT DISTINCT YEAR(apprehension_date) AS y
       FROM apprehension_records
      WHERE record_status = 'active'
   ORDER BY y DESC"
);
$availableYearList = array_map(fn($r) => (int) $r['y'], $availableYears);

$selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$showAllYears = ($selectedYear === 0);
if (!$showAllYears && !in_array($selectedYear, $availableYearList, true)) {
    $selectedYear = (int) date('Y');
}

/* Aggregate totals (safe to publish exactly). */
$totalActiveApprehensions = (int) db_value(
    "SELECT COUNT(*) FROM apprehension_records WHERE record_status = 'active'"
);
$totalActiveViolators = (int) db_value(
    "SELECT COUNT(*) FROM traffic_violators WHERE record_status = 'active'"
);
$rangeApprehensions = (int) db_value(
    "SELECT COUNT(*) FROM apprehension_records
      WHERE record_status = 'active'" . ($showAllYears ? '' : ' AND YEAR(apprehension_date) = :y'),
    $showAllYears ? [] : [':y' => $selectedYear]
);

/* ---------------------------------------------------------------- */
/* Monthly — only for a specific year                               */
/* ---------------------------------------------------------------- */
$monthlyLabels = [];
$monthlyValues = [];

if (!$showAllYears) {
    $monthlyRows = db_all(
        "SELECT MONTH(apprehension_date) AS m, COUNT(*) AS total
           FROM apprehension_records
          WHERE record_status = 'active' AND YEAR(apprehension_date) = :y
       GROUP BY m",
        [':y' => $selectedYear]
    );
    $byMonth = [];
    foreach ($monthlyRows as $r) {
        $byMonth[(int) $r['m']] = (int) $r['total'];
    }
    $monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    for ($m = 1; $m <= 12; $m++) {
        $count = $byMonth[$m] ?? 0;
        $monthlyLabels[] = $monthNames[$m - 1];
        $monthlyValues[] = $count >= $threshold ? $count : null;
    }
}

/* ---------------------------------------------------------------- */
/* Categories                                                       */
/* ---------------------------------------------------------------- */
$categoryRows = db_all(
    "SELECT vc.violation_name, COUNT(a.apprehension_id) AS total
       FROM violation_categories vc
 INNER JOIN apprehension_records a
         ON a.category_id = vc.category_id
        AND a.record_status = 'active'
        " . ($showAllYears ? '' : 'AND YEAR(a.apprehension_date) = :y') . "
   GROUP BY vc.category_id, vc.violation_name
   ORDER BY total DESC, vc.violation_name ASC",
    $showAllYears ? [] : [':y' => $selectedYear]
);

$categoryLabels = [];
$categoryValues = [];
$belowThreshold = 0;
$belowThresholdCount = 0;

foreach ($categoryRows as $r) {
    $count = (int) $r['total'];
    if ($count >= $threshold) {
        $categoryLabels[] = $r['violation_name'];
        $categoryValues[] = $count;
    } else {
        $belowThreshold += $count;
        $belowThresholdCount++;
    }
}

/* If there are multiple below-threshold categories, or even one large enough
   to itself cross the threshold when summed, surface them as "Other". */
if ($belowThreshold >= $threshold) {
    $categoryLabels[] = 'Other (aggregated)';
    $categoryValues[] = $belowThreshold;
}

/* ---------------------------------------------------------------- */
/* Yearly                                                           */
/* ---------------------------------------------------------------- */
$yearlyRows = db_all(
    "SELECT YEAR(apprehension_date) AS y, COUNT(*) AS total
       FROM apprehension_records
      WHERE record_status = 'active'
   GROUP BY y
   ORDER BY y ASC"
);
$yearlyLabels = [];
$yearlyValues = [];
foreach ($yearlyRows as $r) {
    $count = (int) $r['total'];
    $yearlyLabels[] = (string) $r['y'];
    $yearlyValues[] = $count >= $threshold ? $count : null;
}

/* ---------------------------------------------------------------- */
/* Most common category (only from visible ones)                    */
/* ---------------------------------------------------------------- */
$mostCommonCategory = null;
$highest = 0;
foreach ($categoryRows as $r) {
    $c = (int) $r['total'];
    if ($c >= $threshold && $c > $highest) {
        $highest = $c;
        $mostCommonCategory = $r['violation_name'];
    }
}

$publicStats = [
    'monthly'  => ['labels' => $monthlyLabels, 'values' => $monthlyValues],
    'category' => ['labels' => $categoryLabels, 'values' => $categoryValues],
    'yearly'   => ['labels' => $yearlyLabels,  'values' => $yearlyValues],
];

$pageScripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    url('assets/js/public_stats.js'),
];

$pageTitle     = 'Statistics';
$currentPublic = 'statistics';
require __DIR__ . '/includes/public_header.php';
?>

<section class="public-hero" style="padding: 2.5rem 0;">
    <div class="public-hero-inner" style="padding-top:0;padding-bottom:0;">
        <span class="public-hero-eyebrow">Approved for public disclosure</span>
        <h1 style="font-size: clamp(1.6rem, 3vw, 2.2rem);">
            Traffic statistics
        </h1>
        <p class="public-hero-lead" style="font-size:1rem;">
            Aggregated figures approved by the traffic management office.
            Small statistical groups are suppressed per the office's privacy rules.
        </p>
    </div>
</section>

<section class="public-section">

    <!-- Year selector -->
    <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
        <label class="fw-semibold">Year:</label>
        <?php if (!empty($availableYearList)): ?>
            <a href="?year=<?= (int) date('Y') ?>"
               class="btn btn-sm <?= $selectedYear === (int) date('Y') && !$showAllYears ? 'btn-primary' : 'btn-outline-primary' ?>">
                <?= e((string) date('Y')) ?>
            </a>
            <?php foreach ($availableYearList as $y):
                if ($y === (int) date('Y')) { continue; }
            ?>
                <a href="?year=<?= $y ?>"
                   class="btn btn-sm <?= $selectedYear === $y ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <?= e((string) $y) ?>
                </a>
            <?php endforeach; ?>
            <a href="?year=0"
               class="btn btn-sm <?= $showAllYears ? 'btn-primary' : 'btn-outline-primary' ?>">
                All years
            </a>
        <?php endif; ?>
    </div>

    <div class="public-notice">
        <i class="bi bi-shield-lock me-1"></i>
        <strong>About suppression:</strong> buckets with fewer than
        <?= (int) $threshold ?> records are not shown. This prevents re-identification
        through very small statistical groups. The threshold is set by the office's
        Data Protection Officer.
    </div>

    <!-- Top summary cards -->
    <div class="public-stat-grid mb-4">
        <div class="public-stat-card">
            <div class="public-stat-value">
                <?= number_format($rangeApprehensions) ?>
            </div>
            <div class="public-stat-label">
                <?= $showAllYears ? 'Total recorded apprehensions' : 'Recorded in ' . (int) $selectedYear ?>
            </div>
        </div>
        <div class="public-stat-card">
            <div class="public-stat-value"><?= number_format($totalActiveViolators) ?></div>
            <div class="public-stat-label">Active violators (all years)</div>
        </div>
        <div class="public-stat-card">
            <div class="public-stat-value"><?= number_format($totalActiveApprehensions) ?></div>
            <div class="public-stat-label">Total apprehensions (all years)</div>
        </div>
        <?php if ($mostCommonCategory !== null): ?>
            <div class="public-stat-card">
                <div class="public-stat-value" style="font-size:1rem;line-height:1.3;">
                    <?= e($mostCommonCategory) ?>
                </div>
                <div class="public-stat-label">Most recorded category</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Charts -->
    <?php if (!$showAllYears): ?>
        <div class="public-chart-card">
            <h3>Monthly trend &mdash; <?= (int) $selectedYear ?></h3>
            <?php if (array_sum(array_map(fn($v) => $v === null ? 0 : $v, $monthlyValues)) === 0): ?>
                <div class="public-empty">
                    <i class="bi bi-graph-up"></i>
                    Insufficient data to display this chart for <?= (int) $selectedYear ?>.
                </div>
            <?php else: ?>
                <div class="public-chart-container">
                    <canvas id="publicMonthly" aria-label="Monthly apprehension trend"></canvas>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-6">
            <div class="public-chart-card h-100">
                <h3>Category distribution</h3>
                <?php if (empty($categoryLabels)): ?>
                    <div class="public-empty">
                        <i class="bi bi-pie-chart"></i>
                        No category has reached the reporting threshold in this range.
                    </div>
                <?php else: ?>
                    <div class="public-chart-container">
                        <canvas id="publicCategories" aria-label="Category distribution"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="public-chart-card h-100">
                <h3>Yearly comparison</h3>
                <?php if (empty($yearlyLabels)): ?>
                    <div class="public-empty">
                        <i class="bi bi-bar-chart"></i>
                        No yearly data to display.
                    </div>
                <?php else: ?>
                    <div class="public-chart-container">
                        <canvas id="publicYearly" aria-label="Yearly comparison"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="public-notice">
        <i class="bi bi-info-circle me-1"></i>
        Figures reflect <strong>recorded apprehensions</strong> only. A recorded
        apprehension is not a legal finding of guilt. Figures may change as records
        are validated or updated by the office.
    </div>

</section>

<script nonce="<?= e(csp_nonce()) ?>">
window.PUBLIC_STATS = <?= json_encode(
    $publicStats,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
) ?>;
</script>

<?php require __DIR__ . '/includes/public_footer.php'; ?>