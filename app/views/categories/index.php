<?php
$filters = $filters ?? ['q' => ''];
page_header('Categories', 'Manage item product categories', [
    ['label' => 'Add Category', 'url' => base_url('pages/categories/create.php'), 'icon' => 'plus-lg'],
]);

ob_start();
?>
<div class="col-12 col-md-4">
    <label class="form-label">Search</label>
    <input type="text" class="form-control" name="q" placeholder="Category name..."
           value="<?= e($filters['q']) ?>">
</div>
<?php
$searchFields = ob_get_clean();
$showReset = true;
$resetUrl = base_url('pages/categories/index.php');
require APP_PATH . '/views/partials/search-card.php';
?>

<?php list_panel_open('INVENTORY', 'Category List', count($categories)); ?>
        <table class="table data-table data-table-mobile data-table-searchable mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Sort Order</th>
                    <th>Items</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted py-5">
                        <i class="bi bi-tags d-block fs-2 mb-2 opacity-50"></i>
                        No categories found. <a href="<?= base_url('pages/categories/create.php') ?>">Add your first category</a>.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($categories as $row): ?>
                <tr>
                    <td data-label="Name"><?= category_badge($row['name']) ?></td>
                    <td data-label="Sort"><?= (int) $row['sort_order'] ?></td>
                    <td data-label="Items"><span class="badge bg-light text-dark"><?= (int) $row['item_count'] ?></span></td>
                    <td data-label="Created"><span class="small text-muted"><?= format_datetime($row['created_at']) ?></span></td>
                    <td data-label="Actions" class="table-actions-cell">
                        <div class="table-actions">
                            <a href="<?= base_url('pages/categories/edit.php?id=' . $row['id']) ?>" class="btn-action" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ((int) $row['item_count'] === 0): ?>
                            <button class="btn-action danger" title="Delete"
                                    data-delete="<?= base_url('pages/categories/index.php?delete=' . $row['id']) ?>"
                                    data-name="<?= e($row['name']) ?>">
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
<?php list_panel_table_close(); list_panel_close(); ?>

<?php require APP_PATH . '/views/partials/delete-modal.php'; ?>
