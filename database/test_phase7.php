<?php
/**
 * Phase 7 — User management smoke test
 * Run: php database/test_phase7.php
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require APP_PATH . '/config/database.php';
require APP_PATH . '/helpers/session.php';
require APP_PATH . '/models/User.php';

echo "Stock Management — Phase 7 Test\n";
echo str_repeat('=', 44) . "\n\n";

$passed = 0;
$failed = 0;
$createdUserId = null;

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

    $_SESSION['user_id'] = (int) $admin['id'];
    $_SESSION['user'] = User::toSessionUser($admin);

    $username = 'test_p7_' . time();
    $userData = [
        'username'     => $username,
        'display_name' => 'Phase 7 Test Staff',
        'password'     => 'password123',
        'role'         => 'staff',
        'status'       => 'active',
    ];

    assert_test('User validation passes for create', empty(User::validate($userData)));
    assert_test('Short password rejected', !empty(User::validate(array_merge($userData, ['password' => 'short']))));
    assert_test('Duplicate username rejected', !empty(User::validate(array_merge($userData, ['username' => 'admin']))));

    $createdUserId = User::create([
        'username'      => $userData['username'],
        'password_hash' => User::hashPassword($userData['password']),
        'display_name'  => $userData['display_name'],
        'role'          => $userData['role'],
        'status'        => $userData['status'],
    ]);
    assert_test('Staff user created', $createdUserId > 0);

    $created = User::findByUsername($username);
    assert_test('Created user can be found', $created !== null);
    assert_test('Created user can verify password', $created && User::verifyPassword('password123', $created['password_hash']));

    assert_test('User search finds new user', count(User::all(['q' => 'Phase 7 Test'])) >= 1);
    assert_test('Role filter works', count(User::all(['role' => 'staff'])) >= 1);

    User::update($createdUserId, [
        'display_name' => 'Phase 7 Updated Staff',
        'role'         => 'staff',
        'status'       => 'active',
    ]);
    $updated = User::findById($createdUserId);
    assert_test('User display name updated', $updated && $updated['display_name'] === 'Phase 7 Updated Staff');

    assert_test('Cannot deactivate primary admin', !User::canDeactivate(User::PRIMARY_ADMIN_ID, (int) $admin['id']));
    assert_test('Cannot deactivate self', !User::canDeactivate((int) $admin['id'], (int) $admin['id']));
    assert_test('Can deactivate other user', User::canDeactivate($createdUserId, (int) $admin['id']));

    assert_test('Deactivate user succeeds', User::deactivate($createdUserId));
    $deactivated = User::findById($createdUserId);
    assert_test('Deactivated user status is inactive', $deactivated && $deactivated['status'] === 'inactive');

    $loginUser = User::findByUsername($username);
    assert_test('Inactive user blocked from login', $loginUser && $loginUser['status'] !== 'active');

    assert_test('Primary admin edit protection', !empty(User::validate([
        'display_name' => 'Admin',
        'role'         => 'staff',
        'status'       => 'active',
        'password'     => '',
    ], true, User::PRIMARY_ADMIN_ID)));

} catch (Throwable $e) {
    echo "[FAIL] Exception: " . $e->getMessage() . "\n";
    $failed++;
} finally {
    if ($createdUserId) {
        require_once APP_PATH . '/helpers/Database.php';
        $db = Database::connect();
        $db->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $createdUserId]);
    }
}

echo "\n" . str_repeat('=', 44) . "\n";
echo "Results: {$passed} passed, {$failed} failed\n";

if ($failed === 0) {
    echo "Phase 7 check: PASSED\n";
    exit(0);
}

echo "Phase 7 check: FAILED\n";
exit(1);
