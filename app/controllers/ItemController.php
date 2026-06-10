<?php
/**
 * Item module — CRUD
 */

require_once APP_PATH . '/models/Item.php';
require_once APP_PATH . '/models/Category.php';

class ItemController
{
    public function index(): void
    {
        if (is_admin() && isset($_GET['delete'])) {
            $this->destroy((int) $_GET['delete']);
            return;
        }

        $filters = [
            'q'           => trim($_GET['q'] ?? ''),
            'category_id' => $_GET['category_id'] ?? '',
        ];
        $pagination = Item::paginate($filters, Pagination::pageFromRequest());

        render_app('items/index.php', [
            'pageTitle'   => 'Items — ' . APP_NAME,
            'currentNav'  => 'items',
            'breadcrumbs' => [['label' => 'Items']],
            'items'       => $pagination['rows'],
            'pagination'  => $pagination,
            'categories'  => Category::activeList(),
            'filters'     => $filters,
        ]);
    }

    public function create(): void
    {
        require_admin();

        render_app('items/form.php', [
            'pageTitle'   => 'Add Item — ' . APP_NAME,
            'currentNav'  => 'items',
            'breadcrumbs' => [
                ['label' => 'Items', 'url' => base_url('pages/items/index.php')],
                ['label' => 'Add'],
            ],
            'item'        => $_SESSION['form_old'] ?? null,
            'categories'  => Category::activeList(),
        ]);
        unset($_SESSION['form_old']);
    }

    public function store(): void
    {
        require_admin();
        require_csrf('pages/items/create.php');

        $data = Item::normalize($_POST);
        $errors = Item::validate($_POST);

        if ($errors) {
            $_SESSION['form_old'] = $data;
            flash('error', implode(' ', $errors));
            redirect('pages/items/create.php');
        }

        Item::create(array_merge($data, [
            'created_by' => current_user()['id'],
        ]));

        flash('success', 'Item "' . $data['item_name'] . '" created successfully.');
        redirect('pages/items/index.php');
    }

    public function edit(int $id): void
    {
        require_admin();

        $item = Item::find($id);
        if (!$item) {
            flash('error', 'Item not found.');
            redirect('pages/items/index.php');
        }

        if (!empty($_SESSION['form_old'])) {
            $item = array_merge($item, $_SESSION['form_old']);
            unset($_SESSION['form_old']);
        }

        render_app('items/form.php', [
            'pageTitle'   => 'Edit Item — ' . APP_NAME,
            'currentNav'  => 'items',
            'breadcrumbs' => [
                ['label' => 'Items', 'url' => base_url('pages/items/index.php')],
                ['label' => 'Edit'],
            ],
            'item'        => $item,
            'categories'  => Category::activeList(),
        ]);
    }

    public function update(int $id): void
    {
        require_admin();
        require_csrf('pages/items/edit.php?id=' . $id);

        $item = Item::find($id);
        if (!$item) {
            flash('error', 'Item not found.');
            redirect('pages/items/index.php');
        }

        $data = Item::normalize($_POST);
        $errors = Item::validate($_POST, $id);

        if ($errors) {
            $_SESSION['form_old'] = array_merge($data, ['id' => $id]);
            flash('error', implode(' ', $errors));
            redirect('pages/items/edit.php?id=' . $id);
        }

        Item::update($id, $data);
        flash('success', 'Item "' . $data['item_name'] . '" updated successfully.');
        redirect('pages/items/index.php');
    }

    public function destroy(int $id): void
    {
        require_admin();

        $item = Item::find($id);
        if (!$item) {
            flash('error', 'Item not found.');
            redirect('pages/items/index.php');
        }

        if (Item::hasStockRecords($id)) {
            flash('error', 'Cannot delete item with stock records. It is referenced in Stock In or Stock Out.');
            redirect('pages/items/index.php');
        }

        Item::softDelete($id);
        flash('success', 'Item "' . $item['item_name'] . '" deleted successfully.');
        redirect('pages/items/index.php');
    }
}
