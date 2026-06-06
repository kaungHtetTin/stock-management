<?php
/**
 * Item category management (admin only)
 */

require_once APP_PATH . '/models/Category.php';
require_once APP_PATH . '/models/StockIn.php';
require_once APP_PATH . '/models/StockOut.php';

class CategoryController
{
    public function index(): void
    {
        require_admin();

        if (isset($_GET['delete'])) {
            $this->destroy((int) $_GET['delete']);
            return;
        }

        $filters = ['q' => trim($_GET['q'] ?? '')];

        render_app('categories/index.php', [
            'pageTitle'    => 'Categories — ' . APP_NAME,
            'currentNav'   => 'categories',
            'breadcrumbs'  => [['label' => 'Categories']],
            'pendingBadge' => StockIn::countPending() + StockOut::countPending(),
            'categories'   => Category::all($filters),
            'filters'      => $filters,
        ]);
    }

    public function create(): void
    {
        require_admin();

        render_app('categories/form.php', [
            'pageTitle'   => 'Add Category — ' . APP_NAME,
            'currentNav'  => 'categories',
            'breadcrumbs' => [
                ['label' => 'Categories', 'url' => base_url('pages/categories/index.php')],
                ['label' => 'Add'],
            ],
            'category'    => $_SESSION['form_old'] ?? null,
        ]);
        unset($_SESSION['form_old']);
    }

    public function store(): void
    {
        require_admin();
        require_csrf('pages/categories/create.php');

        $data = Category::normalize($_POST);
        $errors = Category::validate($_POST);

        if ($errors) {
            $_SESSION['form_old'] = $data;
            flash('error', implode(' ', $errors));
            redirect('pages/categories/create.php');
        }

        Category::create($data);
        flash('success', 'Category "' . $data['name'] . '" created successfully.');
        redirect('pages/categories/index.php');
    }

    public function edit(int $id): void
    {
        require_admin();

        $category = Category::find($id);
        if (!$category) {
            flash('error', 'Category not found.');
            redirect('pages/categories/index.php');
        }

        if (!empty($_SESSION['form_old'])) {
            $category = array_merge($category, $_SESSION['form_old']);
            unset($_SESSION['form_old']);
        }

        render_app('categories/form.php', [
            'pageTitle'   => 'Edit Category — ' . APP_NAME,
            'currentNav'  => 'categories',
            'breadcrumbs' => [
                ['label' => 'Categories', 'url' => base_url('pages/categories/index.php')],
                ['label' => 'Edit'],
            ],
            'category'    => $category,
        ]);
    }

    public function update(int $id): void
    {
        require_admin();
        require_csrf('pages/categories/edit.php?id=' . $id);

        $category = Category::find($id);
        if (!$category) {
            flash('error', 'Category not found.');
            redirect('pages/categories/index.php');
        }

        $data = Category::normalize($_POST);
        $errors = Category::validate($_POST, $id);

        if ($errors) {
            $_SESSION['form_old'] = array_merge($data, ['id' => $id]);
            flash('error', implode(' ', $errors));
            redirect('pages/categories/edit.php?id=' . $id);
        }

        Category::update($id, $data);
        flash('success', 'Category "' . $data['name'] . '" updated successfully.');
        redirect('pages/categories/index.php');
    }

    public function destroy(int $id): void
    {
        require_admin();

        $category = Category::find($id);
        if (!$category) {
            flash('error', 'Category not found.');
            redirect('pages/categories/index.php');
        }

        if (Category::hasItems($id)) {
            flash('error', 'Cannot delete category with active items assigned.');
            redirect('pages/categories/index.php');
        }

        Category::softDelete($id);
        flash('success', 'Category "' . $category['name'] . '" deleted successfully.');
        redirect('pages/categories/index.php');
    }
}
