<?php
/**
 * actions/save_apprehension.php
 * Create or update apprehension records.
 *
 * Reference numbers (APP-YYYY-NNNNN) are generated inside a transaction
 * using the placeholder → final-code pattern, identical to violators.
 *
 * A recorded apprehension is not a legal finding of guilt. This module
 * never determines penalties, guilt, or legal outcomes.
 */
require_once __DIR__ . '/../includes/init.php';
$admin = require_admin_ready();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('admin/apprehensions.php'));
}

require_csrf();

$apprehensionId = post_int('apprehension_id');
$isEdit         = ($apprehensionId !== null);

$data = [
    'violator_id'       => post_int('violator_id'),
    'vehicle_id'        => post_int('vehicle_id'),
    'category_id'       => post_int('category_id'),
    'apprehension_date' => post_string('apprehension_date'),
    'apprehension_time' => post_string('apprehension_time'),
    'location_text'     => post_string('location_text'),
    'latitude'          => post_string('latitude'),
    'longitude'         => post_string('longitude'),
    'remarks'           => post_string('remarks'),
];

$errors = [];

/* ------------------------------------------------------------------ */
/* Validation                                                          */
/* ------------------------------------------------------------------ */
if ($data['violator_id'] === null || $data['violator_id'] <= 0) {
    $errors[] = 'Please select a violator.';
}

if ($data['category_id'] === null || $data['category_id'] <= 0) {
    $errors[] = 'Please select a violation category.';
}

if ($data['apprehension_date'] === '') {
    $errors[] = 'Apprehension date is required.';
} elseif (!is_valid_date($data['apprehension_date'])) {
    $errors[] = 'Apprehension date must be a valid date (YYYY-MM-DD).';
} elseif ($data['apprehension_date'] > date('Y-m-d')) {
    $errors[] = 'Apprehension date cannot be in the future.';
}

if ($data['apprehension_time'] !== '' && !is_valid_time($data['apprehension_time'])) {
    $errors[] = 'Apprehension time must be in 24-hour format (HH:MM).';
}

if ($data['location_text'] === '') {
    $errors[] = 'Location is required.';
} elseif (mb_strlen($data['location_text']) > 255) {
    $errors[] = 'Location must be 255 characters or fewer.';
}

if (mb_strlen($data['remarks']) > 2000) {
    $errors[] = 'Remarks are too long (maximum 2000 characters).';
}

/* ------------------------------------------------------------------ */
/* Referential checks                                                  */
/* ------------------------------------------------------------------ */
if (empty($errors) && $data['violator_id'] !== null) {
    // Simplified check: violator must exist AND be active,
    // unless editing the current one.
    $currentViolator = null;
    if ($isEdit && $apprehensionId !== null) {
        $currentViolator = db_value(
            'SELECT violator_id FROM apprehension_records WHERE apprehension_id = ?',
            [$apprehensionId]
        );
    }
    $preserve = ($currentViolator !== null && (int) $currentViolator === $data['violator_id']);

    $sql = $preserve
        ? 'SELECT COUNT(*) FROM traffic_violators WHERE violator_id = ?'
        : "SELECT COUNT(*) FROM traffic_violators WHERE violator_id = ? AND record_status = 'active'";
    if (!db_value($sql, [$data['violator_id']])) {
        $errors[] = 'The selected violator does not exist or is not active.';
    }
}

if (empty($errors) && $data['category_id'] !== null) {
    $currentCategory = null;
    if ($isEdit && $apprehensionId !== null) {
        $currentCategory = db_value(
            'SELECT category_id FROM apprehension_records WHERE apprehension_id = ?',
            [$apprehensionId]
        );
    }
    $preserve = ($currentCategory !== null && (int) $currentCategory === $data['category_id']);

    $sql = $preserve
        ? 'SELECT COUNT(*) FROM violation_categories WHERE category_id = ?'
        : "SELECT COUNT(*) FROM violation_categories WHERE category_id = ? AND status = 'active'";
    if (!db_value($sql, [$data['category_id']])) {
        $errors[] = 'The selected violation category does not exist or is inactive.';
    }
}

if (empty($errors) && $data['vehicle_id'] !== null && $data['violator_id'] !== null) {
    $vehicle = db_one(
        'SELECT vehicle_id, violator_id FROM vehicles WHERE vehicle_id = ?',
        [$data['vehicle_id']]
    );
    if ($vehicle === null) {
        $errors[] = 'The selected vehicle does not exist.';
    } elseif ($vehicle['violator_id'] !== null
        && (int) $vehicle['violator_id'] !== $data['violator_id']) {
        $errors[] = 'The selected vehicle is assigned to a different violator.';
    }
}

/* Coordinates */
$lat = null;
$lng = null;

