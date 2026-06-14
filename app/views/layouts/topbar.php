<?php $user = current_user(); ?>
<header class="app-topbar admin-topbar glass">
    <button type="button" class="icon-btn sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu">
        <i class="bi bi-list"></i>
    </button>

    <div class="search-box global-search d-none d-md-flex">
        <i class="bi bi-search"></i>
        <input type="search" id="globalSearch" placeholder="Search pages..." autocomplete="off" aria-label="Search">
    </div>

    <div class="topbar-breadcrumb d-none d-sm-block">
        <?php if (!empty($breadcrumbs)): ?>
            <?php foreach ($breadcrumbs as $i => $crumb): ?>
                <?php if ($i > 0): ?><i class="bi bi-chevron-right breadcrumb-sep"></i><?php endif; ?>
                <?php if (!empty($crumb['url'])): ?>
                    <a href="<?= e($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
                <?php else: ?>
                    <span class="breadcrumb-current"><?= e($crumb['label']) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="topbar-actions ms-auto">
        <?php if (is_admin() && ($pendingBadge ?? 0) > 0): ?>
        <a href="<?= base_url('pages/stock-in/index.php?status=pending') ?>" class="icon-btn topbar-notify" title="Pending approvals">
            <i class="bi bi-bell"></i>
            <span class="topbar-notify-count"><?= (int) $pendingBadge ?></span>
        </a>
        <?php endif; ?>

        <div class="theme-control" id="themeControl">
            <button type="button" class="icon-btn" id="themeToggleBtn" aria-label="Theme settings" aria-expanded="false">
                <i class="bi bi-palette"></i>
            </button>
            <div class="theme-popover glass" id="themePopover" hidden>
                <p class="eyebrow">Appearance</p>
                <div class="theme-segment" role="group" aria-label="Theme mode">
                    <button type="button" class="theme-segment-btn" data-theme-set="light"><i class="bi bi-sun"></i> Light</button>
                    <button type="button" class="theme-segment-btn" data-theme-set="dark"><i class="bi bi-moon"></i> Dark</button>
                </div>
                <p class="eyebrow mt-3">Brand color</p>
                <div class="theme-swatches">
                    <button type="button" class="theme-swatch" data-brand="#545760" style="--swatch:#545760" title="Slate"></button>
                    <button type="button" class="theme-swatch" data-brand="#4f46e5" style="--swatch:#4f46e5" title="Indigo"></button>
                    <button type="button" class="theme-swatch" data-brand="#2563eb" style="--swatch:#2563eb" title="Blue"></button>
                    <button type="button" class="theme-swatch" data-brand="#059669" style="--swatch:#059669" title="Green"></button>
                    <label class="theme-color-input" title="Custom color">
                        <input type="color" id="brandColorInput" value="#545760">
                    </label>
                </div>
            </div>
        </div>

        <span class="topbar-date d-none d-xl-flex">
            <i class="bi bi-calendar3"></i>
            <?= date('D, d M Y') ?>
        </span>

        <div class="dropdown topbar-user-dropdown">
            <button class="topbar-user-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="user-avatar user-avatar-sm"><?= strtoupper(substr($user['display_name'] ?? 'U', 0, 1)) ?></span>
                <span class="topbar-user-name d-none d-md-inline"><?= e($user['display_name'] ?? 'User') ?></span>
                <i class="bi bi-chevron-down topbar-user-chevron"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow polished-dropdown">
                <li class="dropdown-header">
                    <span class="fw-semibold"><?= e($user['display_name'] ?? 'User') ?></span>
                    <span class="d-block small text-muted"><?= e(ucfirst($user['role'] ?? 'staff')) ?></span>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= base_url('pages/dashboard.php') ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                <?php if (is_admin()): ?>
                <li><a class="dropdown-item" href="<?= base_url('pages/users/index.php') ?>"><i class="bi bi-person-gear me-2"></i>Users</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= base_url('logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</header>
