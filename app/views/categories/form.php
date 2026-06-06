<?php
$isEdit = !empty($category);
page_header(
    $isEdit ? 'Edit Category' : 'Add Category',
    $isEdit ? 'Update category name and sort order' : 'Create a new item category',
    [['label' => 'Back to List', 'url' => base_url('pages/categories/index.php'), 'icon' => 'arrow-left', 'class' => 'btn-outline-primary', 'outline' => true]]
);
?>

<form class="form-card" method="post" action="">
    <?= csrf_field() ?>
    <div class="form-section-title">Category Details</div>
    <div class="row g-3">
        <div class="col-12 col-md-8">
            <label class="form-label">Category Name <span class="required">*</span></label>
            <input type="text" class="form-control" name="name"
                   value="<?= e($category['name'] ?? '') ?>" placeholder="e.g. Fruits, Gelato" maxlength="50" required>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label">Sort Order</label>
            <input type="number" class="form-control" name="sort_order" min="0" step="1"
                   value="<?= e($category['sort_order'] ?? '0') ?>" placeholder="0">
            <div class="form-text">Lower numbers appear first in lists and charts.</div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update Category' : 'Save Category' ?>
        </button>
        <a href="<?= base_url('pages/categories/index.php') ?>" class="btn btn-light">Cancel</a>
    </div>
</form>
