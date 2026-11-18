<?php
/**
 * admin/violation_categories.php
 * List + create + edit + toggle-status for violation categories.
 *
 * The "Public disclosure" section controls what Module 7's public website
 * will show. Leave is_public unchecked until the office has approved the
 * public wording.
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
            set_flash('error', 'Invalid category reference.');
            redirect(url('admin/violation_categories.php'));
        }
        $editRecord = db_one('SELECT * FROM violation_categories WHERE category_id = ?', [$editId]);
        if ($editRecord === null) {
            set_flash('error', 'That violation category no longer exists.');
            redirect(url('admin/violation_categories.php'));
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
        'violation_name'     => $old['violation_name']     ?? ($editRecord['violation_name']     ?? ''),
        'description'        => $old['description']        ?? ($editRecord['description']        ?? ''),
        'public_description' => $old['public_description'] ?? ($editRecord['public_description'] ?? ''),
        'is_public'          => (int) ($old['is_public']
                                    ?? ($editRecord['is_public'] ?? 0)),
    ];

    $pageTitle   = $editRecord ? 'Edit Violation Category' : 'New Violation Category';
    $currentPage = 'violation_categories';
    require __DIR__ . '/../includes/header.php';
    ?>

    <div class="row justify-content-center">
        <div class="col-12 col-xl-8">

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h2 class="section-title mb-1">
                        <?= $editRecord ? 'Edit Violation Category' : 'New Violation Category' ?>
                    </h2>
                    <?php if ($editRecord): ?>
                        <p class="text-muted-sm mb-0">
                            Created <?= e(format_datetime($editRecord['created_at'])) ?>
                            &middot; Status:
                            <span class="badge bg-<?= $editRecord['status'] === 'active' ? 'success' : 'secondary' ?>">
                                <?= e(ucfirst($editRecord['status'])) ?>
                            </span>
                        </p>
                    <?php endif; ?>
                </div>
                <a href="<?= e(url('admin/violation_categories.php')) ?>" class="btn btn-outline-secondary btn-sm">
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
                    <form method="post" action="<?= e(url('actions/save_violation_category.php')) ?>" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="intent" value="<?= $editRecord ? 'update' : 'create' ?>">
                        <?php if ($editRecord): ?>
                            <input type="hidden" name="category_id" value="<?= (int) $editRecord['category_id'] ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="violation_name" class="form-label">
                                Violation name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="violation_name" name="violation_name"
                                   value="<?= e($values['violation_name']) ?>"
                                   maxlength="150" required autofocus
                                   placeholder="e.g. Illegal Parking">
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Internal description</label>
                            <textarea class="form-control" id="description" name="description"
                                      rows="3" maxlength="2000"
                                      placeholder="Used by administrators. Not shown publicly."><?= e($values['description']) ?></textarea>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex align-items-center gap-2 mb-3">
                            <i class="bi bi-globe2 text-primary"></i>
                            <h3 class="section-title m-0" style="font-size: 1rem;">Public disclosure</h3>
                        </div>
                        <p class="text-muted-sm">
                            Only categories with the flag enabled AND an approved public description
                            will appear on the public website. Confirm the wording with the office's
                            Data Protection Officer before publishing.
                        </p>

                        <div class="mb-3">
                            <label for="public_description" class="form-label">Public description</label>
                            <textarea class="form-control" id="public_description" name="public_description"
                                      rows="3" maxlength="2000"
                                      placeholder="Plain-language description safe for public viewing."><?= e($values['public_description']) ?></textarea>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox"
                                   id="is_public" name="is_public" value="1"
                                   <?= $values['is_public'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_public">
                                Show this category on the public website
                            </label>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2 me-1"></i>
                                <?= $editRecord ? 'Save changes' : 'Create category' ?>
                            </button>
                            <a href="<?= e(url('admin/violation_categories.php')) ?>" class="btn btn-outline-secondary">
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
/* LIST                                                                */
/* ================================================================== */
$categories = db_all(
    "SELECT c.*,
            (SELECT COUNT(*) FROM apprehension_records a
              WHERE a.category_id = c.category_id AND a.record_status = 'active') AS usage_count
       FROM violation_categories c
   ORDER BY c.status ASC, c.violation_name ASC"
);

$pageTitle   = 'Violation Categories';
$currentPage = 'violation_categories';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h2 class="section-title mb-1">Violation Categories</h2>
        <p class="text-muted-sm mb-0">
            <?= number_format(count($categories)) ?> categor<?= count($categories) === 1 ? 'y' : 'ies' ?>
            &mdash; active and inactive shown together.
        </p>
    </div>
    <a href="<?= e(url('admin/violation_categories.php?action=new')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New category
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Description</th>
                    <th class="text-center">Public</th>
                    <th class="text-center">In use</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted-sm py-4">
                            No violation categories yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $c):
                        $isActive = ($c['status'] === 'active');
                        $usage    = (int) $c['usage_count'];
                    ?>
                    <tr>
                        <td>
                            <strong><?= e($c['violation_name']) ?></strong>
                        </td>
                        <td class="text-muted-sm">
                            <?= $c['description'] !== null && $c['description'] !== ''
                                ? e(mb_strimwidth($c['description'], 0, 80, '…'))
                                : '—' ?>
                        </td>
                        <td class="text-center">
                            <?php if ((int) $c['is_public'] === 1): ?>
                                <span class="badge bg-primary" title="Published on public site">
                                    <i class="bi bi-globe2"></i>
                                </span>
                            <?php else: ?>
                                <span class="text-muted-sm">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?= number_format($usage) ?>
                        </td>
                        <td>
                            <?php if ($isActive): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a href="<?= e(url('admin/violation_categories.php?action=edit&id=' . (int) $c['category_id'])) ?>"
                                   class="btn btn-sm btn-outline-primary" title="Edit"
                                   aria-label="Edit <?= e($c['violation_name']) ?>">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <?php if ($isActive): ?>
                                    <form method="post" action="<?= e(url('actions/save_violation_category.php')) ?>"
                                          class="d-inline"
                                          data-confirm="<?= $usage > 0
                                              ? 'This category is used by ' . $usage . ' active apprehension(s). Deactivating hides it from new records but preserves history. Continue?'
                                              : 'Deactivate this category?' ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="intent" value="toggle">
                                        <input type="hidden" name="category_id" value="<?= (int) $c['category_id'] ?>">
                                        <input type="hidden" name="new_status" value="inactive">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Deactivate">
                                            <i class="bi bi-pause-circle"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?= e(url('actions/save_violation_category.php')) ?>"
                                          class="d-inline"
                                          data-confirm="Activate this category?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="intent" value="toggle">
                                        <input type="hidden" name="category_id" value="<?= (int) $c['category_id'] ?>">
                                        <input type="hidden" name="new_status" value="active">
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Activate">
                                            <i class="bi bi-play-circle"></i>
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

<div class="alert alert-info mt-3 mb-0">
    <i class="bi bi-info-circle me-1"></i>
    Deactivating a category does not delete it. Historical apprehensions referencing
    it are preserved. Public wording is used on the public website in Module 7.
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>