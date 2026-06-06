<?php
$user = current_user();
$hour = (int) date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
page_header('Dashboard', 'Overview of stock operations and pending approvals');
?>

<div class="welcome-banner animate-in animate-in-delay-1">
    <div class="welcome-banner-content">
        <span class="welcome-banner-greeting"><?= e($greeting) ?>, <?= e($user['display_name'] ?? 'User') ?></span>
        <h2 class="welcome-banner-title">Stock operations at a glance</h2>
        <p class="welcome-banner-desc">Monitor inventory, approvals, and daily movement for <?= e(APP_COMPANY) ?>.</p>
    </div>
    <div class="welcome-banner-meta">
        <div class="welcome-stat-pill">
            <i class="bi bi-box-arrow-in-down text-success"></i>
            <span><?= format_number($stats['stock_in_today']) ?> in today</span>
        </div>
        <div class="welcome-stat-pill">
            <i class="bi bi-box-arrow-up-right text-danger"></i>
            <span><?= format_number($stats['stock_out_today']) ?> out today</span>
        </div>
    </div>
</div>

<div class="row g-3 g-md-4 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-card--primary animate-in animate-in-delay-2">
            <div class="stat-card-top">
                <div class="stat-card-icon primary"><i class="bi bi-box-seam"></i></div>
                <span class="stat-card-trend stat-card-trend--neutral">Active</span>
            </div>
            <div class="stat-card-value"><?= format_number($stats['total_items']) ?></div>
            <div class="stat-card-label">Total Items</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-card--success animate-in animate-in-delay-3">
            <div class="stat-card-top">
                <div class="stat-card-icon success"><i class="bi bi-stack"></i></div>
                <span class="stat-card-trend stat-card-trend--up"><i class="bi bi-arrow-up-short"></i> Stock</span>
            </div>
            <div class="stat-card-value"><?= format_number($stats['total_stock']) ?></div>
            <div class="stat-card-label">Total Stock Units</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-card--warning animate-in animate-in-delay-4">
            <div class="stat-card-top">
                <div class="stat-card-icon warning"><i class="bi bi-clock-history"></i></div>
                <?php if ($stats['pending_count'] > 0): ?>
                <span class="stat-card-trend stat-card-trend--warn">Action needed</span>
                <?php endif; ?>
            </div>
            <div class="stat-card-value"><?= format_number($stats['pending_count']) ?></div>
            <div class="stat-card-label">Pending Approvals</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-card--info animate-in animate-in-delay-5">
            <div class="stat-card-top">
                <div class="stat-card-icon info"><i class="bi bi-people"></i></div>
            </div>
            <div class="stat-card-value"><?= format_number($stats['customers']) ?></div>
            <div class="stat-card-label">Customers</div>
        </div>
    </div>
</div>

