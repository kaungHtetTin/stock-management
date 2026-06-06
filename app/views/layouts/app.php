<?php
/**
 * Main app shell — sidebar + topbar + content
 * Expects: $pageTitle, $currentNav, optional $breadcrumbs, $pendingBadge
 */
$pendingBadge = $pendingBadge ?? 0;
require APP_PATH . '/views/layouts/header.php';
?>
<div class="app-wrapper">
    <?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
    <div class="app-main">
        <?php require APP_PATH . '/views/layouts/topbar.php'; ?>
        <div class="app-content">
            <?php require APP_PATH . '/views/partials/flash.php'; ?>
            <?= $content ?? '' ?>
        </div>
        <footer class="app-footer">
            <span>&copy; <?= date('Y') ?> <?= e(APP_COMPANY) ?></span>
            <span class="text-muted">ID: <?= e(APP_COMPANY_ID) ?></span>
        </footer>
    </div>
</div>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
<?php if (!empty($footerScript)): ?>
<script><?= $footerScript ?></script>
<?php endif; ?>
