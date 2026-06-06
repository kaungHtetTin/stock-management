<?php
/**
 * Phase 6 — Reports smoke test
 * Run: php database/test_phase6.php
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
require APP_PATH . '/models/Report.php';
require APP_PATH . '/services/ApprovalService.php';
require __DIR__ . '/test_helpers.php';

echo "Stock Management — Phase 6 Test\n";
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
    $staff = User::findByUsername('staff1');
    assert_test('Admin and staff users exist', $admin !== null && $staff !== null);

    $adminId = (int) $admin['id'];
    $staffId = (int) $staff['id'];
    $today = date('Y-m-d');

    $_SESSION['user_id'] = $adminId;
    $_SESSION['user'] = User::toSessionUser($admin);

    $itemId = Item::create([
        'item_no'    => 'TEST-P6-' . time(),
        'item_name'  => 'Phase 6 Report Mango',
        'unit'       => 'kg',
        'unit_price' => 1000,
        'category_id' => test_category_id('Fruits'),
        'remark'     => 'Phase 6 test',
        'created_by' => $adminId,
    ]);
    $ids['item'] = $itemId;

    $customerId = Customer::create([
        'customer_code' => 'TEST-P6-' . time(),
        'customer_name' => 'Phase 6 Report Customer',
        'address'       => 'Yangon',
        'customer_type' => 'Retail',
        'created_by'    => $adminId,
    ]);
    $ids['customer'] = $customerId;

    $approvedInId = StockIn::create(StockIn::createPayload(StockIn::normalize([
        'item_id'        => $itemId,
        'qty'            => 50,
        'unit'           => 'kg',
        'in_charge_name' => 'Handler',
        'lot_no'         => 'LOT-P6-APP',
    ]), $adminId));
    $ids['records'][] = ['type' => 'in', 'id' => $approvedInId];

    $_SESSION['user'] = User::toSessionUser($staff);
    $pendingInId = StockIn::create(StockIn::createPayload(StockIn::normalize([
        'item_id'        => $itemId,
        'qty'            => 10,
        'unit'           => 'kg',
        'in_charge_name' => 'Handler',
        'lot_no'         => 'LOT-P6-PEN',
    ]), $staffId));
    $ids['records'][] = ['type' => 'in', 'id' => $pendingInId];

    $_SESSION['user'] = User::toSessionUser($admin);
    $approvedOutId = StockOut::create(StockOut::createPayload(StockOut::normalize([
        'item_id'     => $itemId,
        'customer_id' => $customerId,
        'qty'         => 15,
        'unit'        => 'kg',
        'reason'      => 'Sales',
    ]), $adminId));
    $ids['records'][] = ['type' => 'out', 'id' => $approvedOutId];

    $filters = Report::normalizeFilters([
        'report_type' => 'stock_in',
        'status'      => 'approved',
        'item'        => 'Phase 6 Report Mango',
    ]);
    $stockInReport = Report::generate($filters, 1);
    assert_test('Stock In report returns approved record', count($stockInReport['rows']) === 1);
    assert_test('Stock In status filter excludes pending', $stockInReport['rows'][0]['status'] === 'approved');

    $pendingReport = Report::generate(Report::normalizeFilters([
        'report_type' => 'stock_in',
        'status'      => 'pending',
        'item'        => 'Phase 6 Report Mango',
    ]), 1);
    assert_test('Stock In pending filter works', count($pendingReport['rows']) === 1);

    $stockOutReport = Report::generate(Report::normalizeFilters([
        'report_type' => 'stock_out',
        'customer'    => (string) $customerId,
        'reason'      => 'Sales',
        'item'        => 'Phase 6 Report Mango',
    ]), 1);
    assert_test('Stock Out report with customer and reason filters', count($stockOutReport['rows']) === 1);

    $balanceReport = Report::generate(Report::normalizeFilters([
        'report_type' => 'current_stock',
        'category_id'   => test_category_id('Fruits'),
        'item'        => 'Phase 6 Report Mango',
    ]), 1);
    assert_test('Current stock report returns item', count($balanceReport['rows']) === 1);
    assert_test('Current stock balance uses approved only', (float) $balanceReport['rows'][0]['balance'] === 35.0);

    $activityReport = Report::generate(Report::normalizeFilters([
        'report_type' => 'activity',
        'date_from'   => $today,
        'date_to'     => $today,
        'item'        => 'Phase 6 Report Mango',
        'stock_type'  => 'both',
    ]), 1);
    assert_test('Activity report includes in and out rows', count($activityReport['rows']) === 3);
    assert_test('Activity summary counts records', $activityReport['summary']['in_count'] === 2
        && $activityReport['summary']['out_count'] === 1);

    $combinedFilters = Report::normalizeFilters([
        'report_type' => 'stock_out',
        'status'      => 'approved',
        'customer'    => (string) $customerId,
        'reason'      => 'Sales',
        'category_id'   => test_category_id('Fruits'),
        'item'        => 'Phase 6',
    ]);
    $combined = Report::generate($combinedFilters, 1);
    assert_test('Combined AND filters return matching stock out', count($combined['rows']) === 1);

    assert_test('Filter summary includes company context fields', Report::title('current_stock') === 'Current Stock Report'
        && Report::filterSummary($combinedFilters) !== '');

    $emptyReport = Report::generate(Report::normalizeFilters([
        'report_type' => 'stock_in',
        'status'      => 'rejected',
        'item'        => 'Phase 6 Report Mango',
    ]), 1);
    assert_test('Empty result returns zero rows', count($emptyReport['rows']) === 0 && $emptyReport['total'] === 0);

    assert_test('Pagination metadata present', $stockInReport['total_pages'] >= 1 && $stockInReport['per_page'] === Report::PER_PAGE);

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
    echo "Phase 6 check: PASSED\n";
    exit(0);
}

echo "Phase 6 check: FAILED\n";
exit(1);
