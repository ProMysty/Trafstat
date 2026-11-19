<?php
/**
 * includes/public_footer.php
 * Closes </main>, renders the public footer, loads scripts.
 */
if (!defined('APP_NAME')) {
    exit('Direct access denied.');
}
?>
</main>

<footer class="public-footer">
    <div class="public-footer-inner">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <?= trafstat_mark('', 32, false) ?>
                <strong style="color:#fff;font-size:1rem;"><?= e(APP_NAME) ?></strong>
            </div>
            <p>
                Traffic Violation Records and Statistical Analytics System.
                Serving the authorized traffic management office of Pagadian City.
            </p>
            <p class="mt-2" style="color:#6B7280; font-size:0.8rem;">
                This public website displays only approved aggregated statistics.
                Individual records are accessible only to authorized personnel.
            </p>
        </div>
        <div>
            <h4>Explore</h4>
            <a href="<?= e(url('index.php')) ?>">Home</a>
            <a href="<?= e(url('statistics.php')) ?>">Statistics</a>
            <a href="<?= e(url('violations.php')) ?>">Violation Info</a>
            <a href="<?= e(url('about.php')) ?>">About</a>
        </div>
        <div>
            <h4>Access</h4>
            <a href="<?= e(url('about.php#privacy')) ?>">Privacy Notice</a>
            <a href="<?= e(url('admin/login.php')) ?>">Administrator Login</a>
        </div>
    </div>
    <div class="public-footer-bottom">
        <span>&copy; <?= e(date('Y')) ?> <?= e(APP_NAME) ?> &middot; Pagadian City</span>
        <span>Public website &middot; aggregated data only</span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url('assets/js/main.js')) ?>"></script>
<script src="<?= e(url('assets/js/animations.js')) ?>"></script>
<script src="<?= e(url('assets/js/chart-utils.js')) ?>"></script>

<script nonce="<?= e(csp_nonce()) ?>">
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var toggle = document.querySelector('[data-public-nav-toggle]');
        var nav    = document.querySelector('[data-public-nav]');
        if (!toggle || !nav) { return; }

        toggle.addEventListener('click', function () {
            nav.classList.toggle('is-open');
        });
    });
})();
</script>

<?php if (!empty($pageScripts)): ?>
    <?php foreach ((array) $pageScripts as $script): ?>
        <script src="<?= e($script) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>