<div class="row g-3 g-md-4">
    <div class="col-lg-8">
        <div class="card card-polished mb-4 animate-in">
            <div class="card-header card-header-polished">
                <div class="card-header-title">
                    <span class="card-header-icon"><i class="bi bi-bar-chart-fill"></i></span>
                    <span>Stock by Category</span>
                </div>
                <a href="<?= base_url('pages/balance/index.php') ?>" class="btn btn-sm btn-soft-primary">View Balance</a>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="dashboardCategoryChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card card-polished animate-in">
            <div class="card-header card-header-polished">
                <div class="card-header-title">
                    <span class="card-header-icon"><i class="bi bi-activity"></i></span>
                    <span>Recent Activity</span>
                </div>
            </div>
            <div class="card-body p-0 px-3">
                <?php if (empty($activities)): ?>
                <div class="empty-state py-4">
                    <i class="bi bi-activity d-block"></i>
                    <p class="mb-0 small">No recent approved activity yet.</p>
                </div>
                <?php else: ?>
                <?php foreach ($activities as $act): ?>
                <div class="activity-item">
                    <div class="activity-icon <?= e($act['type']) ?>">
                        <i class="bi bi-box-arrow-<?= $act['type'] === 'in' ? 'in-down' : 'up-right' ?>"></i>
                    </div>
                    <div class="activity-body">
                        <div class="activity-title">
                            Stock <?= $act['type'] === 'in' ? 'In' : 'Out' ?>:
                            <?= e($act['item']) ?>
                            <span class="text-muted">(<?= format_number($act['qty']) ?> <?= e($act['unit']) ?>)</span>
                        </div>
                        <div class="activity-meta">
                            <?= e($act['user']) ?> &middot; <?= format_datetime($act['time']) ?>
                        </div>
                    </div>
                    <div><?= status_badge($act['status']) ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-polished mb-4 animate-in">
            <div class="card-header card-header-polished">
                <div class="card-header-title">
                    <span class="card-header-icon card-header-icon--warning"><i class="bi bi-bell"></i></span>
                    <span><?= is_admin() ? 'Pending Approvals' : 'My Pending Requests' ?></span>
                </div>
                <span class="badge badge-pill-count"><?= count($pending) ?></span>
            </div>
            <div class="card-body p-0 px-3">
                <?php if (empty($pending)): ?>
                <div class="empty-state py-4">
                    <i class="bi bi-check2-all d-block"></i>
                    <p class="mb-0 small">No pending requests</p>
                </div>
                <?php else: ?>
                <?php foreach ($pending as $p): ?>
                <div class="pending-item">
                    <div class="pending-info">
                        <div class="pending-title"><?= e($p['title']) ?></div>
                        <div class="pending-meta"><?= e($p['meta']) ?></div>
                    </div>
                    <?php if (is_admin()): ?>
                    <div class="pending-actions">
                        <button class="btn btn-sm btn-success btn-icon-round" title="Approve"
                                data-approve
                                data-approve-url="<?= e($p['approve_url']) ?>"
                                data-name="<?= e($p['title']) ?>">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger btn-icon-round" title="Reject"
                                data-reject
                                data-reject-id="<?= $p['reject_id'] ?>"
                                data-reject-url="<?= e($p['reject_url']) ?>"
                                data-name="<?= e($p['title']) ?>">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card card-polished animate-in">
            <div class="card-header card-header-polished">
                <div class="card-header-title">
                    <span class="card-header-icon"><i class="bi bi-calendar-day"></i></span>
                    <span>Today's Summary</span>
                </div>
            </div>
            <div class="card-body">
                <div class="summary-row">
                    <span class="summary-label"><i class="bi bi-box-arrow-in-down text-success me-1"></i>Stock In</span>
                    <span class="summary-value text-success"><?= format_number($stats['stock_in_today']) ?> records</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label"><i class="bi bi-box-arrow-up-right text-danger me-1"></i>Stock Out</span>
                    <span class="summary-value text-danger"><?= format_number($stats['stock_out_today']) ?> records</span>
                </div>
                <div class="quick-actions d-grid gap-2 mt-3">
                    <?php if (is_admin()): ?>
                    <a href="<?= base_url('pages/stock-in/create.php') ?>" class="btn btn-sm btn-soft-success">
                        <i class="bi bi-plus-lg me-1"></i>New Stock In
                    </a>
                    <a href="<?= base_url('pages/stock-out/create.php') ?>" class="btn btn-sm btn-soft-danger">
                        <i class="bi bi-plus-lg me-1"></i>New Stock Out
                    </a>
                    <?php else: ?>
                    <a href="<?= base_url('pages/stock-in/create.php') ?>" class="btn btn-sm btn-soft-primary">
                        <i class="bi bi-send me-1"></i>Submit Stock In Request
                    </a>
                    <a href="<?= base_url('pages/stock-out/create.php') ?>" class="btn btn-sm btn-soft-primary">
                        <i class="bi bi-send me-1"></i>Submit Stock Out Request
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/partials/approval-modal.php'; ?>

<?php
$footerScript = '$(function(){AppCharts.bar("dashboardCategoryChart",'
    . json_encode($chart['labels']) . ','
    . json_encode($chart['values']) . ');});';