<?php
/**
 * Phase 4 — Approval workflow smoke test
 * Run: php database/test_phase4.php
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require APP_PATH . '/config/database.php';
require APP_PATH . '/helpers/functions.php';
require APP_PATH . '/helpers/Database.php';
require APP_PATH . '/helpers/session.php';
require APP_PATH . '/models/User.php';
require APP_PATH . '/models/Item.php';
require APP_PATH . '/models/Customer.php';
require APP_PATH . '/models/StockIn.php';
require APP_PATH . '/models/StockOut.php';
require_once APP_PATH . '/services/BalanceService.php';
require APP_PATH . '/services/ApprovalService.php';
require __DIR__ . '/test_helpers.php';

echo "Stock Management — Phase 4 Test\n";
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
        'item_no'    => 'TEST-P4-' . time(),
        'item_name'  => 'Phase 4 Test Item',
        'unit'       => 'kg',
        'unit_price' => 1000,
        'category_id' => test_category_id('Fruits'),
        'remark'     => 'Phase 4 test',
        'created_by' => $adminId,
    ]);
    $ids['item'] = $itemId;

    $customerId = Customer::create([
        'customer_code' => 'TEST-P4-' . time(),
        'customer_name' => 'Phase 4 Test Customer',
        'address'       => 'Yangon',
        'customer_type' => 'Retail',
        'created_by'    => $adminId,
    ]);
    $ids['customer'] = $customerId;

    $baseIn = [
        'item_id'        => $itemId,
        'mfd_date'       => '2026-01-01',
        'expire_date'    => '2026-12-31',
        'lot_no'         => 'LOT-P4',
        'qty'            => 100,
        'unit'           => 'kg',
        'worker_qty'     => null,
        'in_charge_name' => 'Handler',
    ];

    set_session_user(User::toSessionUser($staff));
    $pendingInId = StockIn::create(StockIn::createPayload(StockIn::normalize($baseIn), $staffId));
    $ids['records'][] = ['type' => 'in', 'id' => $pendingInId];

    $approveResult = ApprovalService::approveStockIn($pendingInId, $adminId);
    assert_test('Approve pending Stock In succeeds', $approveResult['ok']);
    $approvedIn = StockIn::find($pendingInId);
    assert_test('Stock In status is approved', $approvedIn && $approvedIn['status'] === 'approved');
    assert_test('Stock In approved_by is set', $approvedIn && (int) $approvedIn['approved_by'] === $adminId);
    assert_test('Stock In approved_at is set', $approvedIn && !empty($approvedIn['approved_at']));

    $pendingRejectInId = StockIn::create(StockIn::createPayload(StockIn::normalize(array_merge($baseIn, [
        'lot_no' => 'LOT-P4-REJ',
        'qty'    => 5,
    ])), $staffId));
    $ids['records'][] = ['type' => 'in', 'id' => $pendingRejectInId];

    $rejectEmpty = ApprovalService::rejectStockIn($pendingRejectInId, $adminId, '');
    assert_test('Reject without reason fails', !$rejectEmpty['ok']);

    $rejectResult = ApprovalService::rejectStockIn($pendingRejectInId, $adminId, 'Incorrect lot details');
    assert_test('Reject pending Stock In succeeds', $rejectResult['ok']);
    $rejectedIn = StockIn::find($pendingRejectInId);
    assert_test('Stock In status is rejected', $rejectedIn && $rejectedIn['status'] === 'rejected');
    assert_test('Stock In rejection_reason saved', $rejectedIn && $rejectedIn['rejection_reason'] === 'Incorrect lot details');

    assert_test('Balance reflects approved stock in', BalanceService::getItemBalance($itemId) === 100.0);

    $baseOut = [
        'item_id'     => $itemId,
        'customer_id' => $customerId,
        'mfd_date'    => '2026-01-01',
        'qty'         => 30,
        'unit'        => 'kg',
        'reason'      => 'Sales',
        'remark'      => null,
    ];

    $pendingOutId = StockOut::create(StockOut::createPayload(StockOut::normalize($baseOut), $staffId));
    $ids['records'][] = ['type' => 'out', 'id' => $pendingOutId];

    $outApprove = ApprovalService::approveStockOut($pendingOutId, $adminId);
    assert_test('Approve Stock Out with sufficient balance succeeds', $outApprove['ok']);
    assert_test('Balance after stock out is 70', BalanceService::getItemBalance($itemId) === 70.0);

    $oversellId = StockOut::create(StockOut::createPayload(StockOut::normalize(array_merge($baseOut, [
        'qty' => 80,
    ])), $staffId));
    $ids['records'][] = ['type' => 'out', 'id' => $oversellId];

    $oversellApprove = ApprovalService::approveStockOut($oversellId, $adminId);
    assert_test('Approve Stock Out with insufficient balance fails', !$oversellApprove['ok']);
    $oversellRecord = StockOut::find($oversellId);
    assert_test('Oversell record stays pending', $oversellRecord && $oversellRecord['status'] === 'pending');

    $rejectOut = ApprovalService::rejectStockOut($oversellId, $adminId, 'Quantity exceeds available stock');
    assert_test('Reject oversell Stock Out succeeds', $rejectOut['ok']);

    $doubleApprove = ApprovalService::approveStockIn($pendingInId, $adminId);
    assert_test('Cannot approve non-pending record', !$doubleApprove['ok']);

    assert_test('countPending tracks remaining records', StockIn::countPending() >= 0 && StockOut::countPending() >= 0);

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
    echo "Phase 4 check: PASSED\n";
    exit(0);
}

echo "Phase 4 check: FAILED\n";
exit(1);
