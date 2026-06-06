<?php
$isEdit = !empty($record);
$user = current_user();
page_header(
    $isEdit ? 'Edit Stock In' : (is_admin() ? 'New Stock In' : 'Submit Stock In Request'),
    $isEdit ? 'Update pending stock in record' : 'Record incoming stock details',
    [['label' => 'Back to List', 'url' => base_url('pages/stock-in/index.php'), 'icon' => 'arrow-left', 'class' => 'btn-outline-primary', 'outline' => true]]
);

if (!is_admin() && !$isEdit):
?>
<div class="alert alert-info d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-info-circle me-2 fs-5"></i>
    <span>Your submission will be sent for <strong>admin approval</strong> before stock balance is updated.</span>
</div>
<?php endif; ?>

<form class="form-card" method="post" action="">
    <?= csrf_field() ?>
    <div class="form-section-title">Item Details</div>
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <label class="form-label">Item <span class="required">*</span></label>
            <select class="form-select" name="item_id" data-item-select required>
                <option value="">Select item</option>
                <?php foreach ($items as $it): ?>
                <option value="<?= $it['id'] ?>"
                        data-unit="<?= e($it['unit']) ?>"
                        data-name="<?= e($it['item_name']) ?>"
                        <?= (int) ($record['item_id'] ?? 0) === (int) $it['id'] ? 'selected' : '' ?>>
                    <?= e($it['item_no']) ?> — <?= e($it['item_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label">Item Name</label>
            <input type="text" class="form-control" data-item-name readonly
                   value="<?= e($record['item_name'] ?? '') ?>">
        </div>
    </div>

    <div class="form-section-title mt-4">Batch & Quantity</div>
    <div class="row g-3">
        <div class="col-6 col-md-3">
            <label class="form-label">MFD Date</label>
            <input type="date" class="form-control" name="mfd_date"
                   value="<?= e($record['mfd_date'] ?? '') ?>">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">Expire Date</label>
            <input type="date" class="form-control" name="expire_date"
                   value="<?= e($record['expire_date'] ?? '') ?>">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Lot No</label>
            <input type="text" class="form-control" name="lot_no"
                   value="<?= e($record['lot_no'] ?? '') ?>" placeholder="e.g. LOT-G-0524">
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
        <div class="col-6 col-md-3">
            <label class="form-label">Worker Qty</label>
            <input type="number" class="form-control" name="worker_qty" min="0" step="0.01"
                   value="<?= e($record['worker_qty'] ?? '') ?>" placeholder="Optional">
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label">In Charge Name <span class="required">*</span></label>
            <input type="text" class="form-control" name="in_charge_name"
                   value="<?= e($record['in_charge_name'] ?? $record['in_charge'] ?? $user['display_name'] ?? '') ?>" required>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>
            <?= $isEdit ? 'Update' : (is_admin() ? 'Save & Approve' : 'Submit Request') ?>
        </button>
        <a href="<?= base_url('pages/stock-in/index.php') ?>" class="btn btn-light">Cancel</a>
    </div>
</form>
