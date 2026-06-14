<?php
require_once APP_PATH . '/models/Report.php';
require_once APP_PATH . '/models/StockOut.php';
require_once APP_PATH . '/models/Customer.php';
require_once APP_PATH . '/services/BalanceService.php';

$filters = $filters ?? Report::normalizeFilters([]);
$generated = $generated ?? false;
$report = $report ?? null;
$user = current_user();
$reportType = $filters['report_type'];
$paginationBase = base_url('pages/reports/index.php') . report_query_string($filters);
?>
<?php page_header('Reports', 'Filter and view stock reports'); ?>

<div class="card card-filter filter-toolbar glass mb-4 no-print">
    <div class="card-body p-0">
        <form method="get" action="">
            <input type="hidden" name="generate" value="1">
            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="form-label">Report Type</label>
                    <select class="form-select" name="report_type" id="reportType">
                        <?php foreach (Report::TYPES as $type): ?>
                        <option value="<?= e($type) ?>" <?= $reportType === $type ? 'selected' : '' ?>>
                            <?= e(Report::title($type)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2 report-filter-date">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" name="date_from" value="<?= e($filters['date_from']) ?>">
                </div>
                <div class="col-6 col-md-2 report-filter-date">
                    <label class="form-label">Date To</label>
                    <input type="date" class="form-control" name="date_to" value="<?= e($filters['date_to']) ?>">
                </div>
                <div class="col-6 col-md-2 report-filter-category">
                    <label class="form-label">Category</label>
                    <select class="form-select" name="category_id">
                        <option value="">All</option>
                        <?php foreach (($categories ?? []) as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= (string) ($filters['category_id'] ?? '') === (string) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 report-filter-item">
                    <label class="form-label">Item</label>
                    <input type="text" class="form-control" name="item" placeholder="Item no or name"
                           value="<?= e($filters['item']) ?>">
                </div>
                <div class="col-6 col-md-2 report-filter-out">
                    <label class="form-label">Customer</label>
                    <select class="form-select" name="customer">
                        <option value="">All</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>"
                            <?= (string) $filters['customer'] === (string) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['customer_code']) ?> — <?= e($c['customer_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2 report-filter-out">
                    <label class="form-label">Reason</label>
                    <select class="form-select" name="reason">
                        <option value="">All</option>
                        <?php foreach (StockOut::REASONS as $r): ?>
                        <option value="<?= e($r) ?>" <?= $filters['reason'] === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2 report-filter-status">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All</option>
                        <?php foreach (['approved', 'pending', 'rejected'] as $s): ?>
                        <option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= ucfirst(e($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2 report-filter-out">
                    <label class="form-label">Customer Type</label>
                    <select class="form-select" name="customer_type">
                        <option value="">All</option>
                        <?php foreach (Customer::TYPES as $t): ?>
                        <option value="<?= e($t) ?>" <?= $filters['customer_type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2 report-filter-activity">
                    <label class="form-label">Stock Type</label>
                    <select class="form-select" name="stock_type">
                        <option value="both" <?= $filters['stock_type'] === 'both' ? 'selected' : '' ?>>Both</option>
                        <option value="in" <?= $filters['stock_type'] === 'in' ? 'selected' : '' ?>>Stock In only</option>
                        <option value="out" <?= $filters['stock_type'] === 'out' ? 'selected' : '' ?>>Stock Out only</option>
                    </select>
                </div>
                <div class="col-12 col-md-auto d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel me-1"></i>Generate
                    </button>
                    <?php if ($generated): ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i>Print
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (!$generated): ?>
<section class="card card-polished panel glass">
    <div class="empty-state py-5">
        <i class="bi bi-file-earmark-bar-graph d-block"></i>
        <p class="mb-0">Select a report type and filters, then click <strong>Generate</strong>.</p>
    </div>
</section>
<?php else: ?>
<section class="report-preview panel glass" id="reportOutput">
    <div class="report-company-header">
        <h3><?= e(APP_COMPANY) ?></h3>
        <p>Company ID: <?= e(APP_COMPANY_ID) ?></p>
        <p class="fw-semibold mt-2 mb-0"><?= e(Report::title($reportType)) ?></p>
        <p class="small mb-0"><?= e(Report::filterSummary($filters)) ?></p>
        <p class="small">
            Generated: <?= date('d M Y, H:i') ?>
            &middot; By: <?= e($user['display_name'] ?? 'User') ?>
            &middot; <?= (int) ($report['total'] ?? 0) ?> record(s)
        </p>
    </div>

    <?php if ($reportType === 'current_stock' && !empty($chart['values'])): ?>
    <div class="row g-3 mb-4 d-none d-md-flex no-print">
        <div class="col-md-5 ms-auto">
            <div class="chart-container" style="height:200px">
                <canvas id="reportDoughnutChart"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($reportType === 'activity' && !empty($report['summary'])): ?>
    <div class="metrics-grid mb-3">
        <article class="metric-card glass">
            <small>Stock In records</small>
            <strong class="stat-card-value text-success"><?= format_number($report['summary']['in_count']) ?></strong>
        </article>
        <article class="metric-card glass">
            <small>Stock In qty</small>
            <strong class="stat-card-value text-success"><?= format_number($report['summary']['in_qty'], 2) ?></strong>
        </article>
        <article class="metric-card glass">
            <small>Stock Out records</small>
            <strong class="stat-card-value text-danger"><?= format_number($report['summary']['out_count']) ?></strong>
        </article>
        <article class="metric-card glass">
            <small>Stock Out qty</small>
            <strong class="stat-card-value text-danger"><?= format_number($report['summary']['out_qty'], 2) ?></strong>
        </article>
    </div>
    <?php endif; ?>

    <div class="table-wrap table-responsive">
        <?php if (empty($report['rows'])): ?>
        <div class="empty-state py-5">
            <i class="bi bi-inbox d-block"></i>
            <p class="mb-0">No records match the selected filters.</p>
        </div>
        <?php elseif ($reportType === 'stock_in'): ?>
        <table class="table data-table data-table-mobile mb-0">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Lot</th>
                    <th>MFD</th>
                    <th>Expire</th>
                    <th>Qty</th>
                    <th>In Charge</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['rows'] as $row): ?>
                <tr>
                    <td data-label="Item">
                        <span class="text-code"><?= e($row['item_no']) ?></span><br>
                        <span class="small"><?= e($row['item_name']) ?></span>
                    </td>
                    <td data-label="Lot"><?= e($row['lot_no'] ?: '—') ?></td>
                    <td data-label="MFD"><?= format_date($row['mfd_date']) ?></td>
                    <td data-label="Expire"><?= format_date($row['expire_date']) ?></td>
                    <td data-label="Qty"><strong><?= format_number($row['qty'], 2) ?></strong> <?= e($row['unit']) ?></td>
                    <td data-label="In Charge"><?= e($row['in_charge']) ?></td>
                    <td data-label="Status"><?= status_badge($row['status']) ?></td>
                    <td data-label="Created">
                        <span class="small"><?= format_datetime($row['created_at']) ?></span><br>
                        <span class="small text-muted"><?= e($row['created_by']) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php elseif ($reportType === 'stock_out'): ?>
        <table class="table data-table data-table-mobile mb-0">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Customer</th>
                    <th>MFD</th>
                    <th>Expire</th>
                    <th>Qty</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['rows'] as $row): ?>
                <tr>
                    <td data-label="Item">
                        <span class="text-code"><?= e($row['item_no']) ?></span><br>
                        <span class="small"><?= e($row['item_name']) ?></span>
                    </td>
                    <td data-label="Customer"><?= e($row['customer_name']) ?></td>
                    <td data-label="MFD"><?= format_date($row['mfd_date']) ?></td>
                    <td data-label="Expire"><?= format_date($row['expire_date']) ?></td>
                    <td data-label="Qty"><strong><?= format_number($row['qty'], 2) ?></strong> <?= e($row['unit']) ?></td>
                    <td data-label="Reason"><?= reason_badge($row['reason']) ?></td>
                    <td data-label="Status"><?= status_badge($row['status']) ?></td>
                    <td data-label="Created">
                        <span class="small"><?= format_datetime($row['created_at']) ?></span><br>
                        <span class="small text-muted"><?= e($row['created_by']) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php elseif ($reportType === 'current_stock'): ?>
        <table class="table data-table data-table-mobile mb-0">
            <thead>
                <tr>
                    <th>Item No</th>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Balance</th>
                    <th>Unit</th>
                    <th>Last In</th>
                    <th>Last Out</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['rows'] as $row): ?>
                <?php $isLow = BalanceService::isLowStock((float) $row['balance']); ?>
                <tr>
                    <td data-label="Item No"><span class="text-code"><?= e($row['item_no']) ?></span></td>
                    <td data-label="Name"><?= e($row['item_name']) ?></td>
                    <td data-label="Category"><?= category_badge($row['category']) ?></td>
                    <td data-label="Balance">
                        <span class="<?= $isLow ? 'balance-low' : 'balance-ok' ?>">
                            <strong><?= format_number($row['balance'], 2) ?></strong>
                            <?php if ($isLow): ?><i class="bi bi-exclamation-triangle-fill ms-1"></i><?php endif; ?>
                        </span>
                    </td>
                    <td data-label="Unit"><?= e($row['unit']) ?></td>
                    <td data-label="Last In"><span class="small"><?= format_date($row['last_in']) ?></span></td>
                    <td data-label="Last Out"><span class="small"><?= format_date($row['last_out']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if (!empty($report['total_units'])): ?>
            <tfoot>
                <tr class="table-light">
                    <td colspan="3" class="fw-semibold text-end">Total Units (all pages)</td>
                    <td colspan="4" class="fw-bold"><?= format_number($report['total_units'], 2) ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>

        <?php elseif ($reportType === 'activity'): ?>
        <table class="table data-table data-table-mobile mb-0">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Customer / Reason</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>User</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['rows'] as $row): ?>
                <tr>
                    <td data-label="Type">
                        <span class="status <?= $row['activity_type'] === 'in' ? 'status-success' : 'status-danger' ?>">
                            <span class="status-dot"></span><?= $row['activity_type'] === 'in' ? 'In' : 'Out' ?>
                        </span>
                    </td>
                    <td data-label="Item">
                        <span class="text-code"><?= e($row['item_no']) ?></span><br>
                        <span class="small"><?= e($row['item_name']) ?></span>
                    </td>
                    <td data-label="Qty"><strong><?= format_number($row['qty'], 2) ?></strong> <?= e($row['unit']) ?></td>
                    <td data-label="Detail">
                        <?= $row['activity_type'] === 'out'
                            ? e($row['customer_name']) . ' · ' . e($row['reason'])
                            : '—' ?>
                    </td>
                    <td data-label="Status"><?= status_badge($row['status']) ?></td>
                    <td data-label="Date"><span class="small"><?= format_datetime($row['created_at']) ?></span></td>
                    <td data-label="User"><span class="small"><?= e($row['user_name']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <?php
    $page = $report['page'];
    $totalPages = $report['total_pages'];
    $total = $report['total'];
    $perPage = $report['per_page'];
    $baseUrl = $paginationBase;
    $ariaLabel = 'Report pagination';
    require APP_PATH . '/views/partials/pagination.php';
    ?>
</section>
<?php endif; ?>

<?php
$footerScript = '$(function(){'
    . 'function toggleReportFilters(){'
    . 'var t=$("#reportType").val();'
    . '$(".report-filter-date,.report-filter-status,.report-filter-activity").toggle(t!=="current_stock");'
    . '$(".report-filter-out").toggle(t==="stock_out"||t==="activity");'
    . '$(".report-filter-category,.report-filter-item").toggle(true);'
    . '}'
    . 'toggleReportFilters();'
    . '$("#reportType").on("change",toggleReportFilters);';

if ($generated && $reportType === 'current_stock' && !empty($chart['values'])) {
    $footerScript .= 'AppCharts.doughnut("reportDoughnutChart",'
        . json_encode($chart['labels']) . ','
        . json_encode($chart['values']) . ');';
}

$footerScript .= '});';
