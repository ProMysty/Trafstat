<?php
/**
 * admin/reports.php
 * Filter / preview / print view for all reports.
 *
 * Modes:
 *   (default)              — filter form + preview (first 200 rows) + action buttons
 *   ?print=1&…filters      — standalone print view with @media print CSS,
 *                            auto-triggers window.print()
 *
 * CSV export is handled by actions/export_report.php (POST, CSRF-protected)
 * → redirects to admin/download_export.php?token=<hex> for one-time download.
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/reports.php';

$admin = require_admin_ready();

$type       = $_GET['type']         ?? 'apprehensions';
$dateFrom   = trim($_GET['date_from']   ?? '');
$dateTo     = trim($_GET['date_to']     ?? '');
$categoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;

$allTypes = report_types();
if (!isset($allTypes[$type])) {
    $type = 'apprehensions';
}

if ($dateFrom === '' || !is_valid_date($dateFrom)) {
    $dateFrom = date('Y-m-d', strtotime('-30 days'));
}
if ($dateTo === '' || !is_valid_date($dateTo)) {
    $dateTo = date('Y-m-d');
}
if ($dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

$filters = [
    'date_from'   => $dateFrom,
    'date_to'     => $dateTo,
    'category_id' => $categoryId,
];

$printMode = isset($_GET['print']) && $_GET['print'] === '1';

/* ================================================================== */
/* PRINT VIEW (no header/footer, auto window.print())                  */
/* ================================================================== */
if ($printMode) {
    try {
        $report = build_report_data($type, $filters);
    } catch (Throwable $e) {
        error_log('[TrafStat][reports/print] ' . $e->getMessage());
        http_response_code(500);
        exit('Unable to build the report. Please try again.');
    }

    log_activity(
        (int) $admin['admin_id'],
        'REPORT_PRINTED',
        'Generated print view for "' . $report['title'] . '" (' . $report['row_count'] . ' rows).',
        null, null,
        [
            'report_type' => $type,
            'date_from'   => $allTypes[$type]['uses_range'] ? $dateFrom : null,
            'date_to'     => $allTypes[$type]['uses_range'] ? $dateTo   : null,
            'category_id' => $categoryId > 0 ? $categoryId : null,
            'row_count'   => $report['row_count'],
            'format'      => 'print',
        ]
    );

    $categoryLabel = 'All categories';
    if ($categoryId > 0) {
        $catRow = db_one('SELECT violation_name FROM violation_categories WHERE category_id = ?', [$categoryId]);
        if ($catRow !== null) {
            $categoryLabel = $catRow['violation_name'];
        }
    }

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Print — <?= e($report['title']) ?> — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
</head>
<body style="background:#fff;">
<div class="container-fluid p-4">

    <div class="no-print mb-3">
        <button type="button" data-print class="btn btn-primary">
            <i class="bi bi-printer me-1"></i> Print / Save as PDF
        </button>
        <button type="button" data-close-window class="btn btn-outline-secondary">
            Close
        </button>
        <span class="text-muted-sm ms-3">
            Use your browser's print dialog to print or save as PDF.
        </span>
    </div>

    <div class="print-view">
        <div class="print-header">
            <h1><?= e(APP_NAME) ?> &mdash; <?= e($report['title']) ?></h1>
            <div class="print-meta">
                Pagadian City Traffic Management Office
                &middot;
                <?php if ($allTypes[$type]['uses_range']): ?>
                    Range: <?= e(format_date($dateFrom)) ?> — <?= e(format_date($dateTo)) ?>
                    &middot; Category: <?= e($categoryLabel) ?>
                    &middot;
                <?php endif; ?>
                Generated <?= e(format_datetime(date('Y-m-d H:i:s'))) ?>
                by <?= e($admin['full_name']) ?>
                &middot;
                <?= number_format($report['row_count']) ?> record<?= $report['row_count'] === 1 ? '' : 's' ?>
            </div>
        </div>

        <?php if (empty($report['rows'])): ?>
            <p class="text-muted">No records match the selected filters.</p>
        <?php else: ?>
            <table class="print-table">
                <thead>
                    <tr>
                        <?php foreach ($report['columns'] as $col): ?>
                            <th><?= e($col) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['rows'] as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td><?= e((string) $cell) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="print-footer">
            <?= e(APP_NAME) ?> &mdash; official operational report &middot;
            Confidential &middot; For authorized personnel only.
        </div>
    </div>
</div>

<script nonce="<?= e(csp_nonce()) ?>">
window.addEventListener('load', function () {
    setTimeout(function () { window.print(); }, 350);
});
</script>
</body>
</html>
    <?php
    exit;
}

