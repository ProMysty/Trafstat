<?php
/**
 * admin/apprehensions.php
 * List + create + edit apprehension records.
 * Archive goes through actions/archive_record.php (entity=apprehension).
 */
require_once __DIR__ . '/../includes/init.php';
$admin = require_admin_ready();

$action = $_GET['action'] ?? 'list';

/* ================================================================== */
/* FORM (new / edit)                                                   */
/* ================================================================== */
if ($action === 'new' || $action === 'edit') {

    $editId     = $action === 'edit' ? (int) ($_GET['id'] ?? 0) : null;
    $editRecord = null;

    if ($editId !== null) {
        if ($editId <= 0) {
            set_flash('error', 'Invalid apprehension reference.');
            redirect(url('admin/apprehensions.php'));
        }
        $editRecord = db_one('SELECT * FROM apprehension_records WHERE apprehension_id = ?', [$editId]);
        if ($editRecord === null) {
            set_flash('error', 'That apprehension record no longer exists.');
            redirect(url('admin/apprehensions.php'));
        }
    }

    $formState = take_form_state();
    if (($action === 'new'  && $formState['edit_id'] !== null) ||
        ($action === 'edit' && $formState['edit_id'] !== $editId)) {
        $formState['errors'] = [];
        $formState['old']    = [];
    }
    $old = $formState['old'];

    $values = [
        'violator_id'       => $old['violator_id']       ?? ($editRecord['violator_id']       ?? null),
        'vehicle_id'        => $old['vehicle_id']        ?? ($editRecord['vehicle_id']        ?? null),
        'category_id'       => $old['category_id']       ?? ($editRecord['category_id']       ?? null),
        'apprehension_date' => $old['apprehension_date'] ?? ($editRecord['apprehension_date'] ?? date('Y-m-d')),
        'apprehension_time' => $old['apprehension_time'] ?? ($editRecord['apprehension_time'] ?? ''),
        'location_text'     => $old['location_text']     ?? ($editRecord['location_text']     ?? ''),
        'latitude'          => $old['latitude']          ?? ($editRecord['latitude']          ?? ''),
        'longitude'         => $old['longitude']         ?? ($editRecord['longitude']         ?? ''),
        'remarks'           => $old['remarks']           ?? ($editRecord['remarks']           ?? ''),
    ];

    $currentViolator = $values['violator_id'] !== null ? (int) $values['violator_id'] : 0;
    $currentCategory = $values['category_id'] !== null ? (int) $values['category_id'] : 0;
    $currentVehicle  = $values['vehicle_id']  !== null ? (int) $values['vehicle_id']  : 0;

    $violators = db_all(
        "SELECT violator_id, violator_code, first_name, last_name, record_status
           FROM traffic_violators
          WHERE record_status = 'active' OR violator_id = :current
       ORDER BY last_name, first_name",
        [':current' => $currentViolator]
    );

    $categories = db_all(
        "SELECT category_id, violation_name, status
           FROM violation_categories
          WHERE status = 'active' OR category_id = :current
       ORDER BY violation_name",
        [':current' => $currentCategory]
    );

    $vehicles = db_all(
        "SELECT v.vehicle_id, v.plate_number, v.vehicle_type,
                v.violator_id AS vehicle_violator_id,
                tv.violator_code, tv.first_name, tv.last_name
           FROM vehicles v
      LEFT JOIN traffic_violators tv ON tv.violator_id = v.violator_id
          WHERE v.record_status = 'active' OR v.vehicle_id = :current
       ORDER BY v.plate_number",
        [':current' => $currentVehicle]
    );

    $pageTitle   = $editRecord ? 'Edit Apprehension' : 'New Apprehension';
    $currentPage = 'apprehensions';
    require __DIR__ . '/../includes/header.php';
    ?>

    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h2 class="section-title mb-1">
                        <?= $editRecord ? 'Edit Apprehension' : 'New Apprehension' ?>
                    </h2>
                    <?php if ($editRecord): ?>
                        <p class="text-muted-sm mb-0">
                            Reference: <code><?= e($editRecord['reference_no']) ?></code>
                            &middot; Recorded <?= e(format_datetime($editRecord['created_at'])) ?>
                            <?php if ($editRecord['record_status'] === 'archived'): ?>
                                &middot; <span class="badge bg-secondary">Archived</span>
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <p class="text-muted-sm mb-0">
                            A unique reference (APP-YYYY-NNNNN) is generated on save.
                            A recorded apprehension is not a legal finding of guilt.
                        </p>
                    <?php endif; ?>
                </div>
                <a href="<?= e(url('admin/apprehensions.php')) ?>" class="btn btn-outline-secondary btn-sm">
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
                    <form method="post" action="<?= e(url('actions/save_apprehension.php')) ?>" novalidate>
                        <?= csrf_field() ?>
                        <?php if ($editRecord): ?>
                            <input type="hidden" name="apprehension_id" value="<?= (int) $editRecord['apprehension_id'] ?>">
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="violator_id" class="form-label">
                                    Violator <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="violator_id" name="violator_id" required>
                                    <option value="">— Select violator —</option>
                                    <?php foreach ($violators as $v):
                                        $label = $v['violator_code'] . ' — '
                                               . trim($v['first_name'] . ' ' . $v['last_name'])
                                               . ($v['record_status'] === 'archived' ? ' (archived)' : '');
                                    ?>
                                        <option value="<?= (int) $v['violator_id'] ?>"
                                            <?= $currentViolator === (int) $v['violator_id'] ? 'selected' : '' ?>>
                                            <?= e($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="vehicle_id" class="form-label">Vehicle</label>
                                <select class="form-select" id="vehicle_id" name="vehicle_id">
                                    <option value="">— None —</option>
                                    <?php foreach ($vehicles as $v):
                                        $owner = $v['violator_code'] !== null
                                            ? ' (' . trim($v['first_name'] . ' ' . $v['last_name']) . ')'
                                            : ' (unassigned)';
                                        $label = $v['plate_number']
                                               . ($v['vehicle_type'] ? ' · ' . $v['vehicle_type'] : '')
                                               . $owner;
                                    ?>
                                        <option value="<?= (int) $v['vehicle_id'] ?>"
                                                data-violator-id="<?= $v['vehicle_violator_id'] !== null ? (int) $v['vehicle_violator_id'] : '' ?>"
                                            <?= $currentVehicle === (int) $v['vehicle_id'] ? 'selected' : '' ?>>
                                            <?= e($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Only vehicles belonging to the selected violator are shown.</div>
                            </div>

                            <div class="col-12">
                                <label for="category_id" class="form-label">
                                    Violation category <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">— Select category —</option>
                                    <?php foreach ($categories as $c):
                                        $label = $c['violation_name']
                                               . ($c['status'] === 'inactive' ? ' (inactive)' : '');
                                    ?>
                                        <option value="<?= (int) $c['category_id'] ?>"
                                            <?= $currentCategory === (int) $c['category_id'] ? 'selected' : '' ?>>
                                            <?= e($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="apprehension_date" class="form-label">
                                    Date <span class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control" id="apprehension_date"
                                       name="apprehension_date"
                                       value="<?= e($values['apprehension_date']) ?>"
                                       max="<?= e(date('Y-m-d')) ?>" required>
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="apprehension_time" class="form-label">Time</label>
                                <input type="time" class="form-control" id="apprehension_time"
                                       name="apprehension_time"
                                       value="<?= e($values['apprehension_time']) ?>">
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="location_text" class="form-label">
                                    Location <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="location_text"
                                       name="location_text"
                                       value="<?= e($values['location_text']) ?>"
                                       maxlength="255" required
                                       placeholder="Street / barangay, Pagadian City">
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="latitude" class="form-label">Latitude</label>
                                <input type="text" class="form-control" id="latitude" name="latitude"
                                       value="<?= e($values['latitude']) ?>"
                                       placeholder="e.g. 7.8257000">
                                <div class="form-text">Optional. Leave blank if unknown.</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="longitude" class="form-label">Longitude</label>
                                <input type="text" class="form-control" id="longitude" name="longitude"
                                       value="<?= e($values['longitude']) ?>"
                                       placeholder="e.g. 123.4370000">
                                <div class="form-text">Optional. Both coordinates or neither.</div>
                            </div>

                            <div class="col-12">
                                <label for="remarks" class="form-label">General remarks</label>
                                <textarea class="form-control" id="remarks" name="remarks"
                                          rows="3" maxlength="2000"
                                          placeholder="Neutral notes about the recording. No legal conclusions."><?= e($values['remarks']) ?></textarea>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2 me-1"></i>
                                <?= $editRecord ? 'Save changes' : 'Record apprehension' ?>
                            </button>
                            <a href="<?= e(url('admin/apprehensions.php')) ?>" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script>
    (function () {
        var violatorSel = document.getElementById('violator_id');
        var vehicleSel  = document.getElementById('vehicle_id');
        if (!violatorSel || !vehicleSel) return;

        var allOptions = Array.from(vehicleSel.querySelectorAll('option'));
        var initialValue = vehicleSel.value;

        function filterVehicles() {
            var violatorId = violatorSel.value;
            var keepValue  = vehicleSel.value || initialValue;

            vehicleSel.innerHTML = '';
            vehicleSel.appendChild(allOptions[0]);

            allOptions.slice(1).forEach(function (opt) {
                var belongsTo = opt.getAttribute('data-violator-id') || '';
                var isCurrent = opt.value === keepValue;
                if (!violatorId || belongsTo === '' || belongsTo === violatorId || isCurrent) {
                    vehicleSel.appendChild(opt);
                }
            });

            if (keepValue && vehicleSel.querySelector('option[value="' + keepValue + '"]')) {
                vehicleSel.value = keepValue;
            }
        }

        violatorSel.addEventListener('change', function () { initialValue = ''; filterVehicles(); });
        filterVehicles();
    })();
    </script>

    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

/* ================================================================== */
/* LIST                                                                */
/* ================================================================== */
$q          = trim($_GET['q'] ?? '');
$status     = $_GET['status']    ?? 'active';
$categoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to']   ?? '');

if (!in_array($status, ['active', 'archived', 'all'], true)) {
    $status = 'active';
}
if ($dateFrom !== '' && !is_valid_date($dateFrom)) { $dateFrom = ''; }
if ($dateTo   !== '' && !is_valid_date($dateTo))   { $dateTo   = ''; }

$perPage = 25;
$page    = max(1, (int) ($_GET['page'] ?? 1));

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = "(a.reference_no LIKE :q
                 OR a.location_text LIKE :q
                 OR tv.violator_code LIKE :q
                 OR tv.first_name LIKE :q
                 OR tv.last_name LIKE :q
                 OR veh.plate_number LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
if ($status !== 'all') {
    $where[] = 'a.record_status = :status';
    $params[':status'] = $status;
}
if ($categoryId > 0) {
    $where[] = 'a.category_id = :category_id';
    $params[':category_id'] = $categoryId;
}
if ($dateFrom !== '') {
    $where[] = 'a.apprehension_date >= :date_from';
    $params[':date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 'a.apprehension_date <= :date_to';
    $params[':date_to'] = $dateTo;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$baseFrom = "FROM apprehension_records a
       INNER JOIN traffic_violators  tv  ON tv.violator_id  = a.violator_id
       INNER JOIN violation_categories vc ON vc.category_id = a.category_id
  LEFT JOIN vehicles veh ON veh.vehicle_id = a.vehicle_id";

$total      = (int) db_value("SELECT COUNT(*) $baseFrom $whereSql", $params);
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$apprehensions = db_all(
    "SELECT a.*,
            tv.violator_code, tv.first_name, tv.last_name,
            veh.plate_number,
            vc.violation_name
       $baseFrom
       $whereSql
   ORDER BY a.apprehension_date DESC, a.apprehension_id DESC
      LIMIT $perPage OFFSET $offset",
    $params
);

$categoriesForFilter = db_all(
    'SELECT category_id, violation_name FROM violation_categories ORDER BY violation_name'
);

$pageTitle   = 'Apprehensions';
$currentPage = 'apprehensions';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h2 class="section-title mb-1">Apprehension Records</h2>
        <p class="text-muted-sm mb-0">
            <?= number_format($total) ?> record<?= $total === 1 ? '' : 's' ?>
            &middot; page <?= $page ?> of <?= $totalPages ?>
        </p>
    </div>
    <a href="<?= e(url('admin/apprehensions.php?action=new')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Record apprehension
    </a>
</div>

<form method="get" class="card mb-3">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-12 col-md-4">
                <input type="text" name="q" class="form-control"
                       placeholder="Search by reference, name, plate or location"
                       value="<?= e($q) ?>">
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select">
                    <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
                    <option value="all"      <?= $status === 'all'      ? 'selected' : '' ?>>All</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
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
            <div class="col-6 col-md-2">
                <input type="date" name="date_from" class="form-control"
                       value="<?= e($dateFrom) ?>" placeholder="From">
            </div>
            <div class="col-6 col-md-2">
                <input type="date" name="date_to" class="form-control"
                       value="<?= e($dateTo) ?>" placeholder="To">
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search me-1"></i> Search
                </button>
                <?php if ($q !== '' || $status !== 'active' || $categoryId > 0 || $dateFrom !== '' || $dateTo !== ''): ?>
                    <a href="<?= e(url('admin/apprehensions.php')) ?>" class="btn btn-outline-secondary">
                        Clear
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Date</th>
                    <th>Violator</th>
                    <th>Vehicle</th>
                    <th>Category</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($apprehensions)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted-sm py-4">
                            No apprehension records match your filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($apprehensions as $a):
                        $isArchived = ($a['record_status'] === 'archived');
                        $violatorLabel = $a['violator_code'] . ' — '
                                       . trim($a['first_name'] . ' ' . $a['last_name']);
                    ?>
                    <tr>
                        <td><code><?= e($a['reference_no']) ?></code></td>
                        <td>
                            <?= e(format_date($a['apprehension_date'])) ?>
                            <?php if ($a['apprehension_time'] !== null && $a['apprehension_time'] !== ''): ?>
                                <div class="text-muted-sm">
                                    <?= e(date('g:i A', strtotime($a['apprehension_time']))) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($violatorLabel) ?></td>
                        <td><?= $a['plate_number'] !== null ? '<code>' . e($a['plate_number']) . '</code>' : '—' ?></td>
                        <td><?= e($a['violation_name']) ?></td>
                        <td><?= e($a['location_text']) ?></td>
                        <td>
                            <?php if ($isArchived): ?>
                                <span class="badge bg-secondary">Archived</span>
                            <?php else: ?>
                                <span class="badge bg-success">Active</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a href="<?= e(url('admin/apprehensions.php?action=edit&id=' . (int) $a['apprehension_id'])) ?>"
                                   class="btn btn-sm btn-outline-primary" title="Edit"
                                   aria-label="Edit <?= e($a['reference_no']) ?>">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($isArchived): ?>
                                    <form method="post" action="<?= e(url('actions/archive_record.php')) ?>"
                                          class="d-inline"
                                          data-confirm="Restore apprehension <?= e($a['reference_no']) ?>?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="entity" value="apprehension">
                                        <input type="hidden" name="id" value="<?= (int) $a['apprehension_id'] ?>">
                                        <input type="hidden" name="action" value="restore">
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Restore">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?= e(url('actions/archive_record.php')) ?>"
                                          class="d-inline"
                                          data-confirm="Archive apprehension <?= e($a['reference_no']) ?>? History is preserved.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="entity" value="apprehension">
                                        <input type="hidden" name="id" value="<?= (int) $a['apprehension_id'] ?>">
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
    <nav class="mt-3" aria-label="Apprehensions pagination">
        <ul class="pagination justify-content-center flex-wrap">
            <?php
            $pageParams = [
                'q'           => $q,
                'status'      => $status,
                'category_id' => $categoryId > 0 ? $categoryId : '',
                'date_from'   => $dateFrom,
                'date_to'     => $dateTo,
            ];
            for ($i = 1; $i <= $totalPages; $i++):
                $pageParams['page'] = $i;
            ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= e(url('admin/apprehensions.php?' . http_build_query(array_filter($pageParams, fn($v) => $v !== '')))) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<div class="alert alert-info mt-3 mb-0">
    <i class="bi bi-info-circle me-1"></i>
    A recorded apprehension is <strong>not</strong> a legal finding of guilt.
    TrafStat does not issue citations, determine penalties, or adjudicate offences.
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>