<?php
/**
 * actions/archive_record.php
 * Whitelisted archive / restore for supported record types.
 *
 * Only records that appear in $allowed may be mutated here. This is the
 * single place in the application that flips record_status, so all archive
 * events share identical logging and authorization.
 *
 * Records are never deleted — archiving preserves history for the
 * office's retention and disposal policy.
 */
require_once __DIR__ . '/../includes/init.php';
$admin = require_admin_ready();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('admin/dashboard.php'));
}

require_csrf();

$entity = post_string('entity');
$action = post_string('action');
$id     = post_int('id');

$allowed = [
    'violator' => [
        'table'    => 'traffic_violators',
        'pk'       => 'violator_id',
        'label'    => 'violator',
        'codeCol'  => 'violator_code',
        'redirect' => 'admin/violators.php',
    ],
    'vehicle' => [
        'table'    => 'vehicles',
        'pk'       => 'vehicle_id',
        'label'    => 'vehicle',
        'codeCol'  => 'plate_number',
        'redirect' => 'admin/vehicles.php',
    ],
    'apprehension' => [
        'table'    => 'apprehension_records',
        'pk'       => 'apprehension_id',
        'label'    => 'apprehension record',
        'codeCol'  => 'reference_no',
        'redirect' => 'admin/apprehensions.php',
    ],
];

if (!isset($allowed[$entity]) || $id === null || $id <= 0
    || !in_array($action, ['archive', 'restore'], true)) {
    set_flash('error', 'Invalid archive request.');
    redirect(url('admin/dashboard.php'));
}

$config      = $allowed[$entity];
$newStatus   = $action === 'archive' ? 'archived' : 'active';
$targetLabel = $action === 'archive' ? 'archived'   : 'restored';

$existing = db_one(
    "SELECT {$config['pk']} AS id, {$config['codeCol']} AS code, record_status
       FROM {$config['table']}
      WHERE {$config['pk']} = ?",
    [$id]
);

if ($existing === null) {
    set_flash('error', ucfirst($config['label']) . ' not found.');
    redirect(url($config['redirect']));
}

if ($existing['record_status'] === $newStatus) {
    set_flash('info', ucfirst($config['label']) . ' is already ' . $newStatus . '.');
    redirect(url($config['redirect']));
}

db_execute(
    "UPDATE {$config['table']}
        SET record_status = :status, updated_by = :admin
      WHERE {$config['pk']} = :id",
    [
        ':status' => $newStatus,
        ':admin'  => (int) $admin['admin_id'],
        ':id'     => $id,
    ]
);

log_activity(
    (int) $admin['admin_id'],
    strtoupper($entity) . '_' . strtoupper($action) . 'D',
    ucfirst($entity) . ' ' . $existing['code'] . ' ' . $targetLabel . '.',
    $config['table'],
    (int) $id
);

set_flash('success', ucfirst($config['label']) . ' ' . $existing['code'] . ' has been ' . $targetLabel . '.');
redirect(url($config['redirect']));