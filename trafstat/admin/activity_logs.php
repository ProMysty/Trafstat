<?php
/**
 * admin/activity_logs.php
 * Read-only inspection of activity_logs, with filters and a
 * full-chain integrity verification.
 */
require_once __DIR__ . '/../includes/init.php';
$admin = require_admin_ready();

/* ================================================================== */
/* VERIFY ACTION                                                       */
/* ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify') {
    require_csrf();

    $result = verify_activity_log_chain();

    log_activity(
        (int) $admin['admin_id'],
        'ACTIVITY_LOG_VERIFIED',
        $result['ok']
            ? 'Activity log chain verified: ' . $result['total_checked'] . ' entries intact.'
            : 'Activity log chain integrity FAILED: ' . $result['reason'],
        'activity_logs',
        null,
        [
            'total_checked' => $result['total_checked'],
            'ok'            => $result['ok'],
        ]
    );

    $_SESSION['log_verify_result'] = $result;
    redirect(url('admin/activity_logs.php'));
}

/* ================================================================== */
/* FILTERS                                                             */
/* ================================================================== */
$dateFrom  = trim($_GET['date_from']  ?? '');
$dateTo    = trim($_GET['date_to']    ?? '');
$eventType = trim($_GET['event_type'] ?? '');
$adminId   = isset($_GET['admin_id']) ? (int) $_GET['admin_id'] : 0;
$q         = trim($_GET['q']          ?? '');

if ($dateFrom !== '' && !is_valid_date($dateFrom)) { $dateFrom = ''; }
if ($dateTo   !== '' && !is_valid_date($dateTo))   { $dateTo   = ''; }
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

$where  = [];
$params = [];

