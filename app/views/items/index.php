<?php
$filters = $filters ?? ['q' => '', 'category_id' => ''];
$categories = $categories ?? [];
$actions = is_admin() ? [['label' => 'Add Item', 'url' => base_url('pages/items/create.php'), 'icon' => 'plus-lg']] : [];
page_header('Items', 'Product master — Fruits, Gelato & Icecream', $actions);

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
$resetUrl = base_url('pages/items/index.php');
require APP_PATH . '/views/partials/search-card.php';
?>

<div class="card card-polished table-card">
    <div class="card-header card-header-polished">
        <span>Item List <span class="text-muted fw-normal">(<?= count($items) ?>)</span></span>
        <input type="text" class="form-control form-control-sm" id="tableSearch"
               placeholder="Quick filter..." style="max-width:220px">
    </div>
    <div class="table-responsive">
        <table class="table data-table data-table-mobile data-table-searchable mb-0">
            <thead>
                <tr>
                    <th>Item No</th>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Balance</th>
                    <th>Unit</th>
                    <th>Unit Price</th>
                    <th>Created</th>
                    <?php if (is_admin()): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr>
                    <td colspan="<?= is_admin() ? 8 : 7 ?>" class="text-center text-muted py-5">
                        <i class="bi bi-inbox d-block fs-2 mb-2 opacity-50"></i>
                        No items found.<?php if (is_admin()): ?> <a href="<?= base_url('pages/items/create.php') ?>">Add your first item</a>.<?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($items as $row): ?>
                <tr>
                    <td data-label="Item No"><span class="text-code"><?= e($row['item_no']) ?></span></td>
                    <td data-label="Name"><?= e($row['item_name']) ?></td>
                    <td data-label="Category"><?= category_badge($row['category']) ?></td>
                    <td data-label="Balance">
                        <span class="<?= $row['balance'] < 15 ? 'balance-low' : 'balance-ok' ?>">
                            <?= format_number($row['balance']) ?>
                        </span>
                    </td>
                    <td data-label="Unit"><?= e($row['unit']) ?></td>
                    <td data-label="Price"><?= $row['unit_price'] ? format_number($row['unit_price']) . ' MMK' : '—' ?></td>
                    <td data-label="Created"><span class="small text-muted"><?= format_datetime($row['created_at']) ?></span></td>
                    <?php if (is_admin()): ?>
                    <td data-label="Actions" class="table-actions-cell">
                        <div class="table-actions">
                            <a href="<?= base_url('pages/items/edit.php?id=' . $row['id']) ?>" class="btn-action" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button class="btn-action danger" title="Delete"
                                    data-delete="<?= base_url('pages/items/index.php?delete=' . $row['id']) ?>"
                                    data-name="<?= e($row['item_name']) ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (is_admin()) require APP_PATH . '/views/partials/delete-modal.php'; ?>
