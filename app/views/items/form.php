<?php
$isEdit = !empty($item);
page_header(
    $isEdit ? 'Edit Item' : 'Add Item',
    $isEdit ? 'Update product information' : 'Register a new product',
    [['label' => 'Back to List', 'url' => base_url('pages/items/index.php'), 'icon' => 'arrow-left', 'class' => 'btn-outline-primary', 'outline' => true]]
);
?>

<form class="form-card" method="post" action="">
    <?= csrf_field() ?>
    <div class="form-section-title">Product Information</div>
    <div class="row g-3">
        <div class="col-12 col-md-4">
            <label class="form-label">Item No <span class="required">*</span></label>
            <input type="text" class="form-control" name="item_no"
                   value="<?= e($item['item_no'] ?? '') ?>" placeholder="e.g. ITM-006" required>
        </div>
        <div class="col-12 col-md-8">
            <label class="form-label">Item Name <span class="required">*</span></label>
            <input type="text" class="form-control" name="item_name"
                   value="<?= e($item['item_name'] ?? '') ?>" placeholder="Product name" required>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label">Category <span class="required">*</span></label>
            <select class="form-select" name="category_id" required>
                <option value="">Select category</option>
                <?php foreach (($categories ?? []) as $cat): ?>
                <option value="<?= $cat['id'] ?>"
                    <?= (int) ($item['category_id'] ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (is_admin()): ?>
            <div class="form-text"><a href="<?= base_url('pages/categories/index.php') ?>">Manage categories</a></div>
            <?php endif; ?>
        </div>
        <div class="col-6 col-md-4">
            <label class="form-label">Unit <span class="required">*</span></label>
            <input type="text" class="form-control" name="unit"
                   value="<?= e($item['unit'] ?? '') ?>" placeholder="kg, L, box, pcs" required>
        </div>
        <div class="col-6 col-md-4">
            <label class="form-label">Unit Price (MMK)</label>
            <input type="number" class="form-control" name="unit_price" min="0" step="1"
                   value="<?= e($item['unit_price'] ?? '') ?>" placeholder="Optional">
        </div>
        <div class="col-12">
            <label class="form-label">Remark</label>
            <textarea class="form-control" name="remark" rows="3"
                      placeholder="Optional notes"><?= e($item['remark'] ?? '') ?></textarea>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update Item' : 'Save Item' ?>
        </button>
        <a href="<?= base_url('pages/items/index.php') ?>" class="btn btn-light">Cancel</a>
    </div>
</form>
