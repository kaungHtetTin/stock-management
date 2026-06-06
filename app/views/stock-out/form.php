<?php
require_once APP_PATH . '/models/StockOut.php';
$isEdit = !empty($record);
page_header(
    $isEdit ? 'Edit Stock Out' : (is_admin() ? 'New Stock Out' : 'Submit Stock Out Request'),
    $isEdit ? 'Update pending stock out record' : 'Record outgoing stock to customer',
    [['label' => 'Back to List', 'url' => base_url('pages/stock-out/index.php'), 'icon' => 'arrow-left', 'class' => 'btn-outline-primary', 'outline' => true]]
);

if (!is_admin() && !$isEdit):
?>
<div class="alert alert-info d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-info-circle me-2 fs-5"></i>
    <span>Stock will be deducted only after <strong>admin approval</strong>. Quantity is validated against current balance.</span>
</div>
<?php endif; ?>

<form class="form-card" method="post" action="">
    <?= csrf_field() ?>
    <div class="form-section-title">Item & Customer</div>
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <label class="form-label">Item <span class="required">*</span></label>
            <select class="form-select" name="item_id" data-item-select required>
                <option value="">Select item</option>
                <?php foreach ($items as $it): ?>
                <option value="<?= $it['id'] ?>"
                        data-unit="<?= e($it['unit']) ?>"
                        data-name="<?= e($it['item_name']) ?>"
                        data-balance="<?= $it['balance'] ?>"
                        <?= (int) ($record['item_id'] ?? 0) === (int) $it['id'] ? 'selected' : '' ?>>
                    <?= e($it['item_no']) ?> — <?= e($it['item_name']) ?> (Bal: <?= format_number($it['balance']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
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
    </div>

    <div class="form-section-title mt-4">Quantity & Reason</div>
    <div class="row g-3">
        <div class="col-6 col-md-3">
            <label class="form-label">MFD Date</label>
            <input type="date" class="form-control" name="mfd_date"
                   value="<?= e($record['mfd_date'] ?? '') ?>">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">Qty <span class="required">*</span></label>
            <input type="number" class="form-control" name="qty" min="0.01" step="0.01"
                   value="<?= e($record['qty'] ?? '') ?>" required>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">Unit <span class="required">*</span></label>
            <input type="text" class="form-control" name="unit" data-item-unit
                   value="<?= e($record['unit'] ?? '') ?>" required>
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
        <div class="col-12">
            <label class="form-label">Remark</label>
            <textarea class="form-control" name="remark" rows="2"
                      placeholder="Required if reason is Other"><?= e($record['remark'] ?? '') ?></textarea>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>
            <?= $isEdit ? 'Update' : (is_admin() ? 'Save & Approve' : 'Submit Request') ?>
        </button>
        <a href="<?= base_url('pages/stock-out/index.php') ?>" class="btn btn-light">Cancel</a>
    </div>
</form>
