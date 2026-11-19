<?php
/**
 * admin/analytics.php
 * Statistical analytics with filters.
 *
 * Filters: date_from, date_to, category_id.
 * All figures derive from `apprehension_records WHERE record_status='active'`.
 */
require_once __DIR__ . '/../includes/init.php';
$admin = require_admin_ready();

/* ================================================================== */
/* FILTERS                                                             */
/* ================================================================== */
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to']   ?? '');
$categoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;

if ($dateFrom === '' || !is_valid_date($dateFrom)) {
    $dateFrom = date('Y-m-d', strtotime('-11 months', strtotime(date('Y-m-01'))));
}
if ($dateTo === '' || !is_valid_date($dateTo)) {
    $dateTo = date('Y-m-d');
}
if ($dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

$where  = ["a.record_status = 'active'", 'a.apprehension_date BETWEEN :date_from AND :date_to'];
$params = [':date_from' => $dateFrom, ':date_to' => $dateTo];

if ($categoryId > 0) {
    $where[] = 'a.category_id = :category_id';
    $params[':category_id'] = $categoryId;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$categoriesForFilter = db_all(
    'SELECT category_id, violation_name FROM violation_categories ORDER BY violation_name'
);

/* ================================================================== */
/* SUMMARY CARDS                                                       */
/* ================================================================== */
$totalApprehensions = (int) db_value(
    "SELECT COUNT(*) FROM apprehension_records a $whereSql", $params
);

$distinctViolators = (int) db_value(
    "SELECT COUNT(DISTINCT a.violator_id) FROM apprehension_records a $whereSql", $params
);

$mostCommon = db_one(
    "SELECT c.violation_name, COUNT(a.apprehension_id) AS total
       FROM violation_categories c
 INNER JOIN apprehension_records a
         ON a.category_id = c.category_id
        AND a.record_status = 'active'
        AND a.apprehension_date BETWEEN :date_from AND :date_to
        " . ($categoryId > 0 ? 'AND a.category_id = :category_id' : '') . "
   GROUP BY c.category_id, c.violation_name
   ORDER BY total DESC, c.violation_name ASC
      LIMIT 1",
    $categoryId > 0
        ? [':date_from' => $dateFrom, ':date_to' => $dateTo, ':category_id' => $categoryId]
        : [':date_from' => $dateFrom, ':date_to' => $dateTo]
);

$leastCommon = db_one(
    "SELECT c.violation_name, COUNT(a.apprehension_id) AS total
       FROM violation_categories c
 INNER JOIN apprehension_records a
         ON a.category_id = c.category_id
        AND a.record_status = 'active'
        AND a.apprehension_date BETWEEN :date_from AND :date_to
        " . ($categoryId > 0 ? 'AND a.category_id = :category_id' : '') . "
   GROUP BY c.category_id, c.violation_name
   ORDER BY total ASC, c.violation_name ASC
      LIMIT 1",
    $categoryId > 0
        ? [':date_from' => $dateFrom, ':date_to' => $dateTo, ':category_id' => $categoryId]
        : [':date_from' => $dateFrom, ':date_to' => $dateTo]
);

/* --- Average per month in range ------------------------------------ */
$startDate  = new DateTime($dateFrom);
$endDate    = new DateTime($dateTo);
$daysSpan   = max(1, (int) $startDate->diff($endDate)->days + 1);
$monthsSpan = max(1, round($daysSpan / 30.44, 1));
$avgPerMonth = $monthsSpan > 0 ? round($totalApprehensions / $monthsSpan, 1) : 0;

/* --- Year-over-year (calendar, independent of range) -------------- */
$currentYear = (int) date('Y');
$prevYear    = $currentYear - 1;
$currentYearTotal = (int) db_value(
    "SELECT COUNT(*) FROM apprehension_records
      WHERE record_status = 'active' AND YEAR(apprehension_date) = ?",
    [$currentYear]
);
$prevYearTotal = (int) db_value(
    "SELECT COUNT(*) FROM apprehension_records
      WHERE record_status = 'active' AND YEAR(apprehension_date) = ?",
    [$prevYear]
);
$yoyChangePct = $prevYearTotal > 0
    ? round((($currentYearTotal - $prevYearTotal) / $prevYearTotal) * 100, 1)
    : null;

/* ================================================================== */
/* CHART DATA                                                          */
/* ================================================================== */

/* --- Category distribution ----------------------------------------- */
$categoryRows = db_all(
    "SELECT c.violation_name, COUNT(a.apprehension_id) AS total
       FROM violation_categories c
 INNER JOIN apprehension_records a
         ON a.category_id = c.category_id
        AND a.record_status = 'active'
        AND a.apprehension_date BETWEEN :date_from AND :date_to
        " . ($categoryId > 0 ? 'AND a.category_id = :category_id' : '') . "
   GROUP BY c.category_id, c.violation_name
   ORDER BY total DESC, c.violation_name ASC",
    $categoryId > 0
        ? [':date_from' => $dateFrom, ':date_to' => $dateTo, ':category_id' => $categoryId]
        : [':date_from' => $dateFrom, ':date_to' => $dateTo]
);
$categoryLabels = array_map(fn($r) => $r['violation_name'], $categoryRows);
$categoryValues = array_map(fn($r) => (int) $r['total'], $categoryRows);

/* --- Monthly trend (zero-filled across the filter range) ---------- */
$monthlyRows = db_all(
    "SELECT DATE_FORMAT(a.apprehension_date, '%Y-%m') AS month_key, COUNT(*) AS total
       FROM apprehension_records a
       $whereSql
   GROUP BY month_key",
    $params
);
$monthlyByKey = [];
foreach ($monthlyRows as $r) {
    $monthlyByKey[$r['month_key']] = (int) $r['total'];
}

$monthlyLabels = [];
$monthlyValues = [];
$cursor = new DateTime($dateFrom);
$cursor->modify('first day of this month');
$endBucket = new DateTime($dateTo);
$endBucket->modify('first day of this month');
while ($cursor <= $endBucket) {
    $key = $cursor->format('Y-m');
    $monthlyLabels[] = $cursor->format('M Y');
    $monthlyValues[] = $monthlyByKey[$key] ?? 0;
    $cursor->modify('+1 month');
}

/* --- Yearly trend (within filter range) --------------------------- */
$yearlyRows = db_all(
    "SELECT YEAR(a.apprehension_date) AS year, COUNT(*) AS total
       FROM apprehension_records a
       $whereSql
   GROUP BY year
   ORDER BY year ASC",
    $params
);
$yearlyLabels = array_map(fn($r) => (string) $r['year'], $yearlyRows);
$yearlyValues = array_map(fn($r) => (int) $r['total'], $yearlyRows);

/* --- Top locations ------------------------------------------------- */
$locationRows = db_all(
    "SELECT a.location_text, COUNT(*) AS total
       FROM apprehension_records a
       $whereSql
   GROUP BY a.location_text
   ORDER BY total DESC, a.location_text ASC
      LIMIT 10",
    $params
);
$locationLabels = array_map(fn($r) => $r['location_text'], $locationRows);
$locationValues = array_map(fn($r) => (int) $r['total'], $locationRows);

$chartData = [
    'category' => ['labels' => $categoryLabels, 'values' => $categoryValues],
    'monthly'  => ['labels' => $monthlyLabels,  'values' => $monthlyValues],
    'yearly'   => ['labels' => $yearlyLabels,   'values' => $yearlyValues],
    'location' => ['labels' => $locationLabels, 'values' => $locationValues],
];

$pageScripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    url('assets/js/analytics.js'),
];

$pageTitle   = 'Analytics';
$currentPage = 'analytics';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h2 class="section-title mb-1">Statistical Analytics</h2>
        <p class="text-muted-sm mb-0">
            <?= e(format_date($dateFrom)) ?> &mdash; <?= e(format_date($dateTo)) ?>
            <?= $categoryId > 0 ? ' · filtered by category' : '' ?>
        </p>
    </div>
</div>

<!-- Filters -->
<form method="get" class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label">From</label>
                <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">To</label>
                <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Category</label>
                <select name="category_id" class="form-select">
                    <option value="">All categories</option>
                    <?php foreach ($categoriesForFilter as $c): ?>
                        <option value="<?= (int) $c['category_id'] ?>"
                            <?= $categoryId === (int) $c['category_id'] ? 'selected' : '' ?>>
                            <?= e($c['violation_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">
                    <i class="bi bi-funnel me-1"></i> Apply
                </button>
                <a href="<?= e(url('admin/analytics.php')) ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </div>
</form>

<!-- Summary cards (counters animated for integer values) -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-card-icon"><i class="bi bi-journal-check"></i></div>
        <div>
            <div class="stat-card-value" data-count-target="<?= $totalApprehensions ?>">
                <?= number_format($totalApprehensions) ?>
            </div>
            <div class="stat-card-label">Apprehensions in range</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><i class="bi bi-people"></i></div>
        <div>
            <div class="stat-card-value" data-count-target="<?= $distinctViolators ?>">
                <?= number_format($distinctViolators) ?>
            </div>
            <div class="stat-card-label">Distinct violators</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><i class="bi bi-graph-up-arrow"></i></div>
        <div>
            <!-- Decimal value — no counter, shows immediately -->
            <div class="stat-card-value"><?= number_format($avgPerMonth, 1) ?></div>
            <div class="stat-card-label">Average per month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><i class="bi bi-award"></i></div>
        <div>
            <div class="stat-card-value" style="font-size:1rem; line-height:1.3;">
                <?= $mostCommon ? e($mostCommon['violation_name']) : '—' ?>
            </div>
            <div class="stat-card-label">
                Most common
                <?= $mostCommon ? ' · ' . number_format((int) $mostCommon['total']) : '' ?>
            </div>
        </div>
    </div>
</div>

<!-- Least common + YoY -->
<div class="row g-3 mb-3">
    <div class="col-12 col-md-6">
        <div class="card">
            <div class="card-body p-4">
                <div class="text-muted-sm mb-1">Least common in range</div>
                <?php if ($leastCommon !== null): ?>
                    <div class="stat-card-value" style="font-size:1.1rem; line-height:1.3;">
                        <?= e($leastCommon['violation_name']) ?>
                    </div>
                    <div class="text-muted-sm">
                        <?= number_format((int) $leastCommon['total']) ?>
                        recorded apprehension<?= (int) $leastCommon['total'] === 1 ? '' : 's' ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted-sm mb-0">No data in range.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card">
            <div class="card-body p-4">
                <div class="text-muted-sm mb-1">
                    <?= $currentYear ?> vs <?= $prevYear ?> (calendar years, unfiltered)
                </div>
                <div class="stat-card-value" style="font-size:1.1rem; line-height:1.3;">
                    <?= number_format($currentYearTotal) ?> this year
                    &middot; <?= number_format($prevYearTotal) ?> last year
                </div>
                <?php if ($yoyChangePct === null): ?>
                    <div class="text-muted-sm">No data from <?= $prevYear ?> to compare.</div>
                <?php else: ?>
                    <div class="text-muted-sm">
                        <i class="bi bi-<?= $yoyChangePct >= 0 ? 'arrow-up-right' : 'arrow-down-right' ?>"></i>
                        <?= $yoyChangePct >= 0 ? '+' : '' ?><?= e($yoyChangePct) ?>%
                        change
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Charts row 1 -->
<div class="row g-3 mb-3">
    <div class="col-12 col-lg-8">
        <div class="card chart-card">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-graph-up text-primary"></i>
                    <h3 class="section-title m-0">Monthly Trend</h3>
                </div>
                <div class="chart-card-body">
                    <?php if (array_sum($monthlyValues) === 0): ?>
                        <div class="chart-empty">No apprehended records in this range.</div>
                    <?php else: ?>
                        <div class="chart-skeleton" aria-hidden="true"></div>
                        <canvas id="analyticsMonthly" aria-label="Monthly trend"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card chart-card">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-pie-chart text-primary"></i>
                    <h3 class="section-title m-0">Category Distribution</h3>
                </div>
                <div class="chart-card-body chart-card-body--category">
                    <?php if (empty($categoryLabels)): ?>
                        <div class="chart-empty">No data in range.</div>
                    <?php else: ?>
                        <div class="chart-skeleton" aria-hidden="true"></div>
                        <canvas id="analyticsCategories" aria-label="Category distribution"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts row 2 -->
<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card chart-card">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-bar-chart text-primary"></i>
                    <h3 class="section-title m-0">Yearly Comparison</h3>
                </div>
                <div class="chart-card-body">
                    <?php if (empty($yearlyLabels)): ?>
                        <div class="chart-empty">No data in range.</div>
                    <?php else: ?>
                        <div class="chart-skeleton" aria-hidden="true"></div>
                        <canvas id="analyticsYearly" aria-label="Yearly comparison"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card chart-card">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-geo-alt text-primary"></i>
                    <h3 class="section-title m-0">Top 10 Locations</h3>
                </div>
                <div class="chart-card-body">
                    <?php if (empty($locationLabels)): ?>
                        <div class="chart-empty">No data in range.</div>
                    <?php else: ?>
                        <div class="chart-skeleton" aria-hidden="true"></div>
                        <canvas id="analyticsLocations" aria-label="Top locations"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= e(csp_nonce()) ?>">
window.ANALYTICS_DATA = <?= json_encode(
    $chartData,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
) ?>;
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>