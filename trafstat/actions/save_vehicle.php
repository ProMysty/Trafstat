<?php
/**
 * actions/save_vehicle.php
 * Handles creation and update of vehicles records.
 * Plate number is the natural unique key (unique across active + archived).
 */
require_once __DIR__ . '/../includes/init.php';
$admin = require_admin_ready();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('admin/vehicles.php'));
}

require_csrf();

$vehicleId = post_int('vehicle_id');
$isEdit    = ($vehicleId !== null);

$allowedTypes = ['Motorcycle', 'Car', 'Van', 'Truck', 'Tricycle', 'Jeepney', 'Bus', 'Other'];

$data = [
    'plate_number'      => strtoupper(post_string('plate_number')),
    'vehicle_type'      => post_string('vehicle_type'),
    'vehicle_make'      => post_string('vehicle_make'),
    'vehicle_model'     => post_string('vehicle_model'),
    'vehicle_color'     => post_string('vehicle_color'),
    'registration_info' => post_string('registration_info'),
    'violator_id'       => post_int('violator_id'),
];

$errors = [];

/* ------------------------------------------------------------------ */
/* Validation                                                          */
/* ------------------------------------------------------------------ */
if ($data['plate_number'] === '') {
    $errors[] = 'Plate number is required.';
} elseif (strlen($data['plate_number']) > 20) {
    $errors[] = 'Plate number must be 20 characters or fewer.';
} elseif (!preg_match('/^[A-Z0-9\- ]{3,20}$/', $data['plate_number'])) {
    $errors[] = 'Plate number may only contain letters, digits, hyphens and spaces.';
}

if ($data['vehicle_type'] !== '' && !in_array($data['vehicle_type'], $allowedTypes, true)) {
    $errors[] = 'Please choose a valid vehicle type.';
}

foreach (['vehicle_make' => 50, 'vehicle_model' => 50, 'vehicle_color' => 30, 'registration_info' => 150] as $field => $maxLen) {
    if ($data[$field] !== '' && mb_strlen($data[$field]) > $maxLen) {
        $errors[] = ucwords(str_replace('_', ' ', $field)) . ' must be ' . $maxLen . ' characters or fewer.';
    }
}

/* Violator linkage — must be active, unless editing and preserving current. */
$currentViolatorId = null;
if ($isEdit) {
    $currentViolatorId = (int) db_value(
        'SELECT COALESCE(violator_id, 0) FROM vehicles WHERE vehicle_id = ?',
        [$vehicleId]
    );
    $currentViolatorId = $currentViolatorId ?: null;
}

if ($data['violator_id'] !== null) {
    $preserveCurrent = $isEdit && $currentViolatorId === $data['violator_id'];
    $sql = $preserveCurrent
        ? 'SELECT COUNT(*) FROM traffic_violators WHERE violator_id = ?'
        : "SELECT COUNT(*) FROM traffic_violators WHERE violator_id = ? AND record_status = 'active'";
    if (!db_value($sql, [$data['violator_id']])) {
        $errors[] = 'The selected violator does not exist or is not active.';
    }
}

/* Plate uniqueness — enforced across active AND archived records. */
$duplicateSql = 'SELECT vehicle_id, plate_number FROM vehicles WHERE plate_number = :plate';
$duplicateParams = [':plate' => $data['plate_number']];
if ($isEdit) {
    $duplicateSql .= ' AND vehicle_id <> :id';
    $duplicateParams[':id'] = $vehicleId;
}
$duplicateSql .= ' LIMIT 1';

if (db_one($duplicateSql, $duplicateParams) !== null) {
    $errors[] = 'A vehicle with this plate number already exists.';
}

/* ------------------------------------------------------------------ */
/* On error: stash and go back.                                        */
/* ------------------------------------------------------------------ */
if (!empty($errors)) {
    set_form_state($errors, $data, $vehicleId);
    $returnUrl = $isEdit
        ? url('admin/vehicles.php?action=edit&id=' . $vehicleId)
        : url('admin/vehicles.php?action=new');
    redirect($returnUrl);
}

/* ------------------------------------------------------------------ */
/* Save                                                                */
/* ------------------------------------------------------------------ */
try {
    if ($isEdit) {
        $existing = db_one(
            'SELECT vehicle_id, plate_number FROM vehicles WHERE vehicle_id = ?',
            [$vehicleId]
        );
        if ($existing === null) {
            set_flash('error', 'That vehicle record no longer exists.');
            redirect(url('admin/vehicles.php'));
        }

        db_execute(
            "UPDATE vehicles
                SET plate_number      = :plate,
                    vehicle_type      = :type,
                    vehicle_make      = :make,
                    vehicle_model     = :model,
                    vehicle_color     = :color,
                    registration_info = :reg,
                    violator_id       = :violator,
                    updated_by        = :admin
              WHERE vehicle_id        = :id",
            [
                ':plate'    => $data['plate_number'],
                ':type'     => $data['vehicle_type']      !== '' ? $data['vehicle_type']      : null,
                ':make'     => $data['vehicle_make']      !== '' ? $data['vehicle_make']      : null,
                ':model'    => $data['vehicle_model']     !== '' ? $data['vehicle_model']     : null,
                ':color'    => $data['vehicle_color']     !== '' ? $data['vehicle_color']     : null,
                ':reg'      => $data['registration_info'] !== '' ? $data['registration_info'] : null,
                ':violator' => $data['violator_id'],
                ':admin'    => (int) $admin['admin_id'],
                ':id'       => $vehicleId,
            ]
        );

        log_activity(
            (int) $admin['admin_id'],
            'VEHICLE_UPDATED',
            'Updated vehicle ' . $existing['plate_number'] . '.',
            'vehicles',
            (int) $vehicleId
        );

        set_flash('success', 'Vehicle ' . $existing['plate_number'] . ' updated successfully.');
        redirect(url('admin/vehicles.php'));

    } else {
        db_execute(
            "INSERT INTO vehicles
                (plate_number, vehicle_type, vehicle_make, vehicle_model,
                 vehicle_color, registration_info, violator_id,
                 record_status, created_by, updated_by)
             VALUES
                (:plate, :type, :make, :model, :color, :reg, :violator,
                 'active', :created_by, :updated_by)",
            [
                ':plate'    => $data['plate_number'],
                ':type'     => $data['vehicle_type']      !== '' ? $data['vehicle_type']      : null,
                ':make'     => $data['vehicle_make']      !== '' ? $data['vehicle_make']      : null,
                ':model'    => $data['vehicle_model']     !== '' ? $data['vehicle_model']     : null,
                ':color'    => $data['vehicle_color']     !== '' ? $data['vehicle_color']     : null,
                ':reg'      => $data['registration_info'] !== '' ? $data['registration_info'] : null,
                ':violator' => $data['violator_id'],
                ':created_by' => (int) $admin['admin_id'],
                ':updated_by' => (int) $admin['admin_id'],
            ]
        );
        $newId = (int) db()->lastInsertId();

        log_activity(
            (int) $admin['admin_id'],
            'VEHICLE_CREATED',
            'Created vehicle ' . $data['plate_number'] . '.',
            'vehicles',
            $newId
        );

        set_flash('success', 'Vehicle ' . $data['plate_number'] . ' created.');
        redirect(url('admin/vehicles.php'));
    }
} catch (Throwable $e) {
    error_log('[TrafStat][save_vehicle] ' . $e->getMessage());
    set_flash('error', 'Could not save the vehicle record. Please try again.');
    redirect(url('admin/vehicles.php'));
}