<?php
/**
 * Phase 2 — Items & Customers CRUD smoke test
 * Run: php database/test_phase2.php
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

require APP_PATH . '/config/database.php';
require APP_PATH . '/helpers/Database.php';
require APP_PATH . '/models/Item.php';
require APP_PATH . '/models/Customer.php';
require __DIR__ . '/test_helpers.php';

echo "Stock Management — Phase 2 Test\n";
echo str_repeat('=', 44) . "\n\n";

$passed = 0;
$failed = 0;
$createdItemId = null;
$createdCustomerId = null;

function assert_test(string $label, bool $ok): void
{
    global $passed, $failed;
    if ($ok) {
        echo "[OK] {$label}\n";
        $passed++;
    } else {
        echo "[FAIL] {$label}\n";
        $failed++;
    }
}

try {
    $itemData = [
        'item_no'    => 'TEST-P2-' . time(),
        'item_name'  => 'Phase 2 Test Mango',
        'unit'       => 'kg',
        'unit_price' => 5000,
        'category_id' => test_category_id('Fruits'),
        'remark'     => 'Auto test record',
        'created_by' => 1,
    ];

    assert_test('Item validation passes', empty(Item::validate($itemData)));
    $createdItemId = Item::create($itemData);
    assert_test('Item created', $createdItemId > 0);

    $item = Item::find($createdItemId);
    assert_test('Item found by ID', $item !== null && $item['item_name'] === $itemData['item_name']);
    assert_test('Item balance defaults to 0', $item && (float) $item['balance'] === 0.0);

    $itemData['item_name'] = 'Phase 2 Updated Mango';
    Item::update($createdItemId, $itemData);
    $item = Item::find($createdItemId);
    assert_test('Item updated', $item && $item['item_name'] === 'Phase 2 Updated Mango');

    assert_test('Item search finds record', count(Item::all(['q' => 'Phase 2 Updated'])) >= 1);
    assert_test('Item category filter works', count(Item::all(['category_id' => test_category_id('Fruits')])) >= 1);
    $itemPage = Item::paginate(['q' => 'Phase 2 Updated'], 1);
    assert_test('Item paginate returns rows', count($itemPage['rows']) >= 1);
    assert_test('Item paginate metadata', $itemPage['total'] >= 1 && $itemPage['per_page'] === Pagination::PER_PAGE);
    assert_test('Duplicate item_no rejected', !empty(Item::validate($itemData)));

    $customerData = [
        'customer_code'  => 'TEST-P2-' . time(),
        'customer_name'  => 'Phase 2 Test Customer',
        'contact_person' => 'U Test Contact',
        'phone'          => '09123456789',
        'address'        => 'Yangon',
        'remark'         => 'Test remark',
        'customer_type'  => 'Retail',
        'created_by'     => 1,
    ];

    assert_test('Customer validation passes', empty(Customer::validate($customerData)));
    $createdCustomerId = Customer::create($customerData);
    assert_test('Customer created', $createdCustomerId > 0);

    $customer = Customer::find($createdCustomerId);
    assert_test('Customer found by ID', $customer !== null);

    $customerData['customer_name'] = 'Phase 2 Updated Customer';
    $customerData['contact_person'] = 'Daw Updated Contact';
    $customerData['phone'] = '09987654321';
    $customerData['remark'] = 'Updated remark';
    Customer::update($createdCustomerId, $customerData);
    $customer = Customer::find($createdCustomerId);
    assert_test('Customer updated', $customer && $customer['customer_name'] === 'Phase 2 Updated Customer');
    assert_test('Customer contact person saved', $customer && $customer['contact_person'] === 'Daw Updated Contact');
    assert_test('Customer phone saved', $customer && $customer['phone'] === '09987654321');
    assert_test('Customer remark saved', $customer && $customer['remark'] === 'Updated remark');

    assert_test('Customer search works', count(Customer::all(['q' => 'Phase 2 Updated'])) >= 1);
    $customerPage = Customer::paginate(['q' => 'Phase 2 Updated'], 1);
    assert_test('Customer paginate returns rows', count($customerPage['rows']) >= 1);
    assert_test('Customer paginate metadata', $customerPage['total'] >= 1 && $customerPage['total_pages'] >= 1);
    assert_test('Customer has no stock out yet', !Customer::hasStockOutRecords($createdCustomerId));

    assert_test('Item has no stock records yet', !Item::hasStockRecords($createdItemId));
    Item::softDelete($createdItemId);
    assert_test('Item soft deleted', Item::find($createdItemId) === null);

    Customer::softDelete($createdCustomerId);
    assert_test('Customer soft deleted', Customer::find($createdCustomerId) === null);

} catch (Throwable $e) {
    echo "[FAIL] Exception: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n" . str_repeat('=', 44) . "\n";
echo "Results: {$passed} passed, {$failed} failed\n";

if ($failed === 0) {
    echo "Phase 2 check: PASSED\n";
    exit(0);
}

echo "Phase 2 check: FAILED\n";
exit(1);
