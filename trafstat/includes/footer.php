<?php
/**
 * includes/footer.php
 * Closes .admin-content, .admin-main and .admin-shell; loads scripts.
 */
if (!defined('APP_NAME')) {
    exit('Direct access denied.');
}
?>
        </div><!-- .admin-content -->
    </main><!-- .admin-main -->
</div><!-- .admin-shell -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url('assets/js/main.js')) ?>"></script>
<?php if (!empty($pageScripts)): ?>
    <?php foreach ((array) $pageScripts as $script): ?>
        <script src="<?= e($script) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>