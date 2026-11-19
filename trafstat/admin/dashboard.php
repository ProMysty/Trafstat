<?php
/**
 * admin/dashboard.php
 * Live operational dashboard with real database statistics and
 * Chart.js visualisations.
 */
require_once __DIR__ . '/../includes/init.php';
$admin = require_admin_ready();

/* ================================================================== */
/* STAT CARDS                                                          */
/* ================================================================== */
$totalViolators     = (int) db_value("SELECT COUNT(*) FROM traffic_violators WHERE record_status = 'active'");
$totalVehicles      = (int) db_value("SELECT COUNT(*) FROM vehicles         WHERE record_status = 'active'");
$totalApprehensions = (int) db_value("SELECT COUNT(*) FROM apprehension_records WHERE record_status = 'active'");
$totalCategories    = (int) db_value("SELECT COUNT(*) FROM violation_categories WHERE status = 'active'");

$repeatViolators = (int) db_value(
    "SELECT COUNT(*) FROM (
        SELECT violator_id
          FROM apprehension_records
         WHERE record_status = 'active'
         GROUP BY violator_id
        HAVING COUNT(*) >= 2
    ) AS repeats"
);

$mostCommon = db_one(
    "SELECT c.violation_name, COUNT(a.apprehension_id) AS total
       FROM violation_categories c
 INNER JOIN apprehension_records a
         ON a.category_id = c.category_id
        AND a.record_status = 'active'
   GROUP BY c.category_id, c.violation_name
   ORDER BY total DESC, c.violation_name ASC
      LIMIT 1"
);
$mostCommonName  = $mostCommon['violation_name'] ?? null;
$mostCommonTotal = $mostCommon ? (int) $mostCommon['total'] : 0;

/* ================================================================== */
/* RECENT APPREHENSIONS                                                */
/* ================================================================== */
$recentApprehensions = db_all(
    "SELECT a.reference_no, a.apprehension_date, a.apprehension_time, a.location_text,
            c.violation_name,
            CONCAT(v.first_name, ' ', v.last_name) AS violator_name
       FROM apprehension_records a
 INNER JOIN traffic_violators v ON v.violator_id = a.violator_id
 INNER JOIN violation_categories c ON c.category_id = a.category_id
      WHERE a.record_status = 'active'
   ORDER BY a.apprehension_date DESC, a.apprehension_id DESC
      LIMIT 5"
);

/* ================================================================== */
/* CHART DATA                                                          */
/* ================================================================== */

/* --- Monthly trend: last 12 months, zero-filled --------------------- */
$monthlyRows = db_all(
    "SELECT DATE_FORMAT(apprehension_date, '%Y-%m') AS month_key, COUNT(*) AS total
       FROM apprehension_records
      WHERE record_status = 'active'
        AND apprehension_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
   GROUP BY month_key"
);
$monthlyByKey = [];
foreach ($monthlyRows as $row) {
    $monthlyByKey[$row['month_key']] = (int) $row['total'];
}

$monthlyLabels = [];
$monthlyValues = [];
$cursor = new DateTime('first day of -11 months');
$end    = new DateTime('first day of this month');
while ($cursor <= $end) {
    $key = $cursor->format('Y-m');
    $monthlyLabels[] = $cursor->format('M Y');
    $monthlyValues[] = $monthlyByKey[$key] ?? 0;
    $cursor->modify('+1 month');
}

/* --- Category distribution ----------------------------------------- */
$categoryRows = db_all(
    "SELECT c.violation_name, COUNT(a.apprehension_id) AS total
       FROM violation_categories c
  LEFT JOIN apprehension_records a
         ON a.category_id = c.category_id
        AND a.record_status = 'active'
   GROUP BY c.category_id, c.violation_name
     HAVING total > 0
   ORDER BY total DESC, c.violation_name ASC"
);
$categoryLabels = array_map(fn($r) => $r['violation_name'], $categoryRows);
$categoryValues = array_map(fn($r) => (int) $r['total'], $categoryRows);

/* --- Yearly trend: last 5 years, zero-filled ----------------------- */
$yearlyRows = db_all(
    "SELECT YEAR(apprehension_date) AS year, COUNT(*) AS total
       FROM apprehension_records
      WHERE record_status = 'active'
        AND apprehension_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-01-01'), INTERVAL 4 YEAR)
   GROUP BY year"
);
$yearlyByYear = [];
foreach ($yearlyRows as $row) {
    $yearlyByYear[(int) $row['year']] = (int) $row['total'];
}

$yearlyLabels = [];
$yearlyValues = [];
$currentYear  = (int) date('Y');
for ($y = $currentYear - 4; $y <= $currentYear; $y++) {
    $yearlyLabels[] = (string) $y;
    $yearlyValues[] = $yearlyByYear[$y] ?? 0;
}

$chartData = [
    'monthly'  => ['labels' => $monthlyLabels, 'values' => $monthlyValues],
    'category' => ['labels' => $categoryLabels, 'values' => $categoryValues],
    'yearly'   => ['labels' => $yearlyLabels, 'values' => $yearlyValues],
];

$pageScripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    url('assets/js/dashboard.js'),
];

