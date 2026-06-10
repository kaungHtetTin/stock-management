<?php
/**
 * List panel header — expects $panelEyebrow, $panelTitle
 * Optional: $panelCount, $panelSearch (default true), $panelSearchPlaceholder
 */
$panelSearch = $panelSearch ?? true;
$panelSearchPlaceholder = $panelSearchPlaceholder ?? 'Quick filter...';
?>
<section class="card card-polished panel glass table-card">
    <div class="card-header card-header-polished panel-heading">
        <div>
            <p class="eyebrow"><?= e($panelEyebrow) ?></p>
            <span><?= e($panelTitle) ?><?php if (isset($panelCount)): ?> <span class="text-muted fw-normal">(<?= format_number((int) $panelCount) ?>)</span><?php endif; ?></span>
        </div>
        <?php if ($panelSearch): ?>
        <div class="search-box" style="max-width:220px">
            <i class="bi bi-search"></i>
            <input type="text" id="tableSearch" placeholder="<?= e($panelSearchPlaceholder) ?>" aria-label="Quick filter">
        </div>
        <?php endif; ?>
    </div>
    <div class="table-wrap table-responsive">