/* ================================================================== */
/* NORMAL VIEW (filters + preview)                                     */
/* ================================================================== */
try {
    $report = build_report_data($type, $filters);
} catch (Throwable $e) {
    error_log('[TrafStat][reports] ' . $e->getMessage());
    set_flash('error', 'Unable to build the report. Please try again.');
    redirect(url('admin/reports.php'));
}

$previewLimit = 200;
$previewRows  = array_slice($report['rows'], 0, $previewLimit);

$categoriesForFilter = db_all(
    'SELECT category_id, violation_name FROM violation_categories ORDER BY violation_name'
);

$baseQueryString = http_build_query([
    'type'        => $type,
    'date_from'   => $dateFrom,
    'date_to'     => $dateTo,
    'category_id' => $categoryId > 0 ? $categoryId : '',
]);

$pageTitle   = 'Reports';
$currentPage = 'reports';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h2 class="section-title mb-1">Reports</h2>
        <p class="text-muted-sm mb-0">
            Preview, print or export to CSV (Excel-compatible).
        </p>
    </div>
</div>

<!-- Report type selector -->
<div class="row g-2 mb-3">
    <?php foreach ($allTypes as $key => $meta): ?>
        <?php $isSelected = ($key === $type); ?>
        <div class="col-12 col-md-6 col-xl-4">
            <a href="<?= e(url('admin/reports.php?type=' . urlencode($key))) ?>"
               class="card h-100 text-decoration-none <?= $isSelected ? 'border-primary' : '' ?>"
               style="<?= $isSelected ? 'border-width:2px;' : '' ?>">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i class="bi bi-file-earmark-text <?= $isSelected ? 'text-primary' : 'text-secondary' ?>"></i>
                        <strong class="<?= $isSelected ? 'text-primary' : 'text-dark' ?>">
                            <?= e($meta['label']) ?>
                        </strong>
                        <?php if ($isSelected): ?>
                            <span class="badge bg-primary ms-auto">Selected</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-muted-sm mb-0"><?= e($meta['description']) ?></p>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<!-- Filter form -->
<form method="get" class="card mb-3">
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <?php if ($allTypes[$type]['uses_range']): ?>
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
            <?php else: ?>
                <div class="col-12 col-md-9">
                    <p class="text-muted-sm mb-0">
                        This report lists all active records — no date or category filter applies.
                    </p>
                </div>
            <?php endif; ?>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">
                    <i class="bi bi-funnel me-1"></i> Apply
                </button>
                <a href="<?= e(url('admin/reports.php?type=' . urlencode($type))) ?>"
                   class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </div>
</form>

<!-- Action buttons + summary -->
<div class="card mb-3">
    <div class="card-body p-3 d-flex flex-wrap align-items-center gap-2">
        <div class="flex-grow-1">
            <strong><?= e($report['title']) ?></strong>
            &middot;
            <span class="text-muted-sm">
                <?= number_format($report['row_count']) ?> record<?= $report['row_count'] === 1 ? '' : 's' ?>
                <?php if ($report['row_count'] > $previewLimit): ?>
                    &middot; previewing first <?= number_format($previewLimit) ?>
                <?php endif; ?>
            </span>
        </div>

        <a href="<?= e(url('admin/reports.php?' . $baseQueryString . '&print=1')) ?>"
           target="_blank" rel="noopener"
           class="btn btn-outline-primary">
            <i class="bi bi-printer me-1"></i> Print / Save as PDF
        </a>

        <?php if ($report['row_count'] > 0): ?>
            <form method="post" action="<?= e(url('actions/export_report.php')) ?>" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="type"        value="<?= e($type) ?>">
                <input type="hidden" name="date_from"   value="<?= e($dateFrom) ?>">
                <input type="hidden" name="date_to"     value="<?= e($dateTo) ?>">
                <input type="hidden" name="category_id" value="<?= (int) $categoryId ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-filetype-csv me-1"></i> Export CSV
                </button>
            </form>
        <?php else: ?>
            <button class="btn btn-outline-secondary" disabled>
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Preview table -->
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <?php foreach ($report['columns'] as $col): ?>
                        <th><?= e($col) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($previewRows)): ?>
                    <tr>
                        <td colspan="<?= count($report['columns']) ?>"
                            class="text-center text-muted-sm py-4">
                            No records match the selected filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($previewRows as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td><?= e((string) $cell) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($report['row_count'] > $previewLimit): ?>
    <div class="alert alert-info mt-3 mb-0">
        <i class="bi bi-info-circle me-1"></i>
        Preview shows the first <?= number_format($previewLimit) ?> rows.
        <strong>Print</strong> and <strong>Export CSV</strong> contain all
        <?= number_format($report['row_count']) ?> rows.
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>