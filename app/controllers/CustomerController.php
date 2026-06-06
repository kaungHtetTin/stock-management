<?php
/**
 * Customer module — CRUD
 */

require_once APP_PATH . '/models/Customer.php';

class CustomerController
{
    public function index(): void
    {
        if (is_admin() && isset($_GET['delete'])) {
            $this->destroy((int) $_GET['delete']);
            return;
        }

        $filters = [
            'q'    => trim($_GET['q'] ?? ''),
            'type' => $_GET['type'] ?? '',
        ];

        render_app('customers/index.php', [
            'pageTitle'   => 'Customers — ' . APP_NAME,
            'currentNav'  => 'customers',
            'breadcrumbs' => [['label' => 'Customers']],
            'customers'   => Customer::all($filters),
            'filters'     => $filters,
        ]);
    }

    public function create(): void
    {
        require_admin();

        render_app('customers/form.php', [
            'pageTitle'   => 'Add Customer — ' . APP_NAME,
            'currentNav'  => 'customers',
            'breadcrumbs' => [
                ['label' => 'Customers', 'url' => base_url('pages/customers/index.php')],
                ['label' => 'Add'],
            ],
            'customer'    => $_SESSION['form_old'] ?? null,
        ]);
        unset($_SESSION['form_old']);
    }

    public function store(): void
    {
        require_admin();
        require_csrf('pages/customers/create.php');

        $data = Customer::normalize($_POST);
        $errors = Customer::validate($_POST);

        if ($errors) {
            $_SESSION['form_old'] = $data;
            flash('error', implode(' ', $errors));
            redirect('pages/customers/create.php');
        }

        Customer::create(array_merge($data, [
            'created_by' => current_user()['id'],
        ]));

        flash('success', 'Customer "' . $data['customer_name'] . '" created successfully.');
        redirect('pages/customers/index.php');
    }

    public function edit(int $id): void
    {
        require_admin();

        $customer = Customer::find($id);
        if (!$customer) {
            flash('error', 'Customer not found.');
            redirect('pages/customers/index.php');
        }

        if (!empty($_SESSION['form_old'])) {
            $customer = array_merge($customer, $_SESSION['form_old']);
            unset($_SESSION['form_old']);
        }

        render_app('customers/form.php', [
            'pageTitle'   => 'Edit Customer — ' . APP_NAME,
            'currentNav'  => 'customers',
            'breadcrumbs' => [
                ['label' => 'Customers', 'url' => base_url('pages/customers/index.php')],
                ['label' => 'Edit'],
            ],
            'customer'    => $customer,
        ]);
    }

    public function update(int $id): void
    {
        require_admin();
        require_csrf('pages/customers/edit.php?id=' . $id);

        $customer = Customer::find($id);
        if (!$customer) {
            flash('error', 'Customer not found.');
            redirect('pages/customers/index.php');
        }

        $data = Customer::normalize($_POST);
        $errors = Customer::validate($_POST, $id);

        if ($errors) {
            $_SESSION['form_old'] = array_merge($data, ['id' => $id]);
            flash('error', implode(' ', $errors));
            redirect('pages/customers/edit.php?id=' . $id);
        }

        Customer::update($id, $data);
        flash('success', 'Customer "' . $data['customer_name'] . '" updated successfully.');
        redirect('pages/customers/index.php');
    }

    public function destroy(int $id): void
    {
        require_admin();

        $customer = Customer::find($id);
        if (!$customer) {
            flash('error', 'Customer not found.');
            redirect('pages/customers/index.php');
        }

        if (Customer::hasStockOutRecords($id)) {
            flash('error', 'Cannot delete customer with Stock Out records.');
            redirect('pages/customers/index.php');
        }

        Customer::softDelete($id);
        flash('success', 'Customer "' . $customer['customer_name'] . '" deleted successfully.');
        redirect('pages/customers/index.php');
    }
}
