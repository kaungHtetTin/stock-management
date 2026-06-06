<?php
require_once APP_PATH . '/models/User.php';
$filters = $filters ?? ['q' => '', 'role' => '', 'status' => ''];
$currentUserId = (int) (current_user()['id'] ?? 0);

page_header('Users', 'Manage admin and staff accounts', [
    ['label' => 'Add User', 'url' => base_url('pages/users/create.php'), 'icon' => 'person-plus'],
]);
?>

<div class="card card-filter mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="get">
            <div class="col-12 col-md-4">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" name="q" placeholder="Username or name"
                       value="<?= e($filters['q']) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Role</label>
                <select class="form-select" name="role">
                    <option value="">All Roles</option>
                    <?php foreach (User::ROLES as $role): ?>
                    <option value="<?= e($role) ?>" <?= $filters['role'] === $role ? 'selected' : '' ?>><?= ucfirst(e($role)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">All</option>
                    <?php foreach (User::STATUSES as $status): ?>
                    <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= ucfirst(e($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Search</button>
                <?php if ($filters['q'] !== '' || $filters['role'] !== '' || $filters['status'] !== ''): ?>
                <a href="<?= base_url('pages/users/index.php') ?>" class="btn btn-light">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card card-polished table-card">
    <div class="card-header card-header-polished">
        User List <span class="text-muted fw-normal">(<?= count($users) ?>)</span>
    </div>
    <div class="table-responsive">
        <table class="table data-table data-table-mobile mb-0">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-5">
                        <i class="bi bi-people d-block fs-2 mb-2 opacity-50"></i>
                        No users found.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($users as $row): ?>
                <?php $canDeactivate = User::canDeactivate((int) $row['id'], $currentUserId) && $row['status'] === 'active'; ?>
                <tr>
                    <td data-label="User">
                        <div class="d-flex align-items-center gap-2">
                            <div class="user-avatar" style="width:32px;height:32px;font-size:0.75rem">
                                <?= strtoupper(substr($row['display_name'], 0, 1)) ?>
                            </div>
                            <?= e($row['display_name']) ?>
                        </div>
                    </td>
                    <td data-label="Username"><span class="text-code"><?= e($row['username']) ?></span></td>
                    <td data-label="Role">
                        <span class="badge <?= $row['role'] === 'admin' ? 'bg-primary' : 'bg-secondary' ?>">
                            <?= e(ucfirst($row['role'])) ?>
                        </span>
                    </td>
                    <td data-label="Status"><?= status_badge($row['status']) ?></td>
                    <td data-label="Created"><span class="small text-muted"><?= format_datetime($row['created_at']) ?></span></td>
                    <td data-label="Actions" class="table-actions-cell">
                        <div class="table-actions">
                            <a href="<?= base_url('pages/users/edit.php?id=' . $row['id']) ?>" class="btn-action" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ($canDeactivate): ?>
                            <button class="btn-action danger" title="Deactivate"
                                    data-delete="<?= base_url('pages/users/index.php?deactivate=' . $row['id']) ?>"
                                    data-name="<?= e($row['display_name']) ?>"
                                    data-confirm-label="Deactivate">
                                <i class="bi bi-person-x"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require APP_PATH . '/views/partials/delete-modal.php'; ?>
