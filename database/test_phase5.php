<?php
/**
 * Phase 5 — Balance & Dashboard smoke test
 * Run: php database/test_phase5.php
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
require APP_PATH . '/models/Balance.php';
require APP_PATH . '/models/Dashboard.php';
require APP_PATH . '/services/BalanceService.php';
require APP_PATH . '/services/ApprovalService.php';
require __DIR__ . '/test_helpers.php';

echo "Stock Management — Phase 5 Test\n";
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

function set_session_user(array $user): void
{
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user'] = $user;
}

try {
    $admin = User::findByUsername('admin');
    $staff = User::findByUsername('staff1');
    assert_test('Admin and staff users exist', $admin !== null && $staff !== null);

    $adminId = (int) $admin['id'];
    $staffId = (int) $staff['id'];

    $itemId = Item::create([
        'item_no'    => 'TEST-P5-' . time(),
        'item_name'  => 'Phase 5 Test Item',
        'unit'       => 'kg',
        'unit_price' => 1000,
        'category_id' => test_category_id('Fruits'),
        'remark'     => 'Phase 5 test',
        'created_by' => $adminId,
    ]);
    $ids['item'] = $itemId;

    $customerId = Customer::create([
        'customer_code' => 'TEST-P5-' . time(),
        'customer_name' => 'Phase 5 Test Customer',
        'address'       => 'Yangon',
        'customer_type' => 'Retail',
        'created_by'    => $adminId,
    ]);
    $ids['customer'] = $customerId;

    assert_test('Initial balance is zero', BalanceService::getItemBalance($itemId) === 0.0);

    set_session_user(User::toSessionUser($staff));
    $pendingInId = StockIn::create(StockIn::createPayload(StockIn::normalize([
        'item_id'        => $itemId,
        'mfd_date'       => '2026-01-01',
        'expire_date'    => '2026-12-31',
        'lot_no'         => 'LOT-P5',
        'qty'            => 100,
        'unit'           => 'kg',
        'worker_qty'     => null,
        'in_charge_name' => 'Handler',
    ]), $staffId));
    $ids['records'][] = ['type' => 'in', 'id' => $pendingInId];

    assert_test('Pending Stock In does not affect balance', BalanceService::getItemBalance($itemId) === 0.0);

    ApprovalService::approveStockIn($pendingInId, $adminId);
    assert_test('Balance updates after approved Stock In', BalanceService::getItemBalance($itemId) === 100.0);

    $pendingOutId = StockOut::create(StockOut::createPayload(StockOut::normalize([
        'item_id'     => $itemId,
        'customer_id' => $customerId,
        'mfd_date'    => '2026-01-01',
        'qty'         => 30,
        'unit'        => 'kg',
        'reason'      => 'Sales',
        'remark'      => null,
    ]), $staffId));
    $ids['records'][] = ['type' => 'out', 'id' => $pendingOutId];

    assert_test('Pending Stock Out does not affect balance', BalanceService::getItemBalance($itemId) === 100.0);

    ApprovalService::approveStockOut($pendingOutId, $adminId);
    assert_test('Balance decreases after approved Stock Out', BalanceService::getItemBalance($itemId) === 70.0);

    $balances = BalanceService::getAllBalances(['q' => 'Phase 5 Test']);
    assert_test('Balance list returns item', count($balances) === 1 && (float) $balances[0]['balance'] === 70.0);

    $chart = BalanceService::getChartData();
    assert_test('Category chart has labels', count($chart['labels']) >= 1);
    assert_test('Fruits category total includes test item', array_sum($chart['values']) >= 70.0);

    assert_test('Low stock threshold flags correctly', BalanceService::isLowStock(10.0) && !BalanceService::isLowStock(70.0));

    $anotherPendingId = StockIn::create(StockIn::createPayload(StockIn::normalize([
        'item_id'        => $itemId,
        'qty'            => 5,
        'unit'           => 'kg',
        'in_charge_name' => 'Handler',
    ]), $staffId));
    $ids['records'][] = ['type' => 'in', 'id' => $anotherPendingId];

    $adminStats = Dashboard::stats(true, $adminId);
    assert_test('Admin dashboard pending count matches DB', $adminStats['pending_count'] === StockIn::countPending() + StockOut::countPending());

    $staffStats = Dashboard::stats(false, $staffId);
    assert_test('Staff dashboard pending count is own only', $staffStats['pending_count'] === 1);

    $adminPending = Dashboard::pendingList(true, $adminId);
    $staffPending = Dashboard::pendingList(false, $staffId);
    assert_test('Admin pending list includes all pending', count($adminPending) >= 1);
    assert_test('Staff pending list is scoped to own records', count($staffPending) === 1);

    $activity = Dashboard::recentActivity(5);
    assert_test('Recent activity returns approved records', count($activity) >= 2 && $activity[0]['status'] === 'approved');

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
    echo "Phase 5 check: PASSED\n";
    exit(0);
}

echo "Phase 5 check: FAILED\n";
exit(1);
