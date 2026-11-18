<?php
/**
 * actions/save_violator.php
 * Handles creation and update of traffic_violators records.
 *
 * Flow: POST → validate → on error redirect back to the form,
 *                       on success redirect to the list with a flash.
 *
 * Code generation:
 *   The human-readable violator_code (VIO-YYYY-NNNNN) is derived from the
 *   auto-increment violator_id inside a transaction. We insert with a
 *   placeholder code, then update. This guarantees uniqueness with no
 *   sequence table and no race conditions.
 */
require_once __DIR__ . '/../includes/init.php';
$admin = require_admin_ready();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('admin/violators.php'));
}

require_csrf();

$violatorId = post_int('violator_id');   // null = create, int = update
$isEdit     = ($violatorId !== null);

$data = [
    'first_name'     => post_string('first_name'),
    'middle_name'    => post_string('middle_name'),
    'last_name'      => post_string('last_name'),
    'suffix'         => post_string('suffix'),
    'address'        => post_string('address'),
    'contact_number' => post_string('contact_number'),
];

$errors = [];

/* ------------------------------------------------------------------ */
/* Validation                                                          */
/* ------------------------------------------------------------------ */
if ($data['first_name'] === '') {
    $errors[] = 'First name is required.';
} elseif (mb_strlen($data['first_name']) > 60) {
    $errors[] = 'First name must be 60 characters or fewer.';
}

if ($data['last_name'] === '') {
    $errors[] = 'Last name is required.';
} elseif (mb_strlen($data['last_name']) > 60) {
    $errors[] = 'Last name must be 60 characters or fewer.';
}

if ($data['middle_name'] !== '' && mb_strlen($data['middle_name']) > 60) {
    $errors[] = 'Middle name must be 60 characters or fewer.';
}
if ($data['suffix'] !== '' && mb_strlen($data['suffix']) > 10) {
    $errors[] = 'Suffix must be 10 characters or fewer.';
}
if ($data['address'] === '') {
    $errors[] = 'Address is required.';
} elseif (mb_strlen($data['address']) > 255) {
    $errors[] = 'Address must be 255 characters or fewer.';
}

if ($data['contact_number'] !== ''
    && !preg_match('/^[0-9+\-\s()]{7,20}$/', $data['contact_number'])) {
    $errors[] = 'Contact number may only contain digits, spaces, parentheses, + and - (7–20 characters).';
}

/* ------------------------------------------------------------------ */
/* Duplicate guard — exact name + address among active records.        */
/* ------------------------------------------------------------------ */
$duplicateSql = "SELECT violator_id, violator_code
                   FROM traffic_violators
                  WHERE LOWER(TRIM(first_name)) = LOWER(TRIM(:first))
                    AND LOWER(TRIM(last_name))  = LOWER(TRIM(:last))
                    AND LOWER(TRIM(address))    = LOWER(TRIM(:address))
                    AND record_status = 'active'";
$duplicateParams = [
    ':first'   => $data['first_name'],
    ':last'    => $data['last_name'],
    ':address' => $data['address'],
];
if ($isEdit) {
    $duplicateSql .= ' AND violator_id <> :id';
    $duplicateParams[':id'] = $violatorId;
}
$duplicateSql .= ' LIMIT 1';

$duplicate = db_one($duplicateSql, $duplicateParams);

if ($duplicate !== null) {
    $errors[] = 'An active violator with this name and address already exists ('
              . $duplicate['violator_code'] . '). '
              . 'If this is the same person, edit the existing record instead.';
}

/* ------------------------------------------------------------------ */
/* On error: stash and go back to the form.                            */
/* ------------------------------------------------------------------ */
if (!empty($errors)) {
    set_form_state($errors, $data, $violatorId);
    $returnUrl = $isEdit
        ? url('admin/violators.php?action=edit&id=' . $violatorId)
        : url('admin/violators.php?action=new');
    redirect($returnUrl);
}

/* ------------------------------------------------------------------ */
/* Save                                                                */
/* ------------------------------------------------------------------ */
try {
    if ($isEdit) {
        $existing = db_one(
            'SELECT violator_id, violator_code FROM traffic_violators WHERE violator_id = ?',
            [$violatorId]
        );

        if ($existing === null) {
            set_flash('error', 'That violator record no longer exists.');
            redirect(url('admin/violators.php'));
        }

        db_execute(
            "UPDATE traffic_violators
                SET first_name     = :first,
                    middle_name    = :middle,
                    last_name      = :last,
                    suffix         = :suffix,
                    address        = :address,
                    contact_number = :contact,
                    updated_by     = :admin
              WHERE violator_id    = :id",
            [
                ':first'    => $data['first_name'],
                ':middle'   => $data['middle_name'] !== '' ? $data['middle_name'] : null,
                ':last'     => $data['last_name'],
                ':suffix'   => $data['suffix']      !== '' ? $data['suffix']      : null,
                ':address'  => $data['address'],
                ':contact'  => $data['contact_number'] !== '' ? $data['contact_number'] : null,
                ':admin'    => (int) $admin['admin_id'],
                ':id'       => $violatorId,
            ]
        );

        log_activity(
            (int) $admin['admin_id'],
            'VIOLATOR_UPDATED',
            'Updated violator ' . $existing['violator_code'] . '.',
            'traffic_violators',
            (int) $violatorId,
            ['changed_fields' => array_keys(array_filter($data, fn($v) => $v !== ''))]
        );

        set_flash('success', 'Violator ' . $existing['violator_code'] . ' updated successfully.');
        redirect(url('admin/violators.php'));

    } else {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            // Temporary code that fits violator_code VARCHAR(20).
            // 16 hex characters — cannot collide with VIO-YYYY-NNNNN format.
            $placeholder = bin2hex(random_bytes(8));

            db_execute(
                "INSERT INTO traffic_violators
                    (violator_code, first_name, middle_name, last_name, suffix,
                     address, contact_number, record_status, created_by, updated_by)
                 VALUES
                    (:code, :first, :middle, :last, :suffix,
                    :address, :contact, 'active', :created_by, :updated_by)",
                [
                    ':code'     => $placeholder,
                    ':first'    => $data['first_name'],
                    ':middle'   => $data['middle_name'] !== '' ? $data['middle_name'] : null,
                    ':last'     => $data['last_name'],
                    ':suffix'   => $data['suffix']      !== '' ? $data['suffix']      : null,
                    ':address'  => $data['address'],
                    ':contact'  => $data['contact_number'] !== '' ? $data['contact_number'] : null,
                    ':created_by' => (int) $admin['admin_id'],
                    ':updated_by' => (int) $admin['admin_id'],
                ]
            );
            $newId = (int) $pdo->lastInsertId();
            $finalCode = sprintf('VIO-%s-%05d', date('Y'), $newId);

            db_execute(
                'UPDATE traffic_violators SET violator_code = :code WHERE violator_id = :id',
                [':code' => $finalCode, ':id' => $newId]
            );

            $pdo->commit();

            log_activity(
                (int) $admin['admin_id'],
                'VIOLATOR_CREATED',
                'Created violator ' . $finalCode . '.',
                'traffic_violators',
                $newId
            );

            set_flash('success', 'Violator created: ' . $finalCode);
            redirect(url('admin/violators.php'));

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
} catch (Throwable $e) {
    error_log('[TrafStat][save_violator] ' . $e->getMessage());
    set_flash('error', 'Could not save the violator record. Please try again.');
    redirect(url('admin/violators.php'));
}