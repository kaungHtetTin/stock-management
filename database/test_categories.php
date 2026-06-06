<?php
/**
 * Category CRUD smoke test
 * Run: php database/test_categories.php
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require APP_PATH . '/config/database.php';
require APP_PATH . '/helpers/session.php';
require APP_PATH . '/models/User.php';
require APP_PATH . '/models/Category.php';
require APP_PATH . '/models/Item.php';

echo "Stock Management — Categories Test\n";
echo str_repeat('=', 44) . "\n\n";

$passed = 0;
$failed = 0;
$createdId = null;
$createdItemId = null;

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
    $_SESSION['user'] = User::toSessionUser($admin);

    assert_test('Default categories exist', count(Category::activeList()) >= 3);

    $data = ['name' => 'Test Category ' . time(), 'sort_order' => 99];
    assert_test('Category validation passes', empty(Category::validate($data)));
    $createdId = Category::create(Category::normalize($data));
    assert_test('Category created', $createdId > 0);

    $found = Category::find($createdId);
    assert_test('Category found by ID', $found !== null);

    $data['name'] = 'Updated Category ' . time();
    Category::update($createdId, Category::normalize($data));
    $found = Category::find($createdId);
    assert_test('Category updated', $found && str_starts_with($found['name'], 'Updated Category'));

    $createdItemId = Item::create([
        'item_no'     => 'TEST-CAT-' . time(),
        'item_name'   => 'Category Test Item',
        'unit'        => 'kg',
        'unit_price'  => 100,
        'category_id' => $createdId,
        'remark'      => null,
        'created_by'    => (int) $admin['id'],
    ]);
    assert_test('Item uses new category', $createdItemId > 0);
    assert_test('Category has assigned items', Category::hasItems($createdId));

    Item::softDelete($createdItemId);
    assert_test('Category deletes when no items', Category::softDelete($createdId));
    assert_test('Deleted category not in active list', Category::find($createdId) === null);

} catch (Throwable $e) {
    echo "[FAIL] Exception: " . $e->getMessage() . "\n";
    $failed++;
} finally {
    require_once APP_PATH . '/helpers/Database.php';
    $db = Database::connect();
    if ($createdItemId) {
        $db->prepare('DELETE FROM items WHERE id = :id')->execute(['id' => $createdItemId]);
    }
    if ($createdId) {
        $db->prepare('DELETE FROM categories WHERE id = :id')->execute(['id' => $createdId]);
    }
}

echo "\n" . str_repeat('=', 44) . "\n";
echo "Results: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
