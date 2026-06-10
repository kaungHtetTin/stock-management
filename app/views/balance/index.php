<?php
require_once APP_PATH . '/services/BalanceService.php';
$filters = $filters ?? ['q' => '', 'category_id' => ''];
$categories = $categories ?? [];
page_header('Balance List', 'Current stock levels and category distribution');

ob_start();
?>
<div class="col-12 col-md-4">
    <label class="form-label">Search</label>
    <input type="text" class="form-control" name="q" placeholder="Item no or name..."
           value="<?= e($filters['q']) ?>">
</div>
<div class="col-12 col-md-3">
    <label class="form-label">Category</label>
    <select class="form-select" name="category_id">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>" <?= (string) $filters['category_id'] === (string) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<?php
$searchFields = ob_get_clean();
$showReset = true;
$resetUrl = base_url('pages/balance/index.php');
require APP_PATH . '/views/partials/search-card.php';
?>

<?php list_panel_open('INVENTORY', 'Current Stock', count($balances)); ?>
                <table class="table data-table data-table-mobile data-table-searchable mb-0">
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
                        <?php if (empty($balances)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-inbox d-block fs-2 mb-2 opacity-50"></i>
                                No items with balance data found.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($balances as $row): ?>
                        <?php $isLow = BalanceService::isLowStock((float) $row['balance']); ?>
                        <tr>
                            <td data-label="Item No"><span class="text-code"><?= e($row['item_no']) ?></span></td>
                            <td data-label="Name"><?= e($row['item_name']) ?></td>
                            <td data-label="Category"><?= category_badge($row['category']) ?></td>
                            <td data-label="Balance">
                                <span class="<?= $isLow ? 'balance-low' : 'balance-ok' ?>">
                                    <?= format_number($row['balance'], 2) ?>
                                    <?php if ($isLow): ?>
                                    <i class="bi bi-exclamation-triangle-fill ms-1" title="Low stock"></i>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td data-label="Unit"><?= e($row['unit']) ?></td>
                            <td data-label="Last In"><span class="small"><?= format_date($row['last_in']) ?></span></td>
                            <td data-label="Last Out"><span class="small"><?= format_date($row['last_out']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
<?php list_panel_table_close(); list_panel_close(); ?>

<div class="admin-grid">
    <section class="card card-polished panel glass h-100">
            <div class="card-header card-header-polished panel-heading">
                <div class="card-header-title">
                    <span class="card-header-icon"><i class="bi bi-bar-chart-fill"></i></span>
                    <div><p class="eyebrow mb-1">ANALYTICS</p><span>Balance by Category</span></div>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="balanceBarChart"></canvas>
                </div>
            </div>
        </section>

    <section class="card card-polished panel glass h-100">
            <div class="card-header card-header-polished panel-heading">
                <div class="card-header-title">
                    <span class="card-header-icon"><i class="bi bi-pie-chart"></i></span>
                    <div><p class="eyebrow mb-1">ANALYTICS</p><span>Category Share</span></div>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="balanceDoughnutChart"></canvas>
                </div>
            </div>
        </section>
</div>

<?php
$footerScript = '$(function(){'
    . 'AppCharts.bar("balanceBarChart",' . json_encode($chart['labels']) . ',' . json_encode($chart['values']) . ');'
    . 'AppCharts.doughnut("balanceDoughnutChart",' . json_encode($chart['labels']) . ',' . json_encode($chart['values']) . ');'
    . '});';
