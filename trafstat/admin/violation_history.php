<?php
/**
 * admin/violation_history.php
 * Violation history and repeat-incident monitoring.
 *
 * IMPORTANT: "repeat recorded incident" is a system record indicator only.
 * It is not proof of guilt, nor a legal finding of recidivism.
 */
require_once __DIR__ . '/../includes/init.php';
$admin = require_admin_ready();

$violatorId = isset($_GET['violator_id']) ? (int) $_GET['violator_id'] : 0;
$violatorId = $violatorId > 0 ? $violatorId : null;

/* ================================================================== */
/* DETAIL VIEW                                                         */
/* ================================================================== */
if ($violatorId !== null) {
    $violator = db_one(
        'SELECT * FROM traffic_violators WHERE violator_id = ?',
        [$violatorId]
    );

    if ($violator === null) {
        set_flash('error', 'That violator record no longer exists.');
        redirect(url('admin/violation_history.php'));
    }

    $history = db_all(
        "SELECT a.apprehension_id, a.reference_no, a.apprehension_date,
                a.apprehension_time, a.location_text, a.record_status,
                c.violation_name, c.category_id
           FROM apprehension_records a
     INNER JOIN violation_categories c ON c.category_id = a.category_id
          WHERE a.violator_id   = :violator_id
            AND a.record_status = 'active'
       ORDER BY a.apprehension_date DESC, a.apprehension_id DESC",
        [':violator_id' => $violatorId]
    );

    $totalIncidents = count($history);
    $isRepeat       = $totalIncidents >= 2;

    $categoryCounts = db_all(
        "SELECT c.violation_name, COUNT(a.apprehension_id) AS total
           FROM apprehension_records a
     INNER JOIN violation_categories c ON c.category_id = a.category_id
          WHERE a.violator_id   = :violator_id
            AND a.record_status = 'active'
       GROUP BY c.category_id, c.violation_name
       ORDER BY total DESC, c.violation_name ASC",
        [':violator_id' => $violatorId]
    );

    $fullName = trim(
        $violator['first_name'] . ' '
        . ($violator['middle_name'] !== null && $violator['middle_name'] !== ''
            ? mb_substr($violator['middle_name'], 0, 1) . '. ' : '')
        . $violator['last_name']
        . ($violator['suffix'] !== null && $violator['suffix'] !== ''
            ? ' ' . $violator['suffix'] : '')
    );

    $pageTitle   = 'Violation History';
    $currentPage = 'violation_history';
    require __DIR__ . '/../includes/header.php';
    ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="section-title mb-1">Violation History</h2>
            <p class="text-muted-sm mb-0">
                <code><?= e($violator['violator_code']) ?></code>
                &mdash; <?= e($fullName) ?>
                <?php if ($violator['record_status'] === 'archived'): ?>
                    &middot; <span class="badge bg-secondary">Archived violator</span>
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= e(url('admin/violators.php?action=edit&id=' . $violatorId)) ?>"
               class="btn btn-outline-primary btn-sm">
                <i class="bi bi-pencil me-1"></i> Edit violator
            </a>
            <a href="<?= e(url('admin/violation_history.php')) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> All violators
            </a>
        </div>
    </div>

    <?php if ($isRepeat): ?>
        <div class="alert alert-warning d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div>
                <strong>Multiple recorded incidents.</strong>
                This violator has <?= $totalIncidents ?> recorded apprehension<?= $totalIncidents === 1 ? '' : 's' ?> in TrafStat.
                This is a system record indicator, not a legal finding of guilt or recidivism.
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="card h-100">
                <div class="card-body p-4">
                    <h3 class="section-title mb-3">Summary</h3>

                    <div class="mb-3">
                        <div class="text-muted-sm">Total recorded apprehensions</div>
                        <div class="stat-card-value"><?= number_format($totalIncidents) ?></div>
                    </div>

                    <?php if (!empty($categoryCounts)): ?>
                        <div class="text-muted-sm mb-2">By category</div>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($categoryCounts as $row): ?>
                                <li class="d-flex justify-content-between align-items-center py-1 border-bottom">
                                    <span><?= e($row['violation_name']) ?></span>
                                    <span class="badge bg-secondary"><?= (int) $row['total'] ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted-sm mb-0">No recorded apprehensions.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card h-100">
                <div class="card-body p-4">
                    <h3 class="section-title mb-3">Recorded incidents</h3>

                    <?php if (empty($history)): ?>
                        <p class="text-muted-sm mb-0">
                            No active apprehension records for this violator.
                        </p>
                    <?php else: ?>
                        <ul class="history-timeline">
                            <?php foreach ($history as $row): ?>
                                <li>
                                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-1">
                                        <div>
                                            <strong><?= e($row['violation_name']) ?></strong>
                                            <code class="ms-2"><?= e($row['reference_no']) ?></code>
                                        </div>
                                        <span class="text-muted-sm">
                                            <?= e(format_date($row['apprehension_date'])) ?>
                                            <?php if (!empty($row['apprehension_time'])): ?>
                                                &middot; <?= e(date('g:i A', strtotime($row['apprehension_time']))) ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="text-muted-sm">
                                        <i class="bi bi-geo-alt"></i> <?= e($row['location_text']) ?>
                                    </div>
                                    <div class="mt-1">
                                        <a href="<?= e(url('admin/apprehensions.php?action=edit&id=' . (int) $row['apprehension_id'])) ?>"
                                           class="btn btn-sm btn-outline-primary mt-1">
                                            <i class="bi bi-pencil me-1"></i> Open record
                                        </a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

/* ================================================================== */
/* LIST VIEW — ranked by incident count                                */
/* ================================================================== */
$perPage = 25;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$onlyRepeat = isset($_GET['repeat']) && $_GET['repeat'] === '1';

$havingSql = $onlyRepeat ? 'HAVING apprehension_count >= 2' : '';

$total = (int) db_value(
    "SELECT COUNT(*) FROM (
        SELECT v.violator_id
          FROM traffic_violators v
     LEFT JOIN apprehension_records a
            ON a.violator_id = v.violator_id
           AND a.record_status = 'active'
         WHERE v.record_status = 'active'
         GROUP BY v.violator_id
         $havingSql
    ) AS q"
);

