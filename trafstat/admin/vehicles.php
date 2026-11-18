<?php
/**
 * admin/vehicles.php
 * List + create + edit vehicles.
 *
 * Supports ?violator_id=N to filter by a specific violator
 * (used from the violator edit screen).
 */
require_once __DIR__ . '/../includes/init.php';
$admin = require_admin_ready();

$action             = $_GET['action'] ?? 'list';
$violatorFilterId   = isset($_GET['violator_id']) ? (int) $_GET['violator_id'] : null;
if ($violatorFilterId !== null && $violatorFilterId <= 0) {
    $violatorFilterId = null;
}

$allowedTypes = ['Motorcycle', 'Car', 'Van', 'Truck', 'Tricycle', 'Jeepney', 'Bus', 'Other'];

/* ================================================================== */
/* FORM VIEW                                                           */
/* ================================================================== */
if ($action === 'new' || $action === 'edit') {

    $editId     = $action === 'edit' ? (int) ($_GET['id'] ?? 0) : null;
    $editRecord = null;

    if ($editId !== null) {
        if ($editId <= 0) {
            set_flash('error', 'Invalid vehicle reference.');
            redirect(url('admin/vehicles.php'));
        }
        $editRecord = db_one('SELECT * FROM vehicles WHERE vehicle_id = ?', [$editId]);
        if ($editRecord === null) {
            set_flash('error', 'That vehicle record no longer exists.');
            redirect(url('admin/vehicles.php'));
        }
    }

    $formState = take_form_state();
    if (($action === 'new'  && $formState['edit_id'] !== null) ||
        ($action === 'edit' && $formState['edit_id'] !== $editId)) {
        $formState['errors'] = [];
        $formState['old']    = [];
    }
    $old = $formState['old'];

    // Pre-fill violator from ?violator_id= when creating a new vehicle.
    $presetViolatorId = $action === 'new' && $violatorFilterId !== null
        ? $violatorFilterId
        : null;

    $values = [
        'plate_number'      => $old['plate_number']      ?? ($editRecord['plate_number']      ?? ''),
        'vehicle_type'      => $old['vehicle_type']      ?? ($editRecord['vehicle_type']      ?? ''),
        'vehicle_make'      => $old['vehicle_make']      ?? ($editRecord['vehicle_make']      ?? ''),
        'vehicle_model'     => $old['vehicle_model']     ?? ($editRecord['vehicle_model']     ?? ''),
        'vehicle_color'     => $old['vehicle_color']     ?? ($editRecord['vehicle_color']     ?? ''),
        'registration_info' => $old['registration_info'] ?? ($editRecord['registration_info'] ?? ''),
        'violator_id'       => $old['violator_id']
                                ?? ($editRecord['violator_id']
                                    ?? $presetViolatorId),
    ];

    // Violator dropdown: active violators + the current one (even if archived).
    $currentViolatorId = $values['violator_id'] !== null ? (int) $values['violator_id'] : 0;
    $violators = db_all(
        "SELECT violator_id, violator_code, first_name, last_name, record_status
           FROM traffic_violators
          WHERE record_status = 'active' OR violator_id = :current
       ORDER BY last_name, first_name",
        [':current' => $currentViolatorId]
    );

    $pageTitle   = $editRecord ? 'Edit Vehicle' : 'New Vehicle';
    $currentPage = 'vehicles';
    require __DIR__ . '/../includes/header.php';
    ?>

    <div class="row justify-content-center">
        <div class="col-12 col-xl-9">

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h2 class="section-title mb-1">
                        <?= $editRecord ? 'Edit Vehicle' : 'New Vehicle' ?>
                    </h2>
                    <?php if ($editRecord): ?>
                        <p class="text-muted-sm mb-0">
                            Plate: <code><?= e($editRecord['plate_number']) ?></code>
                            &middot; Added <?= e(format_datetime($editRecord['created_at'])) ?>
                            <?php if ($editRecord['record_status'] === 'archived'): ?>
                                &middot; <span class="badge bg-secondary">Archived</span>
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <p class="text-muted-sm mb-0">
                            Plate number must be unique across active and archived records.
                        </p>
                    <?php endif; ?>
                </div>
                <a href="<?= e(url('admin/vehicles.php')) ?>" class="btn btn-outline-secondary btn-sm">
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
                    <form method="post" action="<?= e(url('actions/save_vehicle.php')) ?>" novalidate>
                        <?= csrf_field() ?>
                        <?php if ($editRecord): ?>
                            <input type="hidden" name="vehicle_id" value="<?= (int) $editRecord['vehicle_id'] ?>">
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label for="plate_number" class="form-label">Plate number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control text-uppercase"
                                       id="plate_number" name="plate_number"
                                       value="<?= e($values['plate_number']) ?>"
                                       maxlength="20" required autofocus
                                       placeholder="ABC 1234">
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="vehicle_type" class="form-label">Vehicle type</label>
                                <select class="form-select" id="vehicle_type" name="vehicle_type">
                                    <option value="">— Select —</option>
                                    <?php foreach ($allowedTypes as $type): ?>
                                        <option value="<?= e($type) ?>"
                                            <?= $values['vehicle_type'] === $type ? 'selected' : '' ?>>
                                            <?= e($type) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="vehicle_color" class="form-label">Color</label>
                                <input type="text" class="form-control" id="vehicle_color" name="vehicle_color"
                                       value="<?= e($values['vehicle_color']) ?>" maxlength="30">
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="vehicle_make" class="form-label">Make</label>
                                <input type="text" class="form-control" id="vehicle_make" name="vehicle_make"
                                       value="<?= e($values['vehicle_make']) ?>" maxlength="50"
                                       placeholder="Honda, Toyota, Yamaha…">
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="vehicle_model" class="form-label">Model</label>
                                <input type="text" class="form-control" id="vehicle_model" name="vehicle_model"
                                       value="<?= e($values['vehicle_model']) ?>" maxlength="50"
                                       placeholder="Click 125, Vios, Mio…">
                            </div>

                            <div class="col-12">
                                <label for="registration_info" class="form-label">Registration information</label>
                                <input type="text" class="form-control" id="registration_info" name="registration_info"
                                       value="<?= e($values['registration_info']) ?>" maxlength="150"
                                       placeholder="Optional. Only if required by the office.">
                            </div>

                            <div class="col-12">
                                <label for="violator_id" class="form-label">Assigned violator</label>
                                <select class="form-select" id="violator_id" name="violator_id">
                                    <option value="">— Unassigned —</option>
                                    <?php foreach ($violators as $v):
                                        $label = $v['violator_code'] . ' — '
                                               . trim($v['first_name'] . ' ' . $v['last_name'])
                                               . ($v['record_status'] === 'archived' ? ' (archived)' : '');
                                        $selected = ((int) $values['violator_id']) === (int) $v['violator_id'];
                                    ?>
                                        <option value="<?= (int) $v['violator_id'] ?>" <?= $selected ? 'selected' : '' ?>>
                                            <?= e($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($violators)): ?>
                                    <div class="form-text">
                                        No violators exist yet.
                                        <a href="<?= e(url('admin/violators.php?action=new')) ?>">Create one first</a>.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2 me-1"></i>
                                <?= $editRecord ? 'Save changes' : 'Create vehicle' ?>
                            </button>
                            <a href="<?= e(url('admin/vehicles.php')) ?>" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>

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
    $where[] = "(plate_number LIKE :q
                 OR vehicle_make LIKE :q
                 OR vehicle_model LIKE :q
                 OR CONCAT_WS(' ', vehicle_make, vehicle_model) LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
if ($status !== 'all') {
    $where[] = 'v.record_status = :status';
    $params[':status'] = $status;
}
if ($violatorFilterId !== null) {
    $where[] = 'v.violator_id = :violator_id';
    $params[':violator_id'] = $violatorFilterId;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total      = (int) db_value("SELECT COUNT(*) FROM vehicles v $whereSql", $params);
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$vehicles = db_all(
    "SELECT v.*,
            tv.violator_code, tv.first_name, tv.last_name
       FROM vehicles v
  LEFT JOIN traffic_violators tv ON tv.violator_id = v.violator_id
       $whereSql
   ORDER BY v.created_at DESC, v.vehicle_id DESC
      LIMIT $perPage OFFSET $offset",
    $params
);

$violatorBanner = null;
if ($violatorFilterId !== null) {
    $violatorBanner = db_one(
        'SELECT violator_id, violator_code, first_name, last_name
           FROM traffic_violators WHERE violator_id = ?',
        [$violatorFilterId]
    );
}

$pageTitle   = 'Vehicles';
$currentPage = 'vehicles';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h2 class="section-title mb-1">Vehicles</h2>
        <p class="text-muted-sm mb-0">
            <?= number_format($total) ?> record<?= $total === 1 ? '' : 's' ?>
            &middot; page <?= $page ?> of <?= $totalPages ?>
        </p>
    </div>
    <a href="<?= e(url('admin/vehicles.php?action=new')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New vehicle
    </a>
</div>

<?php if ($violatorBanner !== null): ?>
    <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span>
            Showing vehicles for
            <strong><?= e($violatorBanner['violator_code']) ?></strong>
            &mdash; <?= e(trim($violatorBanner['first_name'] . ' ' . $violatorBanner['last_name'])) ?>
        </span>
        <a href="<?= e(url('admin/vehicles.php')) ?>" class="btn btn-sm btn-outline-secondary">Clear filter</a>
    </div>
<?php endif; ?>

<form method="get" class="row g-2 mb-3">
    <?php if ($violatorFilterId !== null): ?>
        <input type="hidden" name="violator_id" value="<?= (int) $violatorFilterId ?>">
    <?php endif; ?>
    <div class="col-12 col-md-6">
        <input type="text" name="q" class="form-control"
               placeholder="Search by plate, make or model"
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
            <a href="<?= e(url('admin/vehicles.php'
                . ($violatorFilterId !== null ? '?violator_id=' . $violatorFilterId : ''))) ?>"
               class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Plate</th>
                    <th>Type</th>
                    <th>Make / Model</th>
                    <th>Color</th>
                    <th>Violator</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vehicles)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted-sm py-4">
                            No vehicle records match your search.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($vehicles as $v):
                        $isArchived = ($v['record_status'] === 'archived');
                        $violatorLabel = '—';
                        if ($v['violator_code'] !== null) {
                            $violatorLabel = $v['violator_code'] . ' — '
                                           . trim($v['first_name'] . ' ' . $v['last_name']);
                        }
                        $makeModel = trim(($v['vehicle_make'] ?? '') . ' ' . ($v['vehicle_model'] ?? ''));
                    ?>
                    <tr>
                        <td><code><?= e($v['plate_number']) ?></code></td>
                        <td><?= e($v['vehicle_type'] ?? '—') ?></td>
                        <td><?= $makeModel !== '' ? e($makeModel) : '—' ?></td>
                        <td><?= e($v['vehicle_color'] ?? '—') ?></td>
                        <td><?= e($violatorLabel) ?></td>
                        <td>
                            <?php if ($isArchived): ?>
                                <span class="badge bg-secondary">Archived</span>
                            <?php else: ?>
                                <span class="badge bg-success">Active</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a href="<?= e(url('admin/vehicles.php?action=edit&id=' . (int) $v['vehicle_id'])) ?>"
                                   class="btn btn-sm btn-outline-primary" title="Edit"
                                   aria-label="Edit <?= e($v['plate_number']) ?>">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($isArchived): ?>
                                    <form method="post" action="<?= e(url('actions/archive_record.php')) ?>" class="d-inline"
                                          data-confirm="Restore vehicle <?= e($v['plate_number']) ?>?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="entity" value="vehicle">
                                        <input type="hidden" name="id" value="<?= (int) $v['vehicle_id'] ?>">
                                        <input type="hidden" name="action" value="restore">
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Restore">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?= e(url('actions/archive_record.php')) ?>" class="d-inline"
                                          data-confirm="Archive vehicle <?= e($v['plate_number']) ?>?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="entity" value="vehicle">
                                        <input type="hidden" name="id" value="<?= (int) $v['vehicle_id'] ?>">
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
    <nav class="mt-3" aria-label="Vehicles pagination">
        <ul class="pagination justify-content-center flex-wrap">
            <?php
            $pageParams = ['q' => $q, 'status' => $status];
            if ($violatorFilterId !== null) {
                $pageParams['violator_id'] = $violatorFilterId;
            }
            for ($i = 1; $i <= $totalPages; $i++):
                $pageParams['page'] = $i;
            ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= e(url('admin/vehicles.php?' . http_build_query($pageParams))) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>