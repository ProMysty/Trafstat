<?php
/**
 * actions/save_violation_category.php
 * Handles all writes to violation_categories:
 *   intent=create  — create new category
 *   intent=update  — update existing category
 *   intent=toggle  — activate/deactivate a category
 *
 * The is_public flag gates what Module 7's public site may show.
 * Only set it to true once the office has approved the public wording.
 */
require_once __DIR__ . '/../includes/init.php';
$admin = require_admin_ready();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('admin/violation_categories.php'));
}

require_csrf();

$intent = post_string('intent');

/* ================================================================== */
/* TOGGLE (activate / deactivate)                                      */
/* ================================================================== */
if ($intent === 'toggle') {
    $categoryId = post_int('category_id');
    $newStatus  = post_string('new_status');

    if ($categoryId === null || $categoryId <= 0
        || !in_array($newStatus, ['active', 'inactive'], true)) {
        set_flash('error', 'Invalid category status request.');
        redirect(url('admin/violation_categories.php'));
    }

    $existing = db_one(
        'SELECT category_id, violation_name, status
           FROM violation_categories WHERE category_id = ?',
        [$categoryId]
    );

    if ($existing === null) {
        set_flash('error', 'That violation category no longer exists.');
        redirect(url('admin/violation_categories.php'));
    }

    if ($existing['status'] === $newStatus) {
        set_flash('info', 'Category is already ' . $newStatus . '.');
        redirect(url('admin/violation_categories.php'));
    }

    db_execute(
        'UPDATE violation_categories
            SET status = :status, updated_by = :updated_by
          WHERE category_id = :id',
        [
            ':status'     => $newStatus,
            ':updated_by' => (int) $admin['admin_id'],
            ':id'         => $categoryId,
        ]
    );

    log_activity(
        (int) $admin['admin_id'],
        'CATEGORY_STATUS_CHANGED',
        'Violation category "' . $existing['violation_name']
            . '" set to ' . $newStatus . '.',
        'violation_categories',
        $categoryId
    );

    set_flash(
        'success',
        'Category "' . $existing['violation_name'] . '" is now ' . $newStatus . '.'
    );
    redirect(url('admin/violation_categories.php'));
}

/* ================================================================== */
/* CREATE / UPDATE                                                     */
/* ================================================================== */
$categoryId  = post_int('category_id');
$isUpdate    = ($intent === 'update');

if ($isUpdate && ($categoryId === null || $categoryId <= 0)) {
    set_flash('error', 'Invalid category reference.');
    redirect(url('admin/violation_categories.php'));
}

$data = [
    'violation_name'     => post_string('violation_name'),
    'description'        => post_string('description'),
    'public_description' => post_string('public_description'),
    'is_public'          => isset($_POST['is_public']) ? 1 : 0,
];

$errors = [];

if ($data['violation_name'] === '') {
    $errors[] = 'Violation name is required.';
} elseif (mb_strlen($data['violation_name']) > 150) {
    $errors[] = 'Violation name must be 150 characters or fewer.';
}

if ($data['public_description'] !== '' && $data['is_public'] === 0) {
    // Not an error — but notify the admin that the public wording will not
    // appear on the public site until the flag is enabled.
    // Handled via flash after save.
}

// Uniqueness check on violation_name (case-insensitive).
$dupeSql = 'SELECT category_id FROM violation_categories
             WHERE LOWER(TRIM(violation_name)) = LOWER(TRIM(:name))';
$dupeParams = [':name' => $data['violation_name']];
if ($isUpdate) {
    $dupeSql .= ' AND category_id <> :id';
    $dupeParams[':id'] = $categoryId;
}
$dupeSql .= ' LIMIT 1';

if (db_one($dupeSql, $dupeParams) !== null) {
    $errors[] = 'A violation category with this name already exists.';
}

if (!empty($errors)) {
    set_form_state($errors, $data, $isUpdate ? $categoryId : null);
    redirect($isUpdate
        ? url('admin/violation_categories.php?action=edit&id=' . $categoryId)
        : url('admin/violation_categories.php?action=new'));
}

/* ------------------------------------------------------------------ */
/* Save                                                                */
/* ------------------------------------------------------------------ */
try {
    if ($isUpdate) {
        $existing = db_one(
            'SELECT category_id, violation_name FROM violation_categories WHERE category_id = ?',
            [$categoryId]
        );
        if ($existing === null) {
            set_flash('error', 'That violation category no longer exists.');
            redirect(url('admin/violation_categories.php'));
        }

        db_execute(
            "UPDATE violation_categories
                SET violation_name     = :name,
                    description        = :description,
                    public_description = :public_description,
                    is_public          = :is_public,
                    updated_by         = :updated_by
              WHERE category_id        = :id",
            [
                ':name'             => $data['violation_name'],
                ':description'      => $data['description']        !== '' ? $data['description']        : null,
                ':public_description' => $data['public_description'] !== '' ? $data['public_description'] : null,
                ':is_public'        => $data['is_public'],
                ':updated_by'       => (int) $admin['admin_id'],
                ':id'               => $categoryId,
            ]
        );

        log_activity(
            (int) $admin['admin_id'],
            'CATEGORY_UPDATED',
            'Updated violation category "' . $data['violation_name'] . '".',
            'violation_categories',
            $categoryId
        );

        set_flash('success', 'Violation category updated.');
        redirect(url('admin/violation_categories.php'));

    } else {
        db_execute(
            "INSERT INTO violation_categories
                (violation_name, description, public_description,
                 is_public, status, created_by, updated_by)
             VALUES
                (:name, :description, :public_description,
                 :is_public, 'active', :created_by, :updated_by)",
            [
                ':name'              => $data['violation_name'],
                ':description'       => $data['description']        !== '' ? $data['description']        : null,
                ':public_description'=> $data['public_description'] !== '' ? $data['public_description'] : null,
                ':is_public'         => $data['is_public'],
                ':created_by'        => (int) $admin['admin_id'],
                ':updated_by'        => (int) $admin['admin_id'],
            ]
        );

        $newId = (int) db()->lastInsertId();

        log_activity(
            (int) $admin['admin_id'],
            'CATEGORY_CREATED',
            'Created violation category "' . $data['violation_name'] . '".',
            'violation_categories',
            $newId
        );

        set_flash('success', 'Violation category "' . $data['violation_name'] . '" created.');

        if ($data['is_public'] === 0 && $data['public_description'] !== '') {
            set_flash('info',
                'Public wording saved but the category is not yet published. '
                . 'Enable "Show on public site" once the office approves it.');
        }

        redirect(url('admin/violation_categories.php'));
    }
} catch (Throwable $e) {
    error_log('[TrafStat][save_violation_category] ' . $e->getMessage());
    set_flash('error', 'Could not save the violation category. Please try again.');
    redirect(url('admin/violation_categories.php'));
}