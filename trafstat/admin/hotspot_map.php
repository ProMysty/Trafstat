<?php
/**
 * admin/hotspot_map.php
 * Leaflet.js hotspot map with marker clustering.
 *
 * Only apprehensions with BOTH latitude and longitude are plotted.
 * Coordinates are never invented — if the location text has no coordinates,
 * the record simply doesn't appear on the map. The summary panel tells the
 * admin how many records are missing coordinates.
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
    $dateFrom = date('Y-m-d', strtotime('-90 days'));
}
if ($dateTo === '' || !is_valid_date($dateTo)) {
    $dateTo = date('Y-m-d');
}
if ($dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

/* ================================================================== */
/* DATA                                                                */
/* ================================================================== */
$where  = ["a.record_status = 'active'",
           'a.apprehension_date BETWEEN :date_from AND :date_to'];
$params = [':date_from' => $dateFrom, ':date_to' => $dateTo];

if ($categoryId > 0) {
    $where[] = 'a.category_id = :category_id';
    $params[':category_id'] = $categoryId;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$totalMatching = (int) db_value(
    "SELECT COUNT(*) FROM apprehension_records a $whereSql", $params
);

$plotted = db_all(
    "SELECT a.apprehension_id, a.reference_no, a.apprehension_date, a.apprehension_time,
            a.location_text, a.latitude, a.longitude,
            tv.violator_code,
            vc.violation_name,
            veh.plate_number
       FROM apprehension_records a
 INNER JOIN traffic_violators tv   ON tv.violator_id  = a.violator_id
 INNER JOIN violation_categories vc ON vc.category_id = a.category_id
  LEFT JOIN vehicles veh ON veh.vehicle_id = a.vehicle_id
       $whereSql
         AND a.latitude  IS NOT NULL
         AND a.longitude IS NOT NULL
   ORDER BY a.apprehension_date DESC",
    $params
);

$missingCoords = $totalMatching - count($plotted);

/* Build JSON-safe marker payload for the JS layer. */
$markers = [];
foreach ($plotted as $r) {
    $markers[] = [
        'lat'      => (float) $r['latitude'],
        'lng'      => (float) $r['longitude'],
        'ref'      => $r['reference_no'],
        'date'     => $r['apprehension_date'],
        'time'     => $r['apprehension_time'] ?? '',
        'location' => $r['location_text'],
        'category' => $r['violation_name'],
        'violator' => $r['violator_code'],
        'plate'    => $r['plate_number'] ?? '',
        'edit_url' => url('admin/apprehensions.php?action=edit&id=' . (int) $r['apprehension_id']),
    ];
}

/* Aggregated count per location for the summary table. */
$locationTotals = db_all(
    "SELECT a.location_text, COUNT(*) AS total,
            SUM(CASE WHEN a.latitude IS NOT NULL AND a.longitude IS NOT NULL THEN 1 ELSE 0 END) AS mapped
       FROM apprehension_records a
       $whereSql
   GROUP BY a.location_text
   ORDER BY total DESC, a.location_text ASC
      LIMIT 15",
    $params
);

$categoriesForFilter = db_all(
    'SELECT category_id, violation_name FROM violation_categories ORDER BY violation_name'
);

$pageScripts = [
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js',
    url('assets/js/hotspot_map.js'),
];

$pageTitle   = 'Hotspot Map';
$currentPage = 'hotspot_map';
require __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet"
      href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet"
      href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet"
      href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h2 class="section-title mb-1">Traffic Violation Hotspot Map</h2>
        <p class="text-muted-sm mb-0">
            <?= e(format_date($dateFrom)) ?> &mdash; <?= e(format_date($dateTo)) ?>
            <?= $categoryId > 0 ? ' · filtered by category' : '' ?>
        </p>
    </div>
</div>

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
                <a href="<?= e(url('admin/hotspot_map.php')) ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </div>
</form>

<div class="stat-grid mb-3">
    <div class="stat-card">
        <div class="stat-card-icon"><i class="bi bi-pin-map"></i></div>
        <div>
            <div class="stat-card-value"><?= number_format(count($plotted)) ?></div>
            <div class="stat-card-label">Plotted markers</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><i class="bi bi-journal-text"></i></div>
        <div>
            <div class="stat-card-value"><?= number_format($totalMatching) ?></div>
            <div class="stat-card-label">Total in range</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><i class="bi bi-question-circle"></i></div>
        <div>
            <div class="stat-card-value"><?= number_format($missingCoords) ?></div>
            <div class="stat-card-label">Missing coordinates</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><i class="bi bi-geo"></i></div>
        <div>
            <div class="stat-card-value"><?= number_format(count($locationTotals)) ?></div>
            <div class="stat-card-label">Distinct locations</div>
        </div>
    </div>
</div>

<?php if ($missingCoords > 0 && count($plotted) > 0): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-1"></i>
        <?= number_format($missingCoords) ?> record<?= $missingCoords === 1 ? '' : 's' ?>
        in this range have no coordinates and are not plotted. Coordinates are
        never invented from location text — open a record and add them from
        the apprehension form if precision is required.
    </div>
<?php endif; ?>

<?php if (count($plotted) === 0): ?>
    <div class="card">
        <div class="card-body p-5 text-center">
            <i class="bi bi-pin-map fs-1 text-muted mb-2 d-block"></i>
            <h3 class="section-title mb-1">No mappable records</h3>
            <p class="text-muted-sm mb-0">
                None of the apprehensions in this range have both latitude and
                longitude. Add coordinates on the apprehension form to see them here.
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body p-3">
            <div id="hotspotMap" role="region" aria-label="Hotspot map"></div>
        </div>
    </div>

    <?php if (!empty($locationTotals)): ?>
        <div class="card mt-3">
            <div class="card-body p-4">
                <h3 class="section-title mb-3">Top locations</h3>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Location</th>
                                <th class="text-end">Apprehensions</th>
                                <th class="text-end">Mapped</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($locationTotals as $loc): ?>
                                <tr>
                                    <td><?= e($loc['location_text']) ?></td>
                                    <td class="text-end"><?= number_format((int) $loc['total']) ?></td>
                                    <td class="text-end">
                                        <?= number_format((int) $loc['mapped']) ?>
                                        <?php if ((int) $loc['mapped'] < (int) $loc['total']): ?>
                                            <span class="text-muted-sm"> / <?= number_format((int) $loc['total']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script nonce="<?= e(csp_nonce()) ?>">
window.HOTSPOT_DATA = {
    markers: <?= json_encode(
        $markers,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    ) ?>,
    center: { lat: 7.8257, lng: 123.4370 },
    zoom: 12
};
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>