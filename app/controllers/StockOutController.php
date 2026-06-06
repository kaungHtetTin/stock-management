<?php
/**
 * Stock Out module — CRUD
 */

require_once APP_PATH . '/models/StockOut.php';
require_once APP_PATH . '/models/Item.php';
require_once APP_PATH . '/models/Customer.php';
require_once APP_PATH . '/services/ApprovalService.php';

class StockOutController
{
    public function index(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject') {
            require_csrf('pages/stock-out/index.php');
            $this->reject((int) ($_POST['id'] ?? 0), trim($_POST['reason'] ?? ''));
            return;
        }

        if (isset($_GET['approve'])) {
            $this->approve((int) $_GET['approve']);
            return;
        }

        if (isset($_GET['delete'])) {
            $this->destroy((int) $_GET['delete']);
            return;
        }

        $filters = [
            'date_from' => $_GET['date_from'] ?? '',
            'date_to'   => $_GET['date_to'] ?? '',
            'status'    => $_GET['status'] ?? '',
            'reason'    => $_GET['reason'] ?? '',
            'customer'  => trim($_GET['customer'] ?? ''),
            'item'      => trim($_GET['item'] ?? ''),
        ];

        render_app('stock-out/index.php', [
            'pageTitle'    => 'Stock Out — ' . APP_NAME,
            'currentNav'   => 'stock-out',
            'breadcrumbs'  => [['label' => 'Stock Out']],
            'pendingBadge' => is_admin() ? StockIn::countPending() + StockOut::countPending() : 0,
            'records'      => StockOut::all($filters),
            'filters'      => $filters,
        ]);
    }

    public function create(): void
    {
        require_login();

        $items = Item::all();
        $customers = Customer::all();

        if (empty($items)) {
            flash('error', 'No items available. Please add items first.');
            redirect('pages/items/index.php');
        }
        if (empty($customers)) {
            flash('error', 'No customers available. Please add customers first.');
            redirect('pages/customers/index.php');
        }

        render_app('stock-out/form.php', [
            'pageTitle'   => (is_admin() ? 'New Stock Out' : 'Submit Stock Out') . ' — ' . APP_NAME,
            'currentNav'  => 'stock-out',
            'breadcrumbs' => [
                ['label' => 'Stock Out', 'url' => base_url('pages/stock-out/index.php')],
                ['label' => 'New'],
            ],
            'items'       => $items,
            'customers'   => $customers,
            'record'      => $_SESSION['form_old'] ?? null,
        ]);
        unset($_SESSION['form_old']);
    }

    public function store(): void
    {
        require_login();
        require_csrf('pages/stock-out/create.php');

        $data = StockOut::normalize($_POST);
        $errors = StockOut::validate($_POST);

        if ($errors) {
            $_SESSION['form_old'] = $data;
            flash('error', implode(' ', $errors));
            redirect('pages/stock-out/create.php');
        }

        $userId = (int) current_user()['id'];
        StockOut::create(StockOut::createPayload($data, $userId));

        $msg = is_admin()
            ? 'Stock Out recorded and approved.'
            : 'Stock Out request submitted for approval.';
        flash('success', $msg);
        redirect('pages/stock-out/index.php');
    }

    public function edit(int $id): void
    {
        require_login();

        $record = StockOut::find($id);
        if (!$record || !StockOut::canModify($record)) {
            flash('error', 'Record not found or cannot be edited.');
            redirect('pages/stock-out/index.php');
        }

        if (!empty($_SESSION['form_old'])) {
            $record = array_merge($record, $_SESSION['form_old']);
            unset($_SESSION['form_old']);
        }

        render_app('stock-out/form.php', [
            'pageTitle'   => 'Edit Stock Out — ' . APP_NAME,
            'currentNav'  => 'stock-out',
            'breadcrumbs' => [
                ['label' => 'Stock Out', 'url' => base_url('pages/stock-out/index.php')],
                ['label' => 'Edit'],
            ],
            'items'       => Item::all(),
            'customers'   => Customer::all(),
            'record'      => $record,
        ]);
    }

    public function update(int $id): void
    {
        require_login();
        require_csrf('pages/stock-out/edit.php?id=' . $id);

        $record = StockOut::find($id);
        if (!$record || !StockOut::canModify($record)) {
            flash('error', 'Record not found or cannot be edited.');
            redirect('pages/stock-out/index.php');
        }

        $data = StockOut::normalize($_POST);
        $errors = StockOut::validate($_POST);

        if ($errors) {
            $_SESSION['form_old'] = array_merge($data, ['id' => $id]);
            flash('error', implode(' ', $errors));
            redirect('pages/stock-out/edit.php?id=' . $id);
        }

        StockOut::update($id, $data);
        flash('success', 'Stock Out request updated.');
        redirect('pages/stock-out/index.php');
    }

    public function destroy(int $id): void
    {
        require_login();

        $record = StockOut::find($id);
        if (!$record || !StockOut::canModify($record)) {
            flash('error', 'Record not found or cannot be deleted.');
            redirect('pages/stock-out/index.php');
        }

        if (!StockOut::delete($id)) {
            flash('error', 'Unable to delete record.');
            redirect('pages/stock-out/index.php');
        }

        flash('success', 'Stock Out request deleted.');
        redirect('pages/stock-out/index.php');
    }

    public function approve(int $id): void
    {
        require_admin();

        $result = ApprovalService::approveStockOut($id, (int) current_user()['id']);
        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('pages/stock-out/index.php');
    }

    public function reject(int $id, string $reason): void
    {
        require_admin();

        $result = ApprovalService::rejectStockOut($id, (int) current_user()['id'], $reason);
        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('pages/stock-out/index.php');
    }
}

require_once APP_PATH . '/models/StockIn.php';
