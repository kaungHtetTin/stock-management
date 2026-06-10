<?php
require_once APP_PATH . '/models/User.php';
$isEdit = !empty($user);
page_header(
    $isEdit ? 'Edit User' : 'Add User',
    $isEdit ? 'Update account details' : 'Create a new system user',
    [['label' => 'Back to List', 'url' => base_url('pages/users/index.php'), 'icon' => 'arrow-left', 'class' => 'btn-outline-primary', 'outline' => true]]
);
?>

<form class="form-card glass" method="post" action="">
    <?= csrf_field() ?>
    <div class="form-section-title">Account Details</div>
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <label class="form-label">Display Name <span class="required">*</span></label>
            <input type="text" class="form-control" name="display_name"
                   value="<?= e($user['display_name'] ?? '') ?>" placeholder="e.g. Staff-1" required>
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label">Username <span class="required">*</span></label>
            <input type="text" class="form-control" name="username"
                   value="<?= e($user['username'] ?? '') ?>" <?= $isEdit ? 'readonly' : '' ?> required>
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label">Password <?php if (!$isEdit): ?><span class="required">*</span><?php endif; ?></label>
            <input type="password" class="form-control" name="password"
                   placeholder="<?= $isEdit ? 'Leave blank to keep current' : 'Min. 8 characters' ?>"
                   <?= $isEdit ? '' : 'required minlength="8"' ?>>
        </div>
        <?php
        $isPrimaryAdmin = $isEdit && (int) ($user['id'] ?? 0) === User::PRIMARY_ADMIN_ID;
        $isSelf = $isEdit && (int) ($user['id'] ?? 0) === (int) (current_user()['id'] ?? 0);
        ?>
        <div class="col-6 col-md-3">
            <label class="form-label">Role <span class="required">*</span></label>
            <select class="form-select" name="role" required <?= $isPrimaryAdmin ? 'disabled' : '' ?>>
                <option value="">Select</option>
                <option value="admin" <?= ($user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="staff" <?= ($user['role'] ?? '') === 'staff' ? 'selected' : '' ?>>Staff</option>
            </select>
            <?php if ($isPrimaryAdmin): ?>
            <input type="hidden" name="role" value="admin">
            <?php endif; ?>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">Status <span class="required">*</span></label>
            <select class="form-select" name="status" required <?= ($isPrimaryAdmin || $isSelf) ? 'disabled' : '' ?>>
                <option value="active" <?= ($user['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($user['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <?php if ($isPrimaryAdmin || $isSelf): ?>
            <input type="hidden" name="status" value="active">
            <?php endif; ?>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update User' : 'Create User' ?>
        </button>
        <a href="<?= base_url('pages/users/index.php') ?>" class="btn btn-light">Cancel</a>
    </div>
</form>
