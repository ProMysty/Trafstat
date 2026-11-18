<?php
/**
 * actions/export_report.php
 * Generates a CSV export, stores it under a random token in the protected
 * storage directory, logs the export, and redirects to the download endpoint.
 *
 * Download URLs are single-use capabilities. Files are auto-purged after
 * one hour even if never downloaded.
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/reports.php';

$admin = require_admin_ready();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('admin/reports.php'));
}

require_csrf();

$type       = post_string('type');
$dateFrom   = post_string('date_from');
$dateTo     = post_string('date_to');
$categoryId = post_int('category_id') ?? 0;

$allTypes = report_types();
if (!isset($allTypes[$type])) {
    set_flash('error', 'Unknown report type.');
    redirect(url('admin/reports.php'));
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

try {
    $report = build_report_data($type, $filters);
} catch (Throwable $e) {
    error_log('[TrafStat][export_report] build failed: ' . $e->getMessage());
    set_flash('error', 'Could not build the report. Please try again.');
    redirect(url('admin/reports.php?type=' . urlencode($type)));
}

if ($report['row_count'] === 0) {
    set_flash('error', 'There are no rows to export for the selected filters.');
    redirect(url('admin/reports.php?' . http_build_query([
        'type' => $type, 'date_from' => $dateFrom,
        'date_to' => $dateTo, 'category_id' => $categoryId,
    ])));
}

$exportDir = dirname(__DIR__) . '/storage/exports';
if (!is_dir($exportDir)) {
    @mkdir($exportDir, 0750, true);
}
if (!is_dir($exportDir) || !is_writable($exportDir)) {
    error_log('[TrafStat][export_report] export directory not writable: ' . $exportDir);
    set_flash('error', 'Server configuration error: export directory not writable.');
    redirect(url('admin/reports.php?type=' . urlencode($type)));
}

/* Purge stale exports before creating a new one. */
cleanup_old_exports($exportDir, 3600);

$token    = bin2hex(random_bytes(32));
$csvPath  = $exportDir . '/' . $token . '.csv';
$metaPath = $exportDir . '/' . $token . '.meta.json';

$friendlyName = 'trafstat_' . $type . '_' . date('Ymd_His') . '.csv';

try {
    write_csv($csvPath, $report['columns'], $report['rows']);

    file_put_contents(
        $metaPath,
        json_encode([
            'report_type'   => $type,
            'friendly_name' => $friendlyName,
            'admin_id'      => (int) $admin['admin_id'],
            'created_at'    => time(),
            'row_count'     => $report['row_count'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    @chmod($metaPath, 0640);

} catch (Throwable $e) {
    error_log('[TrafStat][export_report] write failed: ' . $e->getMessage());
    @unlink($csvPath);
    @unlink($metaPath);
    set_flash('error', 'Could not write the export file. Please try again.');
    redirect(url('admin/reports.php?type=' . urlencode($type)));
}

log_activity(
    (int) $admin['admin_id'],
    'REPORT_EXPORTED',
    'Exported "' . $report['title'] . '" to CSV (' . $report['row_count'] . ' rows).',
    null, null,
    [
        'report_type' => $type,
        'date_from'   => $allTypes[$type]['uses_range'] ? $dateFrom : null,
        'date_to'     => $allTypes[$type]['uses_range'] ? $dateTo   : null,
        'category_id' => $categoryId > 0 ? $categoryId : null,
        'row_count'   => $report['row_count'],
        'format'      => 'csv',
    ]
);

set_flash('success',
    'Export ready — ' . number_format($report['row_count']) . ' row(s). Download will start automatically.');
redirect(url('admin/download_export.php?token=' . $token));