$pageTitle   = 'Dashboard';
$currentPage = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h2 class="section-title mb-1">Overview</h2>
    <p class="text-muted-sm mb-0">
        Live figures from the TrafStat database &mdash; refreshes on every page load.
    </p>
</div>

<!-- Stat cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-card-icon"><i class="bi bi-person-vcard"></i></div>
        <div>
            <div class="stat-card-value"><?= number_format($totalViolators) ?></div>
            <div class="stat-card-label">Traffic Violators</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><i class="bi bi-truck"></i></div>
        <div>
            <div class="stat-card-value"><?= number_format($totalVehicles) ?></div>
            <div class="stat-card-label">Registered Vehicles</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><i class="bi bi-journal-text"></i></div>
        <div>
            <div class="stat-card-value"><?= number_format($totalApprehensions) ?></div>
            <div class="stat-card-label">Apprehension Records</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><i class="bi bi-arrow-repeat"></i></div>
        <div>
            <div class="stat-card-value"><?= number_format($repeatViolators) ?></div>
            <div class="stat-card-label">Repeat Recorded Incidents</div>
        </div>
    </div>
        <div class="stat-card">
        <div class="stat-card-icon"><i class="bi bi-person-vcard"></i></div>
        <div>
            <div class="stat-card-value" data-count-target="<?= $totalViolators ?>">
                <?= number_format($totalViolators) ?>
            </div>
            <div class="stat-card-label">Traffic Violators</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><i class="bi bi-truck"></i></div>
        <div>
            <div class="stat-card-value" data-count-target="<?= $totalVehicles ?>">
                <?= number_format($totalVehicles) ?>
            </div>
            <div class="stat-card-label">Registered Vehicles</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><i class="bi bi-journal-text"></i></div>
        <div>
            <div class="stat-card-value" data-count-target="<?= $totalApprehensions ?>">
                <?= number_format($totalApprehensions) ?>
            </div>
            <div class="stat-card-label">Apprehension Records</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><i class="bi bi-arrow-repeat"></i></div>
        <div>
            <div class="stat-card-value" data-count-target="<?= $repeatViolators ?>">
                <?= number_format($repeatViolators) ?>
            </div>
            <div class="stat-card-label">Repeat Recorded Incidents</div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="row g-3 mb-3">
    <div class="col-12 col-lg-8">
        <div class="card chart-card">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-graph-up text-primary"></i>
                    <h3 class="section-title m-0">Monthly Apprehension Trend</h3>
                </div>
                <div class="chart-card-body">
                    <canvas id="chartMonthly" aria-label="Monthly apprehension trend"></canvas>
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
                <div class="chart-card-body">
                    <?php if (empty($categoryLabels)): ?>
                        <div class="chart-empty">No apprehensions recorded yet.</div>
                    <?php else: ?>
                        <canvas id="chartCategories" aria-label="Category distribution"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-5">
        <div class="card chart-card">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-calendar3 text-primary"></i>
                    <h3 class="section-title m-0">Yearly Comparison</h3>
                </div>
                <div class="chart-card-body">
                    <canvas id="chartYearly" aria-label="Yearly comparison"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-3">
        <div class="card chart-card">
            <div class="card-body d-flex flex-column justify-content-center">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-award text-warning"></i>
                    <h3 class="section-title m-0" style="font-size:1rem;">Most Common</h3>
                </div>
                <?php if ($mostCommonName !== null && $mostCommonTotal > 0): ?>
                    <div class="stat-card-value mb-1" style="font-size:1.2rem; line-height:1.2;">
                        <?= e($mostCommonName) ?>
                    </div>
                    <div class="text-muted-sm">
                        <?= number_format($mostCommonTotal) ?>
                        recorded apprehension<?= $mostCommonTotal === 1 ? '' : 's' ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted-sm mb-0">No apprehensions recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card chart-card">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-clock-history text-secondary"></i>
                    <h3 class="section-title m-0">Recent Apprehensions</h3>
                </div>
                <?php if (empty($recentApprehensions)): ?>
                    <p class="text-muted-sm mb-0">No apprehensions recorded yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Violator</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentApprehensions as $row): ?>
                                    <tr>
                                        <td><code><?= e($row['reference_no']) ?></code></td>
                                        <td><?= e($row['violator_name']) ?></td>
                                        <td><?= e(format_date($row['apprehension_date'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
        <div class="col-12 col-lg-8">
        <div class="card chart-card">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-graph-up text-primary"></i>
                    <h3 class="section-title m-0">Monthly Apprehension Trend</h3>
                </div>
                <div class="chart-card-body">
                    <div class="chart-skeleton" aria-hidden="true"></div>
                    <canvas id="chartMonthly" aria-label="Monthly apprehension trend"></canvas>
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
                        <div class="chart-empty">No apprehensions recorded yet.</div>
                    <?php else: ?>
                        <div class="chart-skeleton" aria-hidden="true"></div>
                        <canvas id="chartCategories" aria-label="Category distribution"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart data injection -->
<script nonce="<?= e(csp_nonce()) ?>">
window.DASHBOARD_DATA = <?= json_encode(
    $chartData,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
) ?>;
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>