<?php
$isEdit = !empty($record['anchor_id'])
    || (!empty($record['status']) && !empty($record['lines']));
$user = current_user();
$lines = $record['lines'] ?? [[]];
if (empty($lines)) {
    $lines = [[]];
}
$lineEditMode = $isEdit;

page_header(
    $isEdit ? 'Edit Stock In' : (is_admin() ? 'New Stock In' : 'Submit Stock In Request'),
    $isEdit
        ? ($record['is_batch'] ?? false
            ? 'Update all items in this batch together'
            : 'Update stock in record — balance recalculates automatically')
        : 'Add one or more items in a single submission',
    [['label' => 'Back to List', 'url' => base_url('pages/stock-in/index.php'), 'icon' => 'arrow-left', 'class' => 'btn-outline-primary', 'outline' => true]]
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
    <span>Editing an approved record will update stock balance automatically.</span>
</div>
<?php elseif (!is_admin() && !$isEdit): ?>
<div class="alert alert-info d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-info-circle me-2 fs-5"></i>
    <span>Your submission will be sent for <strong>admin approval</strong> before stock balance is updated.</span>
</div>
<?php endif; ?>

<form class="form-card glass" method="post" action="" id="stockInForm">
    <?= csrf_field() ?>

    <div class="form-section-title"><?= $isEdit ? 'Submission Details' : 'Submission Details' ?></div>
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6">
            <label class="form-label">In Charge Name <span class="required">*</span></label>
            <input type="text" class="form-control" name="in_charge_name"
                   value="<?= e($record['in_charge_name'] ?? $user['display_name'] ?? '') ?>" required>
        </div>
        <?php if ($isEdit && !empty($record['batch_ref'])): ?>
        <div class="col-12 col-md-6">
            <label class="form-label">Batch</label>
            <input type="text" class="form-control" value="<?= e($record['batch_ref']) ?> (<?= count($lines) ?> items)" readonly>
        </div>
        <?php endif; ?>
    </div>

    <div data-stock-lines<?= $isEdit ? ' data-edit-mode' : '' ?>>
        <div class="form-section-title">Items</div>

        <div data-line-container>
            <?php foreach ($lines as $index => $line): ?>
            <?php include APP_PATH . '/views/partials/stock-in-line-row.php'; ?>
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
        <a href="<?= base_url('pages/stock-in/index.php') ?>" class="btn btn-light">Cancel</a>
    </div>
</form>