$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$rows = db_all(
    "SELECT v.violator_id, v.violator_code, v.first_name, v.middle_name, v.last_name, v.suffix,
            COUNT(a.apprehension_id) AS apprehension_count,
            MAX(a.apprehension_date) AS last_apprehension_date
       FROM traffic_violators v
  LEFT JOIN apprehension_records a
         ON a.violator_id = v.violator_id
        AND a.record_status = 'active'
      WHERE v.record_status = 'active'
      GROUP BY v.violator_id, v.violator_code, v.first_name, v.middle_name, v.last_name, v.suffix
      $havingSql
      ORDER BY apprehension_count DESC, v.last_name ASC, v.first_name ASC
      LIMIT $perPage OFFSET $offset"
);

$pageTitle   = 'Violation History';
$currentPage = 'violation_history';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h2 class="section-title mb-1">Violation History</h2>
        <p class="text-muted-sm mb-0">
            <?= number_format($total) ?> violator<?= $total === 1 ? '' : 's' ?>
            &middot; ranked by recorded incidents
        </p>
    </div>
    <form method="get" class="d-flex align-items-center gap-2">
        <div class="form-check form-switch mb-0">
           <input class="form-check-input" type="checkbox" id="repeat" name="repeat" value="1"
                <?= $onlyRepeat ? 'checked' : '' ?>
                data-auto-submit>
            <label class="form-check-label" for="repeat">
                Repeat incidents only (&ge; 2)
            </label>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Violator</th>
                    <th class="text-center">Recorded incidents</th>
                    <th>Last recorded</th>
                    <th>Indicator</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted-sm py-4">
                            <?= $onlyRepeat
                                ? 'No violators have two or more recorded incidents yet.'
                                : 'No violator records yet.' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r):
                        $count     = (int) $r['apprehension_count'];
                        $isRepeat  = $count >= 2;
                        $fullName  = trim(
                            $r['first_name'] . ' '
                            . ($r['middle_name'] !== null && $r['middle_name'] !== ''
                                ? mb_substr($r['middle_name'], 0, 1) . '. ' : '')
                            . $r['last_name']
                            . ($r['suffix'] !== null && $r['suffix'] !== ''
                                ? ' ' . $r['suffix'] : '')
                        );
                    ?>
                    <tr>
                        <td>
                            <strong><?= e($fullName) ?></strong>
                            <div class="text-muted-sm"><code><?= e($r['violator_code']) ?></code></div>
                        </td>
                        <td class="text-center">
                            <span class="fs-6 fw-semibold"><?= number_format($count) ?></span>
                        </td>
                        <td>
                            <?= $r['last_apprehension_date']
                                ? e(format_date($r['last_apprehension_date']))
                                : '—' ?>
                        </td>
                        <td>
                            <?php if ($count === 0): ?>
                                <span class="text-muted-sm">None recorded</span>
                            <?php elseif ($count === 1): ?>
                                <span class="badge bg-secondary">First recorded incident</span>
                            <?php else: ?>
                                <span class="badge badge-repeat">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    Multiple recorded incidents
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= e(url('admin/violation_history.php?violator_id=' . (int) $r['violator_id'])) ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-clock-history me-1"></i> View history
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <nav class="mt-3" aria-label="Violation history pagination">
        <ul class="pagination justify-content-center flex-wrap">
            <?php
            $pageParams = [];
            if ($onlyRepeat) { $pageParams['repeat'] = '1'; }
            for ($i = 1; $i <= $totalPages; $i++):
                $pageParams['page'] = $i;
            ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= e(url('admin/violation_history.php?' . http_build_query($pageParams))) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<div class="alert alert-info mt-3 mb-0">
    <i class="bi bi-info-circle me-1"></i>
    Repeat-record indicators are system-generated and do not constitute
    proof of guilt or a legal finding of recidivism.
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>