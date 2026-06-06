<?php
/**
 * Simple pagination — expects $page, $totalPages, $baseUrl (query string without page)
 */
if (($totalPages ?? 1) <= 1) {
    return;
}
$current = (int) ($page ?? 1);
$sep = str_contains($baseUrl ?? '', '?') ? '&' : '?';
?>
<nav class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2" aria-label="Report pagination">
    <span class="small text-muted">
        Page <?= $current ?> of <?= (int) $totalPages ?>
    </span>
    <ul class="pagination pagination-sm mb-0">
        <?php if ($current > 1): ?>
        <li class="page-item">
            <a class="page-link" href="<?= e($baseUrl . $sep . 'page=' . ($current - 1)) ?>">Previous</a>
        </li>
        <?php endif; ?>
        <?php if ($current < $totalPages): ?>
        <li class="page-item">
            <a class="page-link" href="<?= e($baseUrl . $sep . 'page=' . ($current + 1)) ?>">Next</a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
