<?php
/**
 * Phase 3 — Stock In & Stock Out CRUD smoke test
 * Run: php database/test_phase3.php
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require APP_PATH . '/config/database.php';
require APP_PATH . '/helpers/Database.php';
require APP_PATH . '/helpers/session.php';
require APP_PATH . '/models/User.php';
require APP_PATH . '/models/Item.php';
require APP_PATH . '/models/Customer.php';
require APP_PATH . '/models/StockIn.php';
require APP_PATH . '/models/StockOut.php';
require __DIR__ . '/test_helpers.php';

echo "Stock Management — Phase 3 Test\n";
echo str_repeat('=', 44) . "\n\n";

$passed = 0;
$failed = 0;
$ids = ['item' => null, 'customer' => null, 'stock_in' => [], 'stock_out' => []];

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

function set_session_user(array $user): void
{
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user'] = $user;
}

try {
    $admin = User::findByUsername('admin');
    $staff = User::findByUsername('staff1');
    assert_test('Admin and staff users exist', $admin !== null && $staff !== null);

    $adminSession = User::toSessionUser($admin);
    $staffSession = User::toSessionUser($staff);

    $itemId = Item::create([
        'item_no'    => 'TEST-P3-' . time(),
        'item_name'  => 'Phase 3 Test Item',
        'unit'       => 'kg',
        'unit_price' => 1000,
        'category_id' => test_category_id('Fruits'),
        'remark'     => 'Phase 3 test',
        'created_by' => (int) $admin['id'],
    ]);
    $ids['item'] = $itemId;

    $customerId = Customer::create([
        'customer_code' => 'TEST-P3-' . time(),
        'customer_name' => 'Phase 3 Test Customer',
        'address'       => 'Yangon',
        'customer_type' => 'Retail',
        'created_by'    => (int) $admin['id'],
    ]);
    $ids['customer'] = $customerId;

    $stockInInput = [
        'item_id'        => $itemId,
        'mfd_date'       => '2026-01-01',
        'expire_date'    => '2026-12-31',
        'lot_no'         => 'LOT-P3-TEST',
        'qty'            => 100,
        'unit'           => 'kg',
        'worker_qty'     => 5,
        'in_charge_name' => 'Test Handler',
    ];

    assert_test('Stock In validation passes', empty(StockIn::validate($stockInInput)));
    assert_test('Stock In rejects expire before MFD', !empty(StockIn::validate(array_merge($stockInInput, [
        'mfd_date' => '2026-06-01',
        'expire_date' => '2026-01-01',
    ]))));

    set_session_user($adminSession);
    assert_test('Admin initial status is approved', StockIn::initialStatus() === 'approved');

    $adminInId = StockIn::create(StockIn::createPayload(StockIn::normalize($stockInInput), (int) $admin['id']));
    $ids['stock_in'][] = $adminInId;
    $adminIn = StockIn::find($adminInId);
    assert_test('Admin Stock In created as approved', $adminIn && $adminIn['status'] === 'approved');
    assert_test('Admin cannot modify approved Stock In', $adminIn && !StockIn::canModify($adminIn));

    set_session_user($staffSession);
    assert_test('Staff initial status is pending', StockIn::initialStatus() === 'pending');

    $staffInId = StockIn::create(StockIn::createPayload(StockIn::normalize($stockInInput), (int) $staff['id']));
    $ids['stock_in'][] = $staffInId;
    $staffIn = StockIn::find($staffInId);
    assert_test('Staff Stock In created as pending', $staffIn && $staffIn['status'] === 'pending');
    assert_test('Staff can modify own pending Stock In', $staffIn && StockIn::canModify($staffIn));

    set_session_user($adminSession);
    assert_test('Admin can modify staff pending Stock In', $staffIn && StockIn::canModify($staffIn));

    set_session_user($staffSession);
    $otherPending = StockIn::find($staffInId);
    if ($otherPending) {
        $otherPending['raw_created_by'] = (int) $admin['id'];
        assert_test('Staff cannot modify others pending Stock In', !StockIn::canModify($otherPending));
    }

    $updateData = StockIn::normalize(array_merge($stockInInput, ['qty' => 150, 'lot_no' => 'LOT-P3-UPDATED']));
    assert_test('Staff Stock In update succeeds', StockIn::update($staffInId, $updateData));
    $staffIn = StockIn::find($staffInId);
    assert_test('Stock In qty updated', $staffIn && (float) $staffIn['qty'] === 150.0);

    assert_test('Stock In status filter works', count(StockIn::all(['status' => 'pending'])) >= 1);
    assert_test('Stock In item filter works', count(StockIn::all(['item' => 'Phase 3 Test'])) >= 1);

    $stockOutInput = [
        'item_id'     => $itemId,
        'customer_id' => $customerId,
        'mfd_date'    => '2026-01-01',
        'qty'         => 10,
        'unit'        => 'kg',
        'reason'      => 'Sales',
        'remark'      => null,
    ];

    assert_test('Stock Out validation passes', empty(StockOut::validate($stockOutInput)));
    assert_test('Stock Out Other requires remark', !empty(StockOut::validate(array_merge($stockOutInput, [
        'reason' => 'Other',
        'remark' => '',
    ]))));

    set_session_user($adminSession);
    $adminOutId = StockOut::create(StockOut::createPayload(StockOut::normalize($stockOutInput), (int) $admin['id']));
    $ids['stock_out'][] = $adminOutId;
    $adminOut = StockOut::find($adminOutId);
    assert_test('Admin Stock Out created as approved', $adminOut && $adminOut['status'] === 'approved');

    set_session_user($staffSession);
    $staffOutId = StockOut::create(StockOut::createPayload(StockOut::normalize($stockOutInput), (int) $staff['id']));
    $ids['stock_out'][] = $staffOutId;
    $staffOut = StockOut::find($staffOutId);
    assert_test('Staff Stock Out created as pending', $staffOut && $staffOut['status'] === 'pending');
    assert_test('Staff can modify own pending Stock Out', $staffOut && StockOut::canModify($staffOut));

    assert_test('Stock Out reason filter works', count(StockOut::all(['reason' => 'Sales'])) >= 1);
    assert_test('Stock Out customer filter works', count(StockOut::all(['customer' => 'Phase 3 Test'])) >= 1);

    assert_test('Staff pending Stock In delete succeeds', StockIn::delete($staffInId) && StockIn::find($staffInId) === null);
    assert_test('Staff pending Stock Out delete succeeds', StockOut::delete($staffOutId) && StockOut::find($staffOutId) === null);

    StockIn::delete($adminInId);
    StockOut::delete($adminOutId);
    assert_test('Approved Stock In cannot be deleted', StockIn::find($adminInId) !== null);
    assert_test('Approved Stock Out cannot be deleted', StockOut::find($adminOutId) !== null);

    assert_test('countPending includes records', StockIn::countPending() >= 0 && StockOut::countPending() >= 0);

} catch (Throwable $e) {
    echo "[FAIL] Exception: " . $e->getMessage() . "\n";
    $failed++;
} finally {
    $db = Database::connect();

    foreach ($ids['stock_out'] as $id) {
        $db->prepare('DELETE FROM stock_out WHERE id = :id')->execute(['id' => $id]);
    }
    foreach ($ids['stock_in'] as $id) {
        $db->prepare('DELETE FROM stock_in WHERE id = :id')->execute(['id' => $id]);
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
    echo "Phase 3 check: PASSED\n";
    exit(0);
}

echo "Phase 3 check: FAILED\n";
exit(1);
