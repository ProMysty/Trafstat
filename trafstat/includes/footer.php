<?php
/**
 * includes/footer.php
 * Closes the admin layout, renders the toast container, and outputs the
 * flash messages as JSON for animations.js to render.
 */
if (!defined('APP_NAME')) {
    exit('Direct access denied.');
}

/* Consume flashes exactly once per request. */
$flashes = take_flashes();
$flashData = [];
foreach ($flashes as $type => $messages) {
    if (!in_array($type, ['success', 'error', 'warning', 'info'], true)) {
        $type = 'info';
    }
    foreach ((array) $messages as $message) {
        $flashData[] = ['type' => $type, 'message' => (string) $message];
    }
}
?>
        </div><!-- .admin-content -->
    </main><!-- .admin-main -->
</div><!-- .admin-shell -->

<div id="traf-toast-container" class="traf-toast-container" aria-live="polite" aria-atomic="false"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url('assets/js/main.js')) ?>"></script>
<script src="<?= e(url('assets/js/animations.js')) ?>"></script>
<script src="<?= e(url('assets/js/chart-utils.js')) ?>"></script>
<script nonce="<?= e(csp_nonce()) ?>">
    
window.TRAF_FLASHES = <?= json_encode(
    $flashData,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
) ?>;
</script>

<?php if (!empty($pageScripts)): ?>
    <?php foreach ((array) $pageScripts as $script): ?>
        <script src="<?= e($script) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>