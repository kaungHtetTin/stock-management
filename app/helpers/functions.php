<?php
/**
 * Global helper functions
 */

function base_url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return rtrim(APP_URL, '/') . ($path ? '/' . $path : '');
}

function asset_url(string $path): string
{
    return base_url('assets/' . ltrim($path, '/'));
}

function redirect(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function active_nav(string $key, string $current): string
{
    return $key === $current ? ' active' : '';
}

function format_datetime(?string $value): string
{
    if (!$value) {
        return '—';
    }
    return date('d M Y, H:i', strtotime($value));
}

function format_date(?string $value): string
{
    if (!$value) {
        return '—';
    }
    return date('d M Y', strtotime($value));
}

function format_number($value, int $decimals = 0): string
{
    return number_format((float) $value, $decimals);
}

function status_badge(string $status): string
{
    $map = [
        'pending'  => ['class' => 'badge-status-pending', 'label' => 'Pending', 'icon' => 'clock-history'],
        'approved' => ['class' => 'badge-status-approved', 'label' => 'Approved', 'icon' => 'check-circle'],
        'rejected' => ['class' => 'badge-status-rejected', 'label' => 'Rejected', 'icon' => 'x-circle'],
        'active'   => ['class' => 'badge-status-approved', 'label' => 'Active', 'icon' => 'person-check'],
        'inactive' => ['class' => 'badge-status-rejected', 'label' => 'Inactive', 'icon' => 'person-x'],
    ];
    $item = $map[$status] ?? ['class' => 'bg-secondary', 'label' => ucfirst($status), 'icon' => 'circle'];
    $statusClass = match ($status) {
        'pending'  => 'status-warning',
        'approved', 'active' => 'status-success',
        'rejected', 'inactive' => 'status-danger',
        default => 'status-neutral',
    };
    return sprintf(
        '<span class="status badge-status %s %s"><span class="status-dot"></span>%s</span>',
        e($statusClass),
        e($item['class']),
        e($item['label'])
    );
}

function category_badge(string $category): string
{
    $map = [
        'Fruits'    => 'badge-cat-fruits',
        'Gelato'    => 'badge-cat-gelato',
        'Icecream'  => 'badge-cat-icecream',
    ];
    $class = $map[$category] ?? 'badge-cat-default';
    return sprintf('<span class="badge badge-category %s">%s</span>', e($class), e($category));
}

function customer_type_badge(string $type): string
{
    $class = $type === 'Retail' ? 'badge-type-retail' : 'badge-type-wholesale';
    return sprintf('<span class="badge badge-type %s">%s</span>', e($class), e($type));
}

function reason_badge(string $reason): string
{
    $map = [
        'Sales'             => 'badge-reason-sales',
        'Sample'            => 'badge-reason-sample',
        'Sale & Marketing'  => 'badge-reason-marketing',
        'Other'             => 'badge-reason-other',
    ];
    $class = $map[$reason] ?? 'bg-secondary';
    return sprintf('<span class="badge badge-reason %s">%s</span>', e($class), e($reason));
}

function list_panel_open(string $eyebrow, string $title, ?int $count = null, bool $search = true, string $searchPlaceholder = 'Quick filter...'): void
{
    $panelEyebrow = $eyebrow;
    $panelTitle = $title;
    $panelCount = $count;
    $panelSearch = $search;
    $panelSearchPlaceholder = $searchPlaceholder;
    include APP_PATH . '/views/partials/list-panel-open.php';
}

function list_panel_table_close(): void
{
    include APP_PATH . '/views/partials/list-panel-table-close.php';
}

function list_panel_close(): void
{
    include APP_PATH . '/views/partials/list-panel-close.php';
}

function page_header(string $title, string $subtitle = '', array $actions = []): void
{
    $actionsHtml = '';
    foreach ($actions as $action) {
        $class = $action['class'] ?? 'btn-primary';
        $icon  = !empty($action['icon']) ? '<i class="bi bi-' . e($action['icon']) . ' me-1"></i>' : '';
        $actionsHtml .= sprintf(
            '<a href="%s" class="btn %s%s">%s%s</a>',
            e($action['url']),
            e($class),
            !empty($action['outline']) ? ' btn-outline-primary' : '',
            $icon,
            e($action['label'])
        );
    }
    include APP_PATH . '/views/partials/page-header.php';
}

function render_app(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    include APP_PATH . '/views/' . $view;
    $content = ob_get_clean();
    include APP_PATH . '/views/layouts/app.php';
}

function generate_batch_ref(): string
{
    return bin2hex(random_bytes(16));
}

function report_query_string(array $filters, array $extra = []): string
{
    $params = array_merge($filters, $extra, ['generate' => 1]);
    $params = array_filter($params, static fn ($value) => $value !== '' && $value !== null);

    return '?' . http_build_query($params);
}
