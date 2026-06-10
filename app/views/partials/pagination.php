<?php
/**
 * List pagination — expects $page, $totalPages, $baseUrl
 * Optional: $total, $perPage, $ariaLabel
 */
$totalPages = max(1, (int) ($totalPages ?? 1));
$current = min(max(1, (int) ($page ?? 1)), $totalPages);
$perPage = (int) ($perPage ?? Pagination::PER_PAGE);
$totalCount = isset($total) ? (int) $total : null;
$ariaLabel = $ariaLabel ?? 'Table pagination';
$sep = str_contains($baseUrl ?? '', '?') ? '&' : '?';

if ($totalPages <= 1 && ($totalCount === null || $totalCount <= $perPage)) {
    if ($totalCount !== null && $totalCount > 0): ?>
<p class="small text-muted mt-3 mb-0">
    Showing <?= format_number($totalCount) ?> record<?= $totalCount === 1 ? '' : 's' ?>
</p>
    <?php endif;
    return;
}

$from = $totalCount > 0 ? (($current - 1) * $perPage) + 1 : 0;
$to = $totalCount !== null ? min($current * $perPage, $totalCount) : $current * $perPage;

$window = 2;
$start = max(1, $current - $window);
$end = min($totalPages, $current + $window);
if ($end - $start < $window * 2) {
    if ($start === 1) {
        $end = min($totalPages, $start + $window * 2);
    } elseif ($end === $totalPages) {
        $start = max(1, $end - $window * 2);
    }
}
?>
<nav class="d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center gap-2 mt-3" aria-label="<?= e($ariaLabel) ?>">
    <span class="small text-muted">
        <?php if ($totalCount !== null): ?>
        Showing <?= format_number($from) ?>–<?= format_number($to) ?> of <?= format_number($totalCount) ?>
        <?php else: ?>
        Page <?= $current ?> of <?= $totalPages ?>
        <?php endif; ?>
    </span>
    <ul class="pagination pagination-sm mb-0 flex-wrap justify-content-center justify-content-sm-end">
        <li class="page-item <?= $current <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $current > 1 ? e($baseUrl . $sep . 'page=1') : '#' ?>" aria-label="First page">«</a>
        </li>
        <li class="page-item <?= $current <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $current > 1 ? e($baseUrl . $sep . 'page=' . ($current - 1)) : '#' ?>" aria-label="Previous page">Prev</a>
        </li>
        <?php if ($start > 1): ?>
        <li class="page-item">
            <a class="page-link" href="<?= e($baseUrl . $sep . 'page=1') ?>">1</a>
        </li>
        <?php if ($start > 2): ?>
        <li class="page-item disabled"><span class="page-link">…</span></li>
        <?php endif; ?>
        <?php endif; ?>
        <?php for ($p = $start; $p <= $end; $p++): ?>
        <li class="page-item <?= $p === $current ? 'active' : '' ?>">
            <a class="page-link" href="<?= $p === $current ? '#' : e($baseUrl . $sep . 'page=' . $p) ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($end < $totalPages): ?>
        <?php if ($end < $totalPages - 1): ?>
        <li class="page-item disabled"><span class="page-link">…</span></li>
        <?php endif; ?>
        <li class="page-item">
            <a class="page-link" href="<?= e($baseUrl . $sep . 'page=' . $totalPages) ?>"><?= $totalPages ?></a>
        </li>
        <?php endif; ?>
        <li class="page-item <?= $current >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $current < $totalPages ? e($baseUrl . $sep . 'page=' . ($current + 1)) : '#' ?>" aria-label="Next page">Next</a>
        </li>
        <li class="page-item <?= $current >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $current < $totalPages ? e($baseUrl . $sep . 'page=' . $totalPages) : '#' ?>" aria-label="Last page">»</a>
        </li>
    </ul>
</nav>
