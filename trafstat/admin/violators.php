<?php
/**
 * admin/violators.php
 * List + create + edit traffic violators.
 *
 * Modes (via ?action=):
 *   (default)         list with search, status filter, pagination
 *   new               creation form
 *   edit&id=<int>     edit form
 *
 * Archive/restore is a POST to actions/archive_record.php.
 */
require_once __DIR__ . '/../includes/init.php';
$admin = require_admin_ready();

$action = $_GET['action'] ?? 'list';

/* ================================================================== */
/* FORM VIEW                                                           */
/* ================================================================== */
if ($action === 'new' || $action === 'edit') {

    $editId     = $action === 'edit' ? (int) ($_GET['id'] ?? 0) : null;
    $editRecord = null;

    if ($editId !== null) {
        if ($editId <= 0) {
            set_flash('error', 'Invalid violator reference.');
            redirect(url('admin/violators.php'));
        }
        $editRecord = db_one(
            'SELECT * FROM traffic_violators WHERE violator_id = ?',
            [$editId]
        );
        if ($editRecord === null) {
            set_flash('error', 'That violator record no longer exists.');
            redirect(url('admin/violators.php'));
        }
    }

    $formState = take_form_state();

    // Clear stale errors if they belong to a different form target.
    if (($action === 'new'  && $formState['edit_id'] !== null) ||
        ($action === 'edit' && $formState['edit_id'] !== $editId)) {
        $formState['errors'] = [];
        $formState['old']    = [];
    }

    $old = $formState['old'];

    $values = [
        'first_name'     => $old['first_name']     ?? ($editRecord['first_name']     ?? ''),
        'middle_name'    => $old['middle_name']    ?? ($editRecord['middle_name']    ?? ''),
        'last_name'      => $old['last_name']      ?? ($editRecord['last_name']      ?? ''),
        'suffix'         => $old['suffix']         ?? ($editRecord['suffix']         ?? ''),
        'address'        => $old['address']        ?? ($editRecord['address']        ?? ''),
        'contact_number' => $old['contact_number'] ?? ($editRecord['contact_number'] ?? ''),
    ];

    $relatedVehicles = [];
    if ($editRecord !== null) {
        $relatedVehicles = db_all(
            "SELECT vehicle_id, plate_number, vehicle_type, vehicle_make, vehicle_model
               FROM vehicles
              WHERE violator_id = ? AND record_status = 'active'
           ORDER BY plate_number",
            [$editId]
        );
    }

    $pageTitle   = $editRecord ? 'Edit Violator' : 'New Violator';
    $currentPage = 'violators';
    require __DIR__ . '/../includes/header.php';
    ?>

    <div class="row justify-content-center">
        <div class="col-12 col-xl-9">

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h2 class="section-title mb-1">
                        <?= $editRecord ? 'Edit Violator' : 'New Violator' ?>
                    </h2>
                    <?php if ($editRecord): ?>
                        <p class="text-muted-sm mb-0">
                            Code: <code><?= e($editRecord['violator_code']) ?></code>
                            &middot; Created <?= e(format_datetime($editRecord['created_at'])) ?>
                            <?php if ($editRecord['record_status'] === 'archived'): ?>
                                &middot; <span class="badge bg-secondary">Archived</span>
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <p class="text-muted-sm mb-0">
                            A unique violator code (VIO-YYYY-NNNNN) is generated automatically on save.
                        </p>
                    <?php endif; ?>
                </div>
                <a href="<?= e(url('admin/violators.php')) ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to list
                </a>
            </div>

            <?php if (!empty($formState['errors'])): ?>
                <div class="alert alert-danger">
                    <strong>Please correct the following:</strong>
                    <ul class="mb-0 mt-1">
                        <?php foreach ($formState['errors'] as $msg): ?>
                            <li><?= e($msg) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body p-4">
                    <form method="post" action="<?= e(url('actions/save_violator.php')) ?>" novalidate>
                        <?= csrf_field() ?>
                        <?php if ($editRecord): ?>
                            <input type="hidden" name="violator_id" value="<?= (int) $editRecord['violator_id'] ?>">
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label for="first_name" class="form-label">First name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name"
                                       value="<?= e($values['first_name']) ?>"
                                       maxlength="60" required autofocus>
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="middle_name" class="form-label">Middle name</label>
                                <input type="text" class="form-control" id="middle_name" name="middle_name"
                                       value="<?= e($values['middle_name']) ?>" maxlength="60">
                            </div>
                            <div class="col-8 col-md-3">
                                <label for="last_name" class="form-label">Last name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name"
                                       value="<?= e($values['last_name']) ?>"
                                       maxlength="60" required>
                            </div>
                            <div class="col-4 col-md-1">
                                <label for="suffix" class="form-label">Suffix</label>
                                <input type="text" class="form-control" id="suffix" name="suffix"
                                       value="<?= e($values['suffix']) ?>" maxlength="10"
                                       placeholder="Jr., III">
                            </div>

                            <div class="col-12">
                                <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="address" name="address"
                                       value="<?= e($values['address']) ?>"
                                       maxlength="255" required
                                       placeholder="House / street / barangay, Pagadian City">
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="contact_number" class="form-label">Contact number</label>
                                <input type="tel" class="form-control" id="contact_number" name="contact_number"
                                       value="<?= e($values['contact_number']) ?>"
                                       maxlength="20"
                                       placeholder="0917-123-4567">
                                <div class="form-text">
                                    Only if operationally necessary and authorized by the office.
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2 me-1"></i>
                                <?= $editRecord ? 'Save changes' : 'Create violator' ?>
                            </button>
                            <a href="<?= e(url('admin/violators.php')) ?>" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($editRecord !== null): ?>
                <div class="card mt-3">
                    <div class="card-body p-4">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                            <h3 class="section-title m-0">Vehicles</h3>
                            <a href="<?= e(url('admin/vehicles.php?action=new&violator_id=' . (int) $editRecord['violator_id'])) ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-plus-lg me-1"></i> Add vehicle
                            </a>
                        </div>
                        <?php if (empty($relatedVehicles)): ?>
                            <p class="text-muted-sm mb-0">No active vehicles linked to this violator yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Plate</th>
                                            <th>Type</th>
                                            <th>Make / Model</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($relatedVehicles as $v): ?>
                                            <tr>
                                                <td><code><?= e($v['plate_number']) ?></code></td>
                                                <td><?= e($v['vehicle_type'] ?? '—') ?></td>
                                                <td>
                                                    <?= e(trim(($v['vehicle_make'] ?? '') . ' ' . ($v['vehicle_model'] ?? ''))) ?: '—' ?>
                                                </td>
                                                <td class="text-end">
                                                    <a href="<?= e(url('admin/vehicles.php?action=edit&id=' . (int) $v['vehicle_id'])) ?>"
                                                       class="btn btn-sm btn-outline-primary">Edit</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

/* ================================================================== */
/* LIST VIEW                                                           */
/* ================================================================== */

$q      = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'active';
if (!in_array($status, ['active', 'archived', 'all'], true)) {
    $status = 'active';
}

$perPage = 20;
$page    = max(1, (int) ($_GET['page'] ?? 1));

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = "(violator_code LIKE :q
                 OR first_name LIKE :q
                 OR last_name  LIKE :q
                 OR CONCAT_WS(' ', first_name, last_name) LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
if ($status !== 'all') {
    $where[] = 'record_status = :status';
    $params[':status'] = $status;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total      = (int) db_value("SELECT COUNT(*) FROM traffic_violators $whereSql", $params);
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// $perPage and $offset are hard-cast integers; safe to interpolate into LIMIT/OFFSET.
$violators = db_all(
    "SELECT v.*,
            (SELECT COUNT(*) FROM vehicles veh
              WHERE veh.violator_id = v.violator_id AND veh.record_status = 'active') AS vehicle_count,
            (SELECT COUNT(*) FROM apprehension_records ar
              WHERE ar.violator_id = v.violator_id AND ar.record_status = 'active') AS apprehension_count
       FROM traffic_violators v
       $whereSql
   ORDER BY v.created_at DESC, v.violator_id DESC
      LIMIT $perPage OFFSET $offset",
    $params
);

$pageTitle   = 'Traffic Violators';
$currentPage = 'violators';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h2 class="section-title mb-1">Traffic Violators</h2>
        <p class="text-muted-sm mb-0">
            <?= number_format($total) ?> record<?= $total === 1 ? '' : 's' ?>
            &middot; page <?= $page ?> of <?= $totalPages ?>
        </p>
    </div>
    <a href="<?= e(url('admin/violators.php?action=new')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New violator
    </a>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-12 col-md-6">
        <input type="text" name="q" class="form-control"
               placeholder="Search by code or name"
               value="<?= e($q) ?>">
    </div>
    <div class="col-8 col-md-3">
        <select name="status" class="form-select">
            <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>Active only</option>
            <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived only</option>
            <option value="all"      <?= $status === 'all'      ? 'selected' : '' ?>>All records</option>
        </select>
    </div>
    <div class="col-4 col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-fill">
            <i class="bi bi-search me-1"></i> Search
        </button>
        <?php if ($q !== '' || $status !== 'active'): ?>
            <a href="<?= e(url('admin/violators.php')) ?>" class="btn btn-outline-secondary">
                Clear
            </a>
        <?php endif; ?>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Address</th>
                    <th>Contact</th>
                    <th class="text-center">Vehicles</th>
                    <th class="text-center">Apprehensions</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($violators)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted-sm py-4">
                            No violator records match your search.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($violators as $v):
                        $fullName = trim(
                            $v['first_name'] . ' ' .
                            ($v['middle_name'] !== null && $v['middle_name'] !== ''
                                ? mb_substr($v['middle_name'], 0, 1) . '.' : '') .
                            ' ' . $v['last_name'] .
                            ($v['suffix'] !== null && $v['suffix'] !== ''
                                ? ' ' . $v['suffix'] : '')
                        );
                        $isArchived = ($v['record_status'] === 'archived');
                    ?>
                    <tr>
                        <td><code><?= e($v['violator_code']) ?></code></td>
                        <td>
                            <?= e($fullName) ?>
                            <div class="text-muted-sm">Since <?= e(format_date($v['created_at'])) ?></div>
                        </td>
                        <td><?= e($v['address']) ?></td>
                        <td><?= $v['contact_number'] ? e($v['contact_number']) : '—' ?></td>
                        <td class="text-center"><?= (int) $v['vehicle_count'] ?></td>
                        <td class="text-center"><?= (int) $v['apprehension_count'] ?></td>
                        <td>
                            <?php if ($isArchived): ?>
                                <span class="badge bg-secondary">Archived</span>
                            <?php else: ?>
                                <span class="badge bg-success">Active</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a href="<?= e(url('admin/violators.php?action=edit&id=' . (int) $v['violator_id'])) ?>"
                                   class="btn btn-sm btn-outline-primary" title="Edit" aria-label="Edit <?= e($v['violator_code']) ?>">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                
                                <?php if ($isArchived): ?>
                                    <a href="<?= e(url('admin/violation_history.php?violator_id=' . (int) $v['violator_id'])) ?>"
                                        class="btn btn-sm btn-outline-secondary" title="Violation history"
                                        aria-label="View history for <?= e($v['violator_code']) ?>">
                                            <i class="bi bi-clock-history"></i>
                                    </a>
                                    <form method="post" action="<?= e(url('actions/archive_record.php')) ?>" class="d-inline"
                                          data-confirm="Restore violator <?= e($v['violator_code']) ?>?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="entity" value="violator">
                                        <input type="hidden" name="id" value="<?= (int) $v['violator_id'] ?>">
                                        <input type="hidden" name="action" value="restore">
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Restore">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <?php
                                    $warning = (int) $v['apprehension_count'] > 0
                                        ? ' This violator has ' . (int) $v['apprehension_count'] . ' active apprehension record(s). They will be preserved.'
                                        : '';
                                    ?>
                                    <form method="post" action="<?= e(url('actions/archive_record.php')) ?>" class="d-inline"
                                          data-confirm="Archive violator <?= e($v['violator_code']) ?>?<?= e($warning) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="entity" value="violator">
                                        <input type="hidden" name="id" value="<?= (int) $v['violator_id'] ?>">
                                        <input type="hidden" name="action" value="archive">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Archive">
                                            <i class="bi bi-archive"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <nav class="mt-3" aria-label="Violators pagination">
        <ul class="pagination justify-content-center flex-wrap">
            <?php
            $pageParams = ['q' => $q, 'status' => $status];
            for ($i = 1; $i <= $totalPages; $i++):
                $pageParams['page'] = $i;
            ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= e(url('admin/violators.php?' . http_build_query($pageParams))) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>