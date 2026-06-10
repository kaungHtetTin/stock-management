<?php
require_once APP_PATH . '/models/StockOut.php';
$isEdit = !empty($record['anchor_id'])
    || (!empty($record['status']) && !empty($record['lines']));
$lines = $record['lines'] ?? [[]];
if (empty($lines)) {
    $lines = [[]];
}
$lineEditMode = $isEdit;

page_header(
    $isEdit ? 'Edit Stock Out' : (is_admin() ? 'New Stock Out' : 'Submit Stock Out Request'),
    $isEdit
        ? ($record['is_batch'] ?? false
            ? 'Update all items in this batch together'
            : 'Update stock out record — balance recalculates automatically')
        : 'Issue one or more items to a customer in one submission',
    [['label' => 'Back to List', 'url' => base_url('pages/stock-out/index.php'), 'icon' => 'arrow-left', 'class' => 'btn-outline-primary', 'outline' => true]]
);

if ($isEdit && ($record['status'] ?? '') === 'rejected'):
?>
<div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-arrow-repeat me-2 fs-5"></i>
    <span>This record was rejected. Saving will reset it to <strong>pending</strong> for approval.</span>
</div>
<?php elseif ($isEdit && ($record['status'] ?? '') === 'approved'): ?>
<div class="alert alert-info d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-info-circle me-2 fs-5"></i>
    <span>Editing an approved record will update stock balance automatically. Quantity is validated against current balance.</span>
</div>
<?php elseif (!is_admin() && !$isEdit): ?>
<div class="alert alert-info d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-info-circle me-2 fs-5"></i>
    <span>Stock will be deducted only after <strong>admin approval</strong>. Quantity is validated against current balance.</span>
</div>
<?php endif; ?>

<form class="form-card glass" method="post" action="" id="stockOutForm">
    <?= csrf_field() ?>

    <div class="form-section-title">Customer & Reason</div>
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6">
            <label class="form-label">Customer <span class="required">*</span></label>
            <select class="form-select" name="customer_id" required>
                <option value="">Select customer</option>
                <?php foreach ($customers as $c): ?>
                <option value="<?= $c['id'] ?>"
                        <?= (int) ($record['customer_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                    <?= e($c['customer_code']) ?> — <?= e($c['customer_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Reason <span class="required">*</span></label>
            <select class="form-select" name="reason" required>
                <option value="">Select reason</option>
                <?php foreach (StockOut::REASONS as $r): ?>
                <option value="<?= e($r) ?>" <?= ($record['reason'] ?? '') === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($isEdit && !empty($record['batch_ref'])): ?>
        <div class="col-12 col-md-3">
            <label class="form-label">Batch</label>
            <input type="text" class="form-control" value="<?= e($record['batch_ref']) ?> (<?= count($lines) ?> items)" readonly>
        </div>
        <?php endif; ?>
        <div class="col-12">
            <label class="form-label">Remark</label>
            <textarea class="form-control" name="remark" rows="2"
                      placeholder="Required if reason is Other"><?= e($record['remark'] ?? '') ?></textarea>
        </div>
    </div>

    <div data-stock-lines<?= $isEdit ? ' data-edit-mode' : '' ?>>
        <div class="form-section-title">Items</div>

        <div data-line-container>
            <?php foreach ($lines as $index => $line): ?>
            <?php include APP_PATH . '/views/partials/stock-out-line-row.php'; ?>
            <?php endforeach; ?>
        </div>

        <?php if (!$isEdit): ?>
        <div class="d-grid d-sm-flex mt-2">
            <button type="button" class="btn btn-outline-primary" data-add-line>
                <i class="bi bi-plus-lg me-1"></i>Add Item
            </button>
        </div>
        <?php endif; ?>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>
            <?= $isEdit
                ? (($record['status'] ?? '') === 'rejected' ? 'Update & Resubmit' : 'Update')
                : (is_admin() ? 'Save & Approve' : 'Submit Request') ?>
        </button>
        <a href="<?= base_url('pages/stock-out/index.php') ?>" class="btn btn-light">Cancel</a>
    </div>
</form>
