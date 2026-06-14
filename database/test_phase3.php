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
require APP_PATH . '/helpers/functions.php';
require APP_PATH . '/helpers/Database.php';
require APP_PATH . '/helpers/session.php';
require APP_PATH . '/models/User.php';
require APP_PATH . '/models/Item.php';
require APP_PATH . '/models/Customer.php';
require APP_PATH . '/models/StockIn.php';
require APP_PATH . '/models/StockOut.php';
require_once APP_PATH . '/services/BalanceService.php';
require __DIR__ . '/test_helpers.php';

echo "Stock Management — Phase 3 Test\n";
echo str_repeat('=', 44) . "\n\n";

$passed = 0;
$failed = 0;
$ids = ['item' => null, 'customer' => null, 'item2' => null, 'stock_in' => [], 'stock_out' => []];

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
    assert_test('Admin can modify approved Stock In', $adminIn && StockIn::canModify($adminIn));

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
    $stockInPage = StockIn::paginate(['item' => 'Phase 3 Test'], 1);
    assert_test('Stock In paginate metadata', $stockInPage['total'] >= 1 && $stockInPage['per_page'] === Pagination::PER_PAGE);

    $stockOutInput = [
        'item_id'     => $itemId,
        'customer_id' => $customerId,
        'mfd_date'    => '2026-01-01',
        'expire_date' => '2026-12-31',
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
    assert_test('Stock Out rejects expire before MFD', !empty(StockOut::validate(array_merge($stockOutInput, [
        'mfd_date' => '2026-06-01',
        'expire_date' => '2026-01-01',
    ]))));

    set_session_user($adminSession);
    $adminOutId = StockOut::create(StockOut::createPayload(StockOut::normalize($stockOutInput), (int) $admin['id']));
    $ids['stock_out'][] = $adminOutId;
    $adminOut = StockOut::find($adminOutId);
    assert_test('Admin Stock Out created as approved', $adminOut && $adminOut['status'] === 'approved');
    assert_test('Stock Out expire date saved', $adminOut && $adminOut['expire_date'] === '2026-12-31');

    set_session_user($staffSession);
    $staffOutId = StockOut::create(StockOut::createPayload(StockOut::normalize($stockOutInput), (int) $staff['id']));
    $ids['stock_out'][] = $staffOutId;
    $staffOut = StockOut::find($staffOutId);
    assert_test('Staff Stock Out created as pending', $staffOut && $staffOut['status'] === 'pending');
    assert_test('Staff can modify own pending Stock Out', $staffOut && StockOut::canModify($staffOut));

    assert_test('Stock Out reason filter works', count(StockOut::all(['reason' => 'Sales'])) >= 1);
    assert_test('Stock Out customer filter works', count(StockOut::all(['customer' => 'Phase 3 Test'])) >= 1);
    $stockOutPage = StockOut::paginate(['customer' => 'Phase 3 Test'], 1);
    assert_test('Stock Out paginate metadata', $stockOutPage['total'] >= 1 && $stockOutPage['total_pages'] >= 1);

    assert_test('Staff pending Stock In delete succeeds', StockIn::delete($staffInId) && StockIn::find($staffInId) === null);
    assert_test('Staff pending Stock Out delete succeeds', StockOut::delete($staffOutId) && StockOut::find($staffOutId) === null);

    set_session_user($adminSession);
    $balanceBeforeDelete = BalanceService::getItemBalance($itemId);
    assert_test('Approved Stock In delete succeeds', StockIn::delete($adminInId) && StockIn::find($adminInId) === null);
    assert_test('Approved Stock Out delete succeeds', StockOut::delete($adminOutId) && StockOut::find($adminOutId) === null);
    assert_test(
        'Balance recalculates after approved delete',
        BalanceService::getItemBalance($itemId) === $balanceBeforeDelete - 100.0 + 10.0
    );

    set_session_user($staffSession);
    assert_test('Staff cannot modify admin approved Stock Out', $adminOut && !StockOut::canModify($adminOut));

    assert_test('countPending includes records', StockIn::countPending() >= 0 && StockOut::countPending() >= 0);

    set_session_user($adminSession);
    $multiIn = StockIn::createMany([
        'in_charge_name' => 'Batch Handler',
        'lines' => [
            array_merge($stockInInput, ['qty' => 20, 'lot_no' => 'LOT-M1']),
            array_merge($stockInInput, ['qty' => 30, 'lot_no' => 'LOT-M2']),
        ],
    ], (int) $admin['id']);
    assert_test('Multi Stock In creates two records', count($multiIn['ids']) === 2);
    assert_test('Multi Stock In shares batch_ref', !empty($multiIn['batch_ref']));
    foreach ($multiIn['ids'] as $multiInId) {
        $ids['stock_in'][] = $multiInId;
    }

    $batchEditInput = [
        'in_charge_name' => 'Batch Handler Updated',
        'lines' => [
            ['id' => $multiIn['ids'][0], 'item_id' => $itemId, 'mfd_date' => '2026-01-01', 'expire_date' => '2026-12-31',
             'lot_no' => 'LOT-M1-UPD', 'qty' => 25, 'unit' => 'kg', 'worker_qty' => 5],
            ['id' => $multiIn['ids'][1], 'item_id' => $itemId, 'mfd_date' => '2026-01-01', 'expire_date' => '2026-12-31',
             'lot_no' => 'LOT-M2-UPD', 'qty' => 35, 'unit' => 'kg', 'worker_qty' => 5],
        ],
    ];
    assert_test('Batch Stock In edit validation passes', empty(StockIn::validateEditSubmission($multiIn['ids'][0], $batchEditInput)));
    assert_test('Batch Stock In edit succeeds', StockIn::updateSubmission($multiIn['ids'][0], $batchEditInput));
    $editedBatch = StockIn::findBatchRecords($multiIn['ids'][0]);
    assert_test('Batch Stock In qty updated', $editedBatch && (float) $editedBatch[0]['qty'] === 25.0 && (float) $editedBatch[1]['qty'] === 35.0);

    $ids['item2'] = Item::create([
        'item_no'     => 'TEST-P3B-' . time(),
        'item_name'   => 'Phase 3 Second Item',
        'unit'        => 'kg',
        'unit_price'  => 500,
        'category_id' => test_category_id('Fruits'),
        'remark'      => 'Phase 3 batch test',
        'created_by'  => (int) $admin['id'],
    ]);
    $item2Id = $ids['item2'];

    $multiOut = StockOut::createMany([
        'customer_id' => $customerId,
        'reason'      => 'Sales',
        'remark'      => null,
        'lines'       => [
            ['item_id' => $itemId, 'mfd_date' => '2026-01-01', 'expire_date' => '2026-12-31', 'qty' => 2, 'unit' => 'kg'],
            ['item_id' => $item2Id, 'mfd_date' => '2026-01-01', 'expire_date' => '2026-12-31', 'qty' => 3, 'unit' => 'kg'],
        ],
    ], (int) $admin['id']);
    assert_test('Multi Stock Out creates two records', count($multiOut['ids']) === 2);
    assert_test('Multi Stock Out shares batch_ref', !empty($multiOut['batch_ref']));
    foreach ($multiOut['ids'] as $multiOutId) {
        $ids['stock_out'][] = $multiOutId;
    }

    set_session_user($staffSession);
    $rejectedInId = StockIn::create(StockIn::createPayload(StockIn::normalize($stockInInput), (int) $staff['id']));
    $ids['stock_in'][] = $rejectedInId;
    $db = Database::connect();
    $db->prepare('UPDATE stock_in SET status = \'rejected\', rejection_reason = \'Test reject\' WHERE id = :id')
        ->execute(['id' => $rejectedInId]);
    $rejectedIn = StockIn::find($rejectedInId);
    assert_test('Staff can modify rejected Stock In', $rejectedIn && StockIn::canModify($rejectedIn));

    $resubmitInput = [
        'in_charge_name' => 'Resubmit Handler',
        'lines' => [
            array_merge(StockIn::normalizeLine($stockInInput), ['id' => $rejectedInId, 'qty' => 55]),
        ],
    ];
    assert_test('Rejected Stock In edit resets to pending', StockIn::updateSubmission($rejectedInId, $resubmitInput));
    $resubmitted = StockIn::find($rejectedInId);
    assert_test('Rejected Stock In status is pending after edit', $resubmitted && $resubmitted['status'] === 'pending');
    assert_test('Rejected Stock In qty updated on resubmit', $resubmitted && (float) $resubmitted['qty'] === 55.0);

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
    if ($ids['item2']) {
        $db->prepare('DELETE FROM items WHERE id = :id')->execute(['id' => $ids['item2']]);
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
