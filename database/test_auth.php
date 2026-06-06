<?php
/**
 * Phase 1 — Authentication smoke test
 * Run: php database/test_auth.php
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

require APP_PATH . '/config/database.php';
require APP_PATH . '/helpers/Database.php';
require APP_PATH . '/models/User.php';

echo "Stock Management — Auth Test (Phase 1)\n";
echo str_repeat('=', 44) . "\n\n";

$passed = 0;
$failed = 0;

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

$admin = User::findByUsername('admin');
assert_test('Admin user exists in database', $admin !== null);
assert_test('Admin password "password" verifies', $admin && User::verifyPassword('password', $admin['password_hash']));
assert_test('Wrong password rejected', $admin && !User::verifyPassword('wrong', $admin['password_hash']));

$staff = User::findByUsername('staff1');
assert_test('Staff user exists in database', $staff !== null);
assert_test('Staff role is staff', $staff && $staff['role'] === 'staff');
assert_test('Staff password verifies', $staff && User::verifyPassword('password', $staff['password_hash']));

assert_test('Unknown user returns null', User::findByUsername('nobody') === null);

$sessionUser = $admin ? User::toSessionUser($admin) : [];
assert_test('Session user has required keys', count($sessionUser) === 5
    && isset($sessionUser['id'], $sessionUser['username'], $sessionUser['display_name'], $sessionUser['role'], $sessionUser['status']));

echo "\n" . str_repeat('=', 44) . "\n";
echo "Results: {$passed} passed, {$failed} failed\n";

if ($failed === 0) {
    echo "Phase 1 auth check: PASSED\n";
    exit(0);
}

echo "Phase 1 auth check: FAILED\n";
exit(1);
