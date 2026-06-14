<?php
require_once APP_PATH . '/models/ExpiryTracking.php';

$filters = $filters ?? ExpiryTracking::filters([]);
$rows = $rows ?? [];
$summary = $summary ?? ExpiryTracking::summary($rows);
$categories = $categories ?? [];

page_header(
    'Expiry Tracking',
    'Monitor remaining batches by MFD and expiry date',
    [
        ['label' => 'Stock In', 'url' => base_url('pages/stock-in/index.php'), 'icon' => 'box-arrow-in-down', 'class' => 'btn-outline-primary', 'outline' => true],
        ['label' => 'Stock Out', 'url' => base_url('pages/stock-out/index.php'), 'icon' => 'box-arrow-up-right', 'class' => 'btn-primary'],
    ]
);
?>

<div class="metrics-grid expiry-metrics">
    <article class="metric-card glass">
        <span class="stat-card-icon primary"><i class="bi bi-box-seam"></i></span>
        <small>Tracked batches</small>
        <strong><?= format_number($summary['total_batches']) ?></strong>
        <p><?= format_number($summary['total_qty'], 2) ?> units with expiry data</p>
    </article>
    <article class="metric-card glass">
        <span class="stat-card-icon danger"><i class="bi bi-x-octagon"></i></span>
        <small>Expired</small>
        <strong class="text-danger"><?= format_number($summary['expired']) ?></strong>
        <p>Remove, quarantine, or reconcile</p>
    </article>
    <article class="metric-card glass">
        <span class="stat-card-icon warning"><i class="bi bi-exclamation-triangle"></i></span>
        <small>Due within 30 days</small>
        <strong class="text-warning"><?= format_number($summary['urgent'] + $summary['warning']) ?></strong>
        <p><?= format_number($summary['risk_qty'], 2) ?> units at risk</p>
    </article>
    <article class="metric-card glass">
        <span class="stat-card-icon success"><i class="bi bi-check2-circle"></i></span>
        <small>Healthy</small>
        <strong class="text-success"><?= format_number($summary['healthy']) ?></strong>
        <p>More than 30 days remaining</p>
    </article>
</div>

<?php ob_start(); ?>
<div class="col-12 col-md-3">
    <label class="form-label">Search</label>
    <input type="text" class="form-control" name="q" placeholder="Item or lot..."
           value="<?= e($filters['q']) ?>">
</div>
<div class="col-12 col-md-3">
    <label class="form-label">Category</label>
    <select class="form-select" name="category_id">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>" <?= (string) $filters['category_id'] === (string) $cat['id'] ? 'selected' : '' ?>>
            <?= e($cat['name']) ?>
        </option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-12 col-md-3">
    <label class="form-label">Risk</label>
    <select class="form-select" name="status">
        <option value="">All Risks</option>
        <?php foreach (ExpiryTracking::STATUSES as $status): ?>
        <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>>
            <?= e(ExpiryTracking::statusLabel($status)) ?>
        </option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-12 col-md-3">
    <label class="form-label">Due Window</label>
    <select class="form-select" name="window">
        <?php
        $windows = ['all' => 'All dates', '0' => 'Expired only', '7' => 'Next 7 days', '30' => 'Next 30 days', '60' => 'Next 60 days', '90' => 'Next 90 days'];
        foreach ($windows as $value => $label):
        ?>
        <option value="<?= e($value) ?>" <?= $filters['window'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<?php
$searchFields = ob_get_clean();
$showReset = true;
$resetUrl = base_url('pages/expiry/index.php');
require APP_PATH . '/views/partials/search-card.php';
?>

<?php list_panel_open('EXPIRY', 'Batch Expiry Register', count($rows)); ?>
        <table class="table data-table data-table-mobile data-table-searchable mb-0">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Category</th>
                    <th>Lot</th>
                    <th>MFD</th>
                    <th>Expire</th>
                    <th>Remaining</th>
                    <th>Issued</th>
                    <th>Risk</th>
                    <th>Last In</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-5">
                        <i class="bi bi-calendar2-check d-block fs-2 mb-2 opacity-50"></i>
                        No expiring stock batches match the current filters.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($rows as $row): ?>
                <tr class="expiry-row expiry-row--<?= e($row['expiry_status']) ?>">
                    <td data-label="Item">
                        <span class="text-code"><?= e($row['item_no']) ?></span><br>
                        <span class="small"><?= e($row['item_name']) ?></span>
                    </td>
                    <td data-label="Category"><?= category_badge($row['category']) ?></td>
                    <td data-label="Lot">
                        <span class="small"><?= e($row['lot_numbers'] ?: 'No lot') ?></span>
                        <?php if ((int) $row['lot_count'] > 1): ?>
                        <span class="badge bg-secondary-subtle text-secondary ms-1"><?= (int) $row['lot_count'] ?> entries</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="MFD"><?= format_date($row['mfd_date']) ?></td>
                    <td data-label="Expire">
                        <strong><?= format_date($row['expire_date']) ?></strong><br>
                        <span class="small text-muted">
                            <?= (int) $row['days_to_expire'] < 0
                                ? format_number(abs((int) $row['days_to_expire'])) . ' days overdue'
                                : format_number((int) $row['days_to_expire']) . ' days left' ?>
                        </span>
                    </td>
                    <td data-label="Remaining">
                        <strong><?= format_number($row['remaining_qty'], 2) ?></strong> <?= e($row['unit']) ?>
                    </td>
                    <td data-label="Issued">
                        <span class="small"><?= format_number($row['issued_qty'], 2) ?> / <?= format_number($row['received_qty'], 2) ?></span>
                    </td>
                    <td data-label="Risk">
                        <span class="status expiry-status expiry-status--<?= e($row['expiry_status']) ?>">
                            <span class="status-dot"></span><?= e($row['expiry_label']) ?>
                        </span>
                    </td>
                    <td data-label="Last In"><span class="small"><?= format_datetime($row['last_received_at']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
<?php list_panel_table_close(); list_panel_close(); ?>
