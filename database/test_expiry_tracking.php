<?php
/**
 * Expiry tracking smoke test
 * Run: php database/test_expiry_tracking.php
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require APP_PATH . '/config/app.php';
require APP_PATH . '/config/database.php';
require APP_PATH . '/helpers/functions.php';
require APP_PATH . '/helpers/Database.php';
require APP_PATH . '/helpers/session.php';
require APP_PATH . '/models/User.php';
require APP_PATH . '/models/Item.php';
require APP_PATH . '/models/Customer.php';
require APP_PATH . '/models/StockIn.php';
require APP_PATH . '/models/StockOut.php';
require APP_PATH . '/models/ExpiryTracking.php';
require __DIR__ . '/test_helpers.php';

echo "Stock Management - Expiry Tracking Test\n";
echo str_repeat('=', 44) . "\n\n";

$passed = 0;
$failed = 0;
$ids = ['item' => null, 'customer' => null, 'records' => []];

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
    $admin = User::findByUsername('admin');
    assert_test('Admin user exists', $admin !== null);
    $adminId = (int) $admin['id'];
    $_SESSION['user_id'] = $adminId;
    $_SESSION['user'] = User::toSessionUser($admin);

    $suffix = time();
    $itemId = Item::create([
        'item_no'     => 'TEST-EXP-' . $suffix,
        'item_name'   => 'Expiry Tracking Test Item',
        'unit'        => 'kg',
        'unit_price'  => 1000,
        'category_id' => test_category_id('Fruits'),
        'remark'      => 'Expiry tracking test',
        'created_by'  => $adminId,
    ]);
    $ids['item'] = $itemId;

    $customerId = Customer::create([
        'customer_code' => 'TEST-EXP-' . $suffix,
        'customer_name' => 'Expiry Tracking Test Customer',
        'address'       => 'Yangon',
        'customer_type' => 'Retail',
        'created_by'    => $adminId,
    ]);
    $ids['customer'] = $customerId;

    $expireDate = date('Y-m-d', strtotime('+5 days'));
    $stockInId = StockIn::create(StockIn::createPayload(StockIn::normalize([
        'item_id'        => $itemId,
        'mfd_date'       => date('Y-m-d', strtotime('-10 days')),
        'expire_date'    => $expireDate,
        'lot_no'         => 'EXP-LOT-1',
        'qty'            => 40,
        'unit'           => 'kg',
        'in_charge_name' => 'Expiry Tester',
    ]), $adminId));
    $ids['records'][] = ['type' => 'in', 'id' => $stockInId];

    $stockOutId = StockOut::create(StockOut::createPayload(StockOut::normalize([
        'item_id'     => $itemId,
        'customer_id' => $customerId,
        'mfd_date'    => date('Y-m-d', strtotime('-10 days')),
        'expire_date' => $expireDate,
        'qty'         => 12,
        'unit'        => 'kg',
        'reason'      => 'Sales',
    ]), $adminId));
    $ids['records'][] = ['type' => 'out', 'id' => $stockOutId];

    $rows = ExpiryTracking::rows(['q' => 'TEST-EXP-' . $suffix]);
    assert_test('Expiry tracker returns test batch', count($rows) === 1);
    assert_test('Expiry tracker subtracts matching stock out', isset($rows[0]) && (float) $rows[0]['remaining_qty'] === 28.0);
    assert_test('Expiry tracker classifies urgent batch', isset($rows[0]) && $rows[0]['expiry_status'] === 'urgent');

    $summary = ExpiryTracking::summary($rows);
    assert_test('Expiry summary counts urgent batch', $summary['urgent'] === 1 && $summary['total_batches'] === 1);

    $urgentRows = ExpiryTracking::rows(['q' => 'TEST-EXP-' . $suffix, 'status' => 'urgent']);
    assert_test('Expiry risk filter returns urgent batch', count($urgentRows) === 1);
} catch (Throwable $e) {
    echo "[FAIL] Exception: " . $e->getMessage() . "\n";
    $failed++;
} finally {
    $db = Database::connect();

    foreach ($ids['records'] as $rec) {
        $table = $rec['type'] === 'in' ? 'stock_in' : 'stock_out';
        $db->prepare("DELETE FROM {$table} WHERE id = :id")->execute(['id' => $rec['id']]);
    }
    if ($ids['item']) {
        $db->prepare('DELETE FROM items WHERE id = :id')->execute(['id' => $ids['item']]);
    }
    if ($ids['customer']) {
        $db->prepare('DELETE FROM customers WHERE id = :id')->execute(['id' => $ids['customer']]);
    }
}

echo "\n" . str_repeat('=', 44) . "\n";
echo "Results: {$passed} passed, {$failed} failed\n";

if ($failed === 0) {
    echo "Expiry tracking check: PASSED\n";
    exit(0);
}

echo "Expiry tracking check: FAILED\n";
exit(1);
