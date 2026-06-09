    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= asset_url('js/app.js') ?>?v=<?= filemtime(PUBLIC_PATH . '/assets/js/app.js') ?>"></script>
    <?php if (!empty($footerScript)): ?>
    <script><?= $footerScript ?></script>
    <?php endif; ?>
    <?php if (!empty($pageScripts)): ?>
        <?php foreach ($pageScripts as $script): ?>
    <script src="<?= asset_url('js/' . $script) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
