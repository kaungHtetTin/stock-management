<?php
require_once APP_PATH . '/models/StockIn.php';
$filters = $filters ?? ['date_from' => '', 'date_to' => '', 'status' => '', 'item' => ''];
page_header(
    'Stock In',
    is_admin() ? 'Record and approve incoming stock' : 'Submit stock in requests for approval',
    [['label' => is_admin() ? 'New Stock In' : 'Submit Request', 'url' => base_url('pages/stock-in/create.php'), 'icon' => 'plus-lg']]
);

ob_start();
?>
<div class="col-12 col-md-3">
    <label class="form-label">Date From</label>
    <input type="date" class="form-control" name="date_from" value="<?= e($filters['date_from']) ?>">
</div>
<div class="col-12 col-md-3">
    <label class="form-label">Date To</label>
    <input type="date" class="form-control" name="date_to" value="<?= e($filters['date_to']) ?>">
</div>
<div class="col-12 col-md-3">
    <label class="form-label">Status</label>
    <select class="form-select" name="status">
        <option value="">All</option>
        <?php foreach (['pending', 'approved', 'rejected'] as $s): ?>
        <option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= ucfirst(e($s)) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-12 col-md-3">
    <label class="form-label">Item</label>
    <input type="text" class="form-control" name="item" placeholder="Item no or name"
           value="<?= e($filters['item']) ?>">
</div>
<?php
$searchFields = ob_get_clean();
$showReset = true;
$resetUrl = base_url('pages/stock-in/index.php');
require APP_PATH . '/views/partials/search-card.php';
?>

<div class="card card-polished table-card">
    <div class="card-header card-header-polished">
        <span>Stock In Records <span class="text-muted fw-normal">(<?= count($records) ?>)</span></span>
        <input type="text" class="form-control form-control-sm" id="tableSearch"
               placeholder="Quick filter..." style="max-width:220px">
    </div>
    <div class="table-responsive">
        <table class="table data-table data-table-mobile data-table-searchable mb-0">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Lot No</th>
                    <th>MFD</th>
                    <th>Expire</th>
                    <th>Qty</th>
                    <th>In Charge</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-5">
                        <i class="bi bi-inbox d-block fs-2 mb-2 opacity-50"></i>
                        No stock in records found.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($records as $row): ?>
                <?php $canModify = StockIn::canModify($row); ?>
                <tr>
                    <td data-label="Item">
                        <span class="text-code"><?= e($row['item_no']) ?></span><br>
                        <span class="small"><?= e($row['item_name']) ?></span>
                    </td>
                    <td data-label="Lot"><?= e($row['lot_no'] ?: '—') ?></td>
                    <td data-label="MFD"><?= format_date($row['mfd_date']) ?></td>
                    <td data-label="Expire"><?= format_date($row['expire_date']) ?></td>
                    <td data-label="Qty"><strong><?= format_number($row['qty']) ?></strong> <?= e($row['unit']) ?></td>
                    <td data-label="In Charge"><?= e($row['in_charge']) ?></td>
                    <td data-label="Status">
                        <?= status_badge($row['status']) ?>
                        <?php if ($row['status'] === 'rejected' && !empty($row['rejection_reason'])): ?>
                        <div class="small text-muted mt-1" title="<?= e($row['rejection_reason']) ?>">
                            <i class="bi bi-chat-left-text me-1"></i><?= e(mb_strimwidth($row['rejection_reason'], 0, 40, '…')) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td data-label="Created">
                        <span class="small text-muted"><?= format_datetime($row['created_at']) ?></span><br>
                        <span class="small"><?= e($row['created_by']) ?></span>
                    </td>
                    <td data-label="Actions" class="table-actions-cell">
                        <div class="table-actions">
                            <?php if ($row['status'] === 'pending' && is_admin()): ?>
                            <button class="btn-action success" title="Approve"
                                    data-approve
                                    data-approve-url="<?= base_url('pages/stock-in/index.php?approve=' . $row['id']) ?>"
                                    data-name="<?= e($row['item_name']) ?>">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <button class="btn-action danger" title="Reject"
                                    data-reject
                                    data-reject-id="<?= $row['id'] ?>"
                                    data-reject-url="<?= base_url('pages/stock-in/index.php') ?>"
                                    data-name="<?= e($row['item_name']) ?>">
                                <i class="bi bi-x-lg"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ($canModify): ?>
                            <a href="<?= base_url('pages/stock-in/edit.php?id=' . $row['id']) ?>" class="btn-action" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button class="btn-action danger" title="Delete"
                                    data-delete="<?= base_url('pages/stock-in/index.php?delete=' . $row['id']) ?>"
                                    data-name="<?= e($row['item_name']) ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require APP_PATH . '/views/partials/delete-modal.php';
require APP_PATH . '/views/partials/approval-modal.php';
?>
