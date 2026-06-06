<?php
$filters = $filters ?? ['q' => '', 'type' => ''];
$actions = is_admin() ? [['label' => 'Add Customer', 'url' => base_url('pages/customers/create.php'), 'icon' => 'plus-lg']] : [];
page_header('Customers', 'Customer master — Retail & Whole Sale', $actions);

ob_start();
?>
<div class="col-12 col-md-4">
    <label class="form-label">Search</label>
    <input type="text" class="form-control" name="q" placeholder="Code or name..."
           value="<?= e($filters['q']) ?>">
</div>
<div class="col-12 col-md-3">
    <label class="form-label">Type</label>
    <select class="form-select" name="type">
        <option value="">All Types</option>
        <?php foreach (['Retail', 'Whole Sale'] as $type): ?>
        <option value="<?= e($type) ?>" <?= $filters['type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<?php
$searchFields = ob_get_clean();
$showReset = true;
$resetUrl = base_url('pages/customers/index.php');
require APP_PATH . '/views/partials/search-card.php';
?>

<div class="card card-polished table-card">
    <div class="card-header card-header-polished">
        <span>Customer List <span class="text-muted fw-normal">(<?= count($customers) ?>)</span></span>
        <input type="text" class="form-control form-control-sm" id="tableSearch"
               placeholder="Quick filter..." style="max-width:220px">
    </div>
    <div class="table-responsive">
        <table class="table data-table data-table-mobile data-table-searchable mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Address</th>
                    <th>Type</th>
                    <th>Created</th>
                    <?php if (is_admin()): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                <tr>
                    <td colspan="<?= is_admin() ? 6 : 5 ?>" class="text-center text-muted py-5">
                        <i class="bi bi-inbox d-block fs-2 mb-2 opacity-50"></i>
                        No customers found.<?php if (is_admin()): ?> <a href="<?= base_url('pages/customers/create.php') ?>">Add your first customer</a>.<?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($customers as $row): ?>
                <tr>
                    <td data-label="Code"><span class="text-code"><?= e($row['customer_code']) ?></span></td>
                    <td data-label="Name"><?= e($row['customer_name']) ?></td>
                    <td data-label="Address"><span class="small"><?= e($row['address']) ?></span></td>
                    <td data-label="Type"><?= customer_type_badge($row['customer_type']) ?></td>
                    <td data-label="Created"><span class="small text-muted"><?= format_datetime($row['created_at']) ?></span></td>
                    <?php if (is_admin()): ?>
                    <td data-label="Actions" class="table-actions-cell">
                        <div class="table-actions">
                            <a href="<?= base_url('pages/customers/edit.php?id=' . $row['id']) ?>" class="btn-action" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button class="btn-action danger" title="Delete"
                                    data-delete="<?= base_url('pages/customers/index.php?delete=' . $row['id']) ?>"
                                    data-name="<?= e($row['customer_name']) ?>">
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
