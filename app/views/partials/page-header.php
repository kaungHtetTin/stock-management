<div class="page-header admin-page-heading animate-in">
    <div class="page-header-text">
        <p class="eyebrow"><?= date('D, d M Y') ?></p>
        <h1 class="page-title"><?= e($title) ?></h1>
        <?php if (!empty($subtitle)): ?>
        <p class="page-subtitle"><?= e($subtitle) ?></p>
        <?php endif; ?>
    </div>
    <?php if (!empty($actionsHtml)): ?>
    <div class="page-header-actions page-cta">
        <?= $actionsHtml ?>
    </div>
    <?php endif; ?>
</div>
