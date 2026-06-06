<?php
/**
 * User management (admin only)
 */

require_once APP_PATH . '/models/User.php';
require_once APP_PATH . '/models/StockIn.php';
require_once APP_PATH . '/models/StockOut.php';

class UserController
{
    public function index(): void
    {
        require_admin();

        if (isset($_GET['deactivate'])) {
            $this->deactivate((int) $_GET['deactivate']);
            return;
        }

        $filters = [
            'q'      => trim($_GET['q'] ?? ''),
            'role'   => $_GET['role'] ?? '',
            'status' => $_GET['status'] ?? '',
        ];

        render_app('users/index.php', [
            'pageTitle'    => 'Users — ' . APP_NAME,
            'currentNav'   => 'users',
            'breadcrumbs'  => [['label' => 'Users']],
            'pendingBadge' => StockIn::countPending() + StockOut::countPending(),
            'users'        => User::all($filters),
            'filters'      => $filters,
        ]);
    }

    public function create(): void
    {
        require_admin();

        render_app('users/form.php', [
            'pageTitle'   => 'Add User — ' . APP_NAME,
            'currentNav'  => 'users',
            'breadcrumbs' => [
                ['label' => 'Users', 'url' => base_url('pages/users/index.php')],
                ['label' => 'Add'],
            ],
            'user'        => $_SESSION['form_old'] ?? null,
        ]);
        unset($_SESSION['form_old']);
    }

    public function store(): void
    {
        require_admin();
        require_csrf('pages/users/create.php');

        $data = User::normalize($_POST);
        $errors = User::validate($_POST);

        if ($errors) {
            $_SESSION['form_old'] = $data;
            flash('error', implode(' ', $errors));
            redirect('pages/users/create.php');
        }

        User::create([
            'username'      => $data['username'],
            'password_hash' => User::hashPassword($data['password']),
            'display_name'  => $data['display_name'],
            'role'          => $data['role'],
            'status'        => $data['status'],
        ]);

        flash('success', 'User "' . $data['display_name'] . '" created successfully.');
        redirect('pages/users/index.php');
    }

    public function edit(int $id): void
    {
        require_admin();

        $user = User::findById($id);
        if (!$user || !User::canModify($id)) {
            flash('error', 'User not found.');
            redirect('pages/users/index.php');
        }

        if (!empty($_SESSION['form_old'])) {
            $user = array_merge($user, $_SESSION['form_old']);
            unset($_SESSION['form_old']);
        }

        render_app('users/form.php', [
            'pageTitle'   => 'Edit User — ' . APP_NAME,
            'currentNav'  => 'users',
            'breadcrumbs' => [
                ['label' => 'Users', 'url' => base_url('pages/users/index.php')],
                ['label' => 'Edit'],
            ],
            'user'        => $user,
        ]);
    }

    public function update(int $id): void
    {
        require_admin();
        require_csrf('pages/users/edit.php?id=' . $id);

        $user = User::findById($id);
        if (!$user || !User::canModify($id)) {
            flash('error', 'User not found.');
            redirect('pages/users/index.php');
        }

        $data = User::normalize(array_merge($_POST, ['username' => $user['username']]));
        $errors = User::validate(array_merge($_POST, ['username' => $user['username']]), true, $id);

        if ($errors) {
            $_SESSION['form_old'] = array_merge($data, ['id' => $id]);
            flash('error', implode(' ', $errors));
            redirect('pages/users/edit.php?id=' . $id);
        }

        $payload = [
            'display_name' => $data['display_name'],
            'role'         => $data['role'],
            'status'       => $data['status'],
        ];

        if ($data['password'] !== '') {
            $payload['password_hash'] = User::hashPassword($data['password']);
        }

        User::update($id, $payload);

        if ($id === (int) current_user()['id']) {
            $_SESSION['user'] = User::toSessionUser(User::findById($id));
        }

        flash('success', 'User "' . $data['display_name'] . '" updated successfully.');
        redirect('pages/users/index.php');
    }

    public function deactivate(int $id): void
    {
        require_admin();

        $currentId = (int) current_user()['id'];

        if (!User::canDeactivate($id, $currentId)) {
            flash('error', 'This user cannot be deactivated.');
            redirect('pages/users/index.php');
        }

        if (!User::deactivate($id)) {
            flash('error', 'Unable to deactivate user.');
            redirect('pages/users/index.php');
        }

        $user = User::findById($id);
        flash('success', 'User "' . ($user['display_name'] ?? 'Account') . '" has been deactivated.');
        redirect('pages/users/index.php');
    }
}