if ($data['latitude'] !== '' || $data['longitude'] !== '') {
    if ($data['latitude'] === '' || $data['longitude'] === '') {
        $errors[] = 'Both latitude and longitude must be provided, or both left blank.';
    } else {
        $latValue = filter_var($data['latitude'], FILTER_VALIDATE_FLOAT);
        $lngValue = filter_var($data['longitude'], FILTER_VALIDATE_FLOAT);

        if ($latValue === false || $latValue < -90 || $latValue > 90) {
            $errors[] = 'Latitude must be a number between -90 and 90.';
        } elseif ($lngValue === false || $lngValue < -180 || $lngValue > 180) {
            $errors[] = 'Longitude must be a number between -180 and 180.';
        } else {
            $lat = round((float) $latValue, 7);
            $lng = round((float) $lngValue, 7);
        }
    }
}

/* ------------------------------------------------------------------ */
/* On error: stash and return to form                                  */
/* ------------------------------------------------------------------ */
if (!empty($errors)) {
    set_form_state($errors, $data, $isEdit ? $apprehensionId : null);
    redirect($isEdit
        ? url('admin/apprehensions.php?action=edit&id=' . $apprehensionId)
        : url('admin/apprehensions.php?action=new'));
}

/* ------------------------------------------------------------------ */
/* Save                                                                */
/* ------------------------------------------------------------------ */
try {
    if ($isEdit) {
        $existing = db_one(
            'SELECT apprehension_id, reference_no FROM apprehension_records WHERE apprehension_id = ?',
            [$apprehensionId]
        );
        if ($existing === null) {
            set_flash('error', 'That apprehension record no longer exists.');
            redirect(url('admin/apprehensions.php'));
        }

        db_execute(
            "UPDATE apprehension_records
                SET violator_id       = :violator_id,
                    vehicle_id        = :vehicle_id,
                    category_id       = :category_id,
                    apprehension_date = :apprehension_date,
                    apprehension_time = :apprehension_time,
                    location_text     = :location_text,
                    latitude          = :latitude,
                    longitude         = :longitude,
                    remarks           = :remarks
              WHERE apprehension_id   = :id",
            [
                ':violator_id'       => $data['violator_id'],
                ':vehicle_id'        => $data['vehicle_id'],
                ':category_id'       => $data['category_id'],
                ':apprehension_date' => $data['apprehension_date'],
                ':apprehension_time' => $data['apprehension_time'] !== '' ? $data['apprehension_time'] : null,
                ':location_text'     => $data['location_text'],
                ':latitude'          => $lat,
                ':longitude'         => $lng,
                ':remarks'           => $data['remarks'] !== '' ? $data['remarks'] : null,
                ':id'                => $apprehensionId,
            ]
        );

        log_activity(
            (int) $admin['admin_id'],
            'APPREHENSION_UPDATED',
            'Updated apprehension ' . $existing['reference_no'] . '.',
            'apprehension_records',
            $apprehensionId
        );

        set_flash('success', 'Apprehension ' . $existing['reference_no'] . ' updated.');
        redirect(url('admin/apprehensions.php'));

    } else {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            // 16-char placeholder — fits reference_no VARCHAR(25).
            $placeholder = bin2hex(random_bytes(8));

            db_execute(
                "INSERT INTO apprehension_records
                    (reference_no, violator_id, vehicle_id, category_id,
                     apprehension_date, apprehension_time, location_text,
                     latitude, longitude, remarks, recorded_by, record_status)
                 VALUES
                    (:reference, :violator_id, :vehicle_id, :category_id,
                     :apprehension_date, :apprehension_time, :location_text,
                     :latitude, :longitude, :remarks, :recorded_by, 'active')",
                [
                    ':reference'         => $placeholder,
                    ':violator_id'       => $data['violator_id'],
                    ':vehicle_id'        => $data['vehicle_id'],
                    ':category_id'       => $data['category_id'],
                    ':apprehension_date' => $data['apprehension_date'],
                    ':apprehension_time' => $data['apprehension_time'] !== '' ? $data['apprehension_time'] : null,
                    ':location_text'     => $data['location_text'],
                    ':latitude'          => $lat,
                    ':longitude'         => $lng,
                    ':remarks'           => $data['remarks'] !== '' ? $data['remarks'] : null,
                    ':recorded_by'       => (int) $admin['admin_id'],
                ]
            );

            $newId = (int) $pdo->lastInsertId();
            $referenceNo = sprintf('APP-%s-%05d', date('Y'), $newId);

            db_execute(
                'UPDATE apprehension_records SET reference_no = :reference WHERE apprehension_id = :id',
                [':reference' => $referenceNo, ':id' => $newId]
            );

            $pdo->commit();

            log_activity(
                (int) $admin['admin_id'],
                'APPREHENSION_CREATED',
                'Created apprehension ' . $referenceNo . '.',
                'apprehension_records',
                $newId
            );

            set_flash('success', 'Apprehension created: ' . $referenceNo);
            redirect(url('admin/apprehensions.php'));

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
} catch (Throwable $e) {
    error_log('[TrafStat][save_apprehension] ' . $e->getMessage());
    set_flash('error', 'Could not save the apprehension record. Please try again.');
    redirect(url('admin/apprehensions.php'));
}