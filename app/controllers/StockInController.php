<?php
/**
 * Stock In module — CRUD
 */

require_once APP_PATH . '/models/StockIn.php';
require_once APP_PATH . '/models/Item.php';
require_once APP_PATH . '/services/ApprovalService.php';

class StockInController
{
    public function index(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject') {
            require_csrf('pages/stock-in/index.php');
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
            'item'      => trim($_GET['item'] ?? ''),
        ];

        render_app('stock-in/index.php', [
            'pageTitle'    => 'Stock In — ' . APP_NAME,
            'currentNav'   => 'stock-in',
            'breadcrumbs'  => [['label' => 'Stock In']],
            'pendingBadge' => is_admin() ? StockIn::countPending() + StockOut::countPending() : 0,
            'records'      => StockIn::all($filters),
            'filters'      => $filters,
        ]);
    }

    public function create(): void
    {
        require_login();

        $items = Item::all();
        if (empty($items)) {
            flash('error', 'No items available. Please add items first.');
            redirect('pages/items/index.php');
        }

        render_app('stock-in/form.php', [
            'pageTitle'   => (is_admin() ? 'New Stock In' : 'Submit Stock In') . ' — ' . APP_NAME,
            'currentNav'  => 'stock-in',
            'breadcrumbs' => [
                ['label' => 'Stock In', 'url' => base_url('pages/stock-in/index.php')],
                ['label' => 'New'],
            ],
            'items'       => $items,
            'record'      => $_SESSION['form_old'] ?? null,
        ]);
        unset($_SESSION['form_old']);
    }

    public function store(): void
    {
        require_login();
        require_csrf('pages/stock-in/create.php');

        $data = StockIn::normalize($_POST);
        $errors = StockIn::validate($_POST);

        if ($errors) {
            $_SESSION['form_old'] = $data;
            flash('error', implode(' ', $errors));
            redirect('pages/stock-in/create.php');
        }

        $userId = (int) current_user()['id'];
        StockIn::create(StockIn::createPayload($data, $userId));

        $msg = is_admin()
            ? 'Stock In recorded and approved.'
            : 'Stock In request submitted for approval.';
        flash('success', $msg);
        redirect('pages/stock-in/index.php');
    }

    public function edit(int $id): void
    {
        require_login();

        $record = StockIn::find($id);
        if (!$record || !StockIn::canModify($record)) {
            flash('error', 'Record not found or cannot be edited.');
            redirect('pages/stock-in/index.php');
        }

        if (!empty($_SESSION['form_old'])) {
            $record = array_merge($record, $_SESSION['form_old']);
            unset($_SESSION['form_old']);
        }

        render_app('stock-in/form.php', [
            'pageTitle'   => 'Edit Stock In — ' . APP_NAME,
            'currentNav'  => 'stock-in',
            'breadcrumbs' => [
                ['label' => 'Stock In', 'url' => base_url('pages/stock-in/index.php')],
                ['label' => 'Edit'],
            ],
            'items'       => Item::all(),
            'record'      => $record,
        ]);
    }

    public function update(int $id): void
    {
        require_login();
        require_csrf('pages/stock-in/edit.php?id=' . $id);

        $record = StockIn::find($id);
        if (!$record || !StockIn::canModify($record)) {
            flash('error', 'Record not found or cannot be edited.');
            redirect('pages/stock-in/index.php');
        }

        $data = StockIn::normalize($_POST);
        $errors = StockIn::validate($_POST);

        if ($errors) {
            $_SESSION['form_old'] = array_merge($data, ['id' => $id, 'item_id' => $data['item_id']]);
            flash('error', implode(' ', $errors));
            redirect('pages/stock-in/edit.php?id=' . $id);
        }

        StockIn::update($id, $data);
        flash('success', 'Stock In request updated.');
        redirect('pages/stock-in/index.php');
    }

    public function destroy(int $id): void
    {
        require_login();

        $record = StockIn::find($id);
        if (!$record || !StockIn::canModify($record)) {
            flash('error', 'Record not found or cannot be deleted.');
            redirect('pages/stock-in/index.php');
        }

        if (!StockIn::delete($id)) {
            flash('error', 'Unable to delete record.');
            redirect('pages/stock-in/index.php');
        }

        flash('success', 'Stock In request deleted.');
        redirect('pages/stock-in/index.php');
    }

    public function approve(int $id): void
    {
        require_admin();

        $result = ApprovalService::approveStockIn($id, (int) current_user()['id']);
        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('pages/stock-in/index.php');
    }

    public function reject(int $id, string $reason): void
    {
        require_admin();

        $result = ApprovalService::rejectStockIn($id, (int) current_user()['id'], $reason);
        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('pages/stock-in/index.php');
    }
}

require_once APP_PATH . '/models/StockOut.php';
