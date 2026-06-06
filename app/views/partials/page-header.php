<div class="page-header animate-in">
    <div class="page-header-text">
        <h1 class="page-title"><?= e($title) ?></h1>
        <?php if (!empty($subtitle)): ?>
        <p class="page-subtitle"><?= e($subtitle) ?></p>
        <?php endif; ?>
    </div>
    <?php if (!empty($actionsHtml)): ?>
    <div class="page-header-actions">
        <?= $actionsHtml ?>
    </div>
    <?php endif; ?>
</div>
