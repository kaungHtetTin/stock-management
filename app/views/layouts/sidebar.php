<?php
$currentNav = $currentNav ?? '';
$user = current_user();

$navSections = [
    [
        'label' => 'Overview',
        'items' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'speedometer2', 'url' => 'pages/dashboard.php'],
        ],
    ],
    [
        'label' => 'Inventory',
        'items' => [
            ['key' => 'items', 'label' => 'Items', 'icon' => 'box-seam', 'url' => 'pages/items/index.php'],
            ['key' => 'categories', 'label' => 'Categories', 'icon' => 'tags', 'url' => 'pages/categories/index.php', 'admin' => true],
            ['key' => 'customers', 'label' => 'Customers', 'icon' => 'people', 'url' => 'pages/customers/index.php'],
            ['key' => 'balance', 'label' => 'Balance', 'icon' => 'bar-chart-line', 'url' => 'pages/balance/index.php'],
            ['key' => 'expiry', 'label' => 'Expiry Tracking', 'icon' => 'calendar2-week', 'url' => 'pages/expiry/index.php'],
        ],
    ],
    [
        'label' => 'Operations',
        'items' => [
            ['key' => 'stock-in', 'label' => 'Stock In', 'icon' => 'box-arrow-in-down', 'url' => 'pages/stock-in/index.php'],
            ['key' => 'stock-out', 'label' => 'Stock Out', 'icon' => 'box-arrow-up-right', 'url' => 'pages/stock-out/index.php'],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'file-earmark-bar-graph', 'url' => 'pages/reports/index.php'],
        ],
    ],
];

if (is_admin()) {
    $navSections[] = [
        'label' => 'System',
        'items' => [
            ['key' => 'users', 'label' => 'Users', 'icon' => 'person-gear', 'url' => 'pages/users/index.php'],
        ],
    ];
}
?>
<aside class="app-sidebar admin-sidebar glass" id="appSidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <img src="<?= asset_url('img/logo.png') ?>" alt="<?= e(APP_NAME) ?> logo">
        </div>
        <div class="brand-text">
            <span class="brand-title"><?= e(APP_NAME) ?></span>
            <span class="brand-sub"><?= e(APP_COMPANY) ?></span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($navSections as $section): ?>
        <div class="sidebar-section">
            <span class="sidebar-section-label eyebrow"><?= e($section['label']) ?></span>
            <?php foreach ($section['items'] as $item): ?>
            <?php if (!empty($item['admin']) && !is_admin()) continue; ?>
            <a href="<?= base_url($item['url']) ?>"
               class="sidebar-link<?= active_nav($item['key'], $currentNav) ?>">
                <span class="sidebar-link-icon"><i class="bi bi-<?= e($item['icon']) ?>"></i></span>
                <span class="sidebar-link-text"><?= e($item['label']) ?></span>
                <?php if ($item['key'] === 'stock-in' && is_admin() && ($pendingBadge ?? 0) > 0): ?>
                <small class="sidebar-badge"><?= (int) $pendingBadge ?></small>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer admin-profile">
        <div class="sidebar-user">
            <div class="user-avatar"><?= strtoupper(substr($user['display_name'] ?? 'U', 0, 2)) ?></div>
            <div class="user-info">
                <span class="user-name"><?= e($user['display_name'] ?? 'User') ?></span>
                <span class="user-role"><?= e(ucfirst($user['role'] ?? 'staff')) ?></span>
            </div>
        </div>
        <a href="<?= base_url('logout.php') ?>" class="sidebar-logout icon-btn small" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</aside>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
