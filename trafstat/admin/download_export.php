<?php
/**
 * admin/download_export.php
 * Streams a previously generated CSV export exactly once, then deletes it.
 *
 * The 64-character random token acts as a single-use capability. The storage
 * directory is blocked by .htaccess, so this endpoint is the only way to read
 * the file. Requires an authenticated administrator.
 */
require_once __DIR__ . '/../includes/init.php';

$admin = require_admin_ready();

$token = isset($_GET['token']) ? (string) $_GET['token'] : '';

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(400);
    exit('Invalid download token.');
}

$exportDir = dirname(__DIR__) . '/storage/exports';
$csvPath   = $exportDir . '/' . $token . '.csv';
$metaPath  = $exportDir . '/' . $token . '.meta.json';

if (!is_file($csvPath)) {
    http_response_code(404);
    exit('This download link has expired. Please generate the export again.');
}

$meta = null;
if (is_file($metaPath)) {
    $raw = @file_get_contents($metaPath);
    if ($raw !== false) {
        $meta = json_decode($raw, true);
    }
}

$friendlyName = (is_array($meta) && !empty($meta['friendly_name']))
    ? $meta['friendly_name']
    : 'trafstat_export.csv';

/* Sanity — never allow path traversal or header injection in the filename. */
$friendlyName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($friendlyName));
if ($friendlyName === '' || $friendlyName === '.' || $friendlyName === '..') {
    $friendlyName = 'trafstat_export.csv';
}

log_activity(
    (int) $admin['admin_id'],
    'REPORT_DOWNLOADED',
    'Downloaded export "' . $friendlyName . '".',
    null, null,
    [
        'report_type' => $meta['report_type'] ?? null,
        'row_count'   => $meta['row_count']   ?? null,
        'format'      => 'csv',
    ]
);

/* Stream the file. */
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $friendlyName . '"');
header('Content-Length: ' . (string) filesize($csvPath));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

readfile($csvPath);

/* Single-use: delete the file and its metadata after streaming. */
@unlink($csvPath);
@unlink($metaPath);

exit;