if ($dateFrom !== '') {
    $where[] = 'DATE(l.created_at) >= :date_from';
    $params[':date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 'DATE(l.created_at) <= :date_to';
    $params[':date_to'] = $dateTo;
}
if ($eventType !== '') {
    $where[] = 'l.event_type = :event_type';
    $params[':event_type'] = $eventType;
}
if ($adminId > 0) {
    $where[] = 'l.admin_id = :admin_id';
    $params[':admin_id'] = $adminId;
}
if ($q !== '') {
    $where[] = '(l.action_description LIKE :q OR l.ip_address LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* Distinct event types for the filter dropdown */
$eventTypes = db_all(
    'SELECT DISTINCT event_type FROM activity_logs ORDER BY event_type'
);

$adminList = db_all(
    'SELECT admin_id, full_name, username FROM administrators ORDER BY full_name'
);

/* ================================================================== */
/* PAGINATION                                                          */
/* ================================================================== */
$perPage = 50;
$page    = max(1, (int) ($_GET['page'] ?? 1));

$total = (int) db_value(
    "SELECT COUNT(*) FROM activity_logs l $whereSql",
    $params
);
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$logs = db_all(
    "SELECT l.*, a.full_name AS admin_name, a.username AS admin_username
       FROM activity_logs l
  LEFT JOIN administrators a ON a.admin_id = l.admin_id
       $whereSql
   ORDER BY l.log_id DESC
      LIMIT $perPage OFFSET $offset",
    $params
);

/* One-time verification result */
$verifyResult = $_SESSION['log_verify_result'] ?? null;
unset($_SESSION['log_verify_result']);

$pageTitle   = 'Activity Logs';
$currentPage = 'activity_logs';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h2 class="section-title mb-1">Activity Logs</h2>
        <p class="text-muted-sm mb-0">
            <?= number_format($total) ?> entr<?= $total === 1 ? 'y' : 'ies' ?>
            &middot; page <?= $page ?> of <?= $totalPages ?>
            &middot; append-only, hash-chained
        </p>
    </div>

    <form method="post" action="<?= e(url('admin/activity_logs.php')) ?>"
          data-confirm="Verify the entire activity log hash chain? This may take a moment on large logs.">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="verify">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-shield-check me-1"></i> Verify chain integrity
        </button>
    </form>
</div>

<?php if ($verifyResult !== null): ?>
    <?php if ($verifyResult['ok']): ?>
        <div class="alert alert-success">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-check-circle-fill"></i>
                <strong>Hash chain verified</strong>
            </div>
            <div>
                <?= number_format($verifyResult['total_checked']) ?> entries checked.
                No tampering detected.
            </div>
            <?php if (!empty($verifyResult['truncation_warning'])): ?>
                <div class="mt-2 text-muted-sm">
                    <i class="bi bi-info-circle me-1"></i>
                    <?= e($verifyResult['truncation_warning']) ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>Chain integrity failure</strong>
            </div>
            <div>
                <?= number_format($verifyResult['total_checked']) ?> entries checked.
                Chain broke at <code>log_id <?= (int) $verifyResult['first_broken_log_id'] ?></code>.
                <br>
                <small><?= e($verifyResult['reason']) ?></small>
            </div>
            <div class="mt-2 small">
                Investigate the log entry and all subsequent entries immediately.
                Notify the Data Protection Officer.
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Filters -->
<form method="get" class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label">From</label>
                <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Event type</label>
                <select name="event_type" class="form-select">
                    <option value="">All events</option>
                    <?php foreach ($eventTypes as $et): ?>
                        <option value="<?= e($et['event_type']) ?>"
                            <?= $eventType === $et['event_type'] ? 'selected' : '' ?>>
                            <?= e($et['event_type']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Administrator</label>
                <select name="admin_id" class="form-select">
                    <option value="0">All administrators</option>
                    <?php foreach ($adminList as $a): ?>
                        <option value="<?= (int) $a['admin_id'] ?>"
                            <?= $adminId === (int) $a['admin_id'] ? 'selected' : '' ?>>
                            <?= e($a['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">Search</label>
                <input type="text" name="q" class="form-control"
                       value="<?= e($q) ?>"
                       placeholder="Description or IP">
            </div>
            <div class="col-12 d-flex gap-2 mt-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel me-1"></i> Apply filters
                </button>
                <a href="<?= e(url('admin/activity_logs.php')) ?>" class="btn btn-outline-secondary">
                    Reset
                </a>
            </div>
        </div>
    </div>
</form>

<!-- Log entries -->
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Administrator</th>
                    <th>Event</th>
                    <th>Description</th>
                    <th>Related</th>
                    <th>IP</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted-sm py-4">
                            No log entries match your filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log):
                        $adminName = $log['admin_name'] !== null
                            ? $log['admin_name'] . ' (' . $log['admin_username'] . ')'
                            : 'System';
                    ?>
                    <tr>
                        <td class="text-nowrap">
                            <span class="text-muted-sm">
                                <?= e(format_datetime($log['created_at'])) ?>
                            </span>
                        </td>
                        <td>
                            <?= e($adminName) ?>
                            <div class="text-muted-sm">
                                ID <?= $log['admin_id'] !== null ? (int) $log['admin_id'] : '—' ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-secondary">
                                <?= e($log['event_type']) ?>
                            </span>
                        </td>
                        <td><?= e($log['action_description']) ?></td>
                        <td class="text-muted-sm">
                            <?php if ($log['related_table'] !== null): ?>
                                <?= e($log['related_table']) ?>
                                <?php if ($log['related_record_id'] !== null): ?>
                                    · #<?= (int) $log['related_record_id'] ?>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="text-muted-sm">
                            <?= e($log['ip_address'] ?? '—') ?>
                        </td>
                        <td class="text-end">
                            <?php if (!empty($log['details'])): ?>
                                <button class="btn btn-sm btn-outline-secondary"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#log-<?= (int) $log['log_id'] ?>"
                                        aria-expanded="false"
                                        aria-controls="log-<?= (int) $log['log_id'] ?>">
                                    <i class="bi bi-code-square"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (!empty($log['details'])): ?>
                        <tr class="collapse" id="log-<?= (int) $log['log_id'] ?>">
                            <td colspan="7" class="bg-light">
                                <div class="small">
                                    <div class="text-muted mb-1">Details:</div>
                                    <pre class="mb-2" style="white-space:pre-wrap;word-break:break-all;"><?php
                                        $decoded = json_decode($log['details'], true);
                                        echo e($decoded !== null
                                            ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                                            : $log['details']);
                                    ?></pre>
                                    <div class="text-muted mb-1">Hash verification data:</div>
                                    <div class="font-monospace small">
                                        <div><strong>previous_hash:</strong> <?= e($log['previous_hash']) ?></div>
                                        <div><strong>entry_hash:</strong> <?= e($log['entry_hash']) ?></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <nav class="mt-3" aria-label="Activity log pagination">
        <ul class="pagination justify-content-center flex-wrap">
            <?php
            $pageParams = [
                'date_from'  => $dateFrom,
                'date_to'    => $dateTo,
                'event_type' => $eventType,
                'admin_id'   => $adminId > 0 ? $adminId : '',
                'q'          => $q,
            ];
            for ($i = 1; $i <= $totalPages; $i++):
                $pageParams['page'] = $i;
            ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link"
                       href="<?= e(url('admin/activity_logs.php?' . http_build_query(array_filter($pageParams, fn($v) => $v !== '')))) ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<div class="alert alert-info mt-3 mb-0">
    <i class="bi bi-info-circle me-1"></i>
    Activity logs are append-only and hash-chained. Editing, deleting or
    reordering any entry breaks the chain at that point and is detected by
    the verify tool. Logs never contain passwords or session secrets.
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>