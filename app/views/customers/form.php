<?php
$isEdit = !empty($customer);
page_header(
    $isEdit ? 'Edit Customer' : 'Add Customer',
    $isEdit ? 'Update customer details' : 'Register a new customer',
    [['label' => 'Back to List', 'url' => base_url('pages/customers/index.php'), 'icon' => 'arrow-left', 'class' => 'btn-outline-primary', 'outline' => true]]
);
?>

<form class="form-card" method="post" action="">
    <?= csrf_field() ?>
    <div class="form-section-title">Customer Information</div>
    <div class="row g-3">
        <div class="col-12 col-md-4">
            <label class="form-label">Customer Code <span class="required">*</span></label>
            <input type="text" class="form-control" name="customer_code"
                   value="<?= e($customer['customer_code'] ?? '') ?>" placeholder="e.g. CUS-005" required>
        </div>
        <div class="col-12 col-md-8">
            <label class="form-label">Customer Name <span class="required">*</span></label>
            <input type="text" class="form-control" name="customer_name"
                   value="<?= e($customer['customer_name'] ?? '') ?>" required>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label">Phone</label>
            <input type="tel" class="form-control" name="phone"
                   value="<?= e($customer['phone'] ?? '') ?>" placeholder="e.g. 09xxxxxxxxx" maxlength="30">
        </div>
        <div class="col-12">
            <label class="form-label">Customer Address</label>
            <textarea class="form-control" name="address" rows="2"
                      placeholder="Full address"><?= e($customer['address'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
            <label class="form-label">Remark</label>
            <textarea class="form-control" name="remark" rows="2"
                      placeholder="Notes about this customer"><?= e($customer['remark'] ?? '') ?></textarea>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label">Customer Type <span class="required">*</span></label>
            <select class="form-select" name="customer_type" required>
                <option value="">Select type</option>
                <?php foreach (['Retail', 'Whole Sale'] as $type): ?>
                <option value="<?= e($type) ?>" <?= ($customer['customer_type'] ?? '') === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update Customer' : 'Save Customer' ?>
        </button>
        <a href="<?= base_url('pages/customers/index.php') ?>" class="btn btn-light">Cancel</a>
    </div>
</form>
