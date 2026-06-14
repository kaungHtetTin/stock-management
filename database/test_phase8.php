<?php
/**
 * Phase 8 — Hardening & MVP acceptance smoke test
 * Run: php database/test_phase8.php
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require APP_PATH . '/config/app.php';
require APP_PATH . '/helpers/functions.php';
require APP_PATH . '/helpers/session.php';
require APP_PATH . '/helpers/csrf.php';
require APP_PATH . '/helpers/logger.php';

echo "Stock Management — Phase 8 Test\n";
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

// --- Security & cleanup ---
assert_test('mock-data.php removed', !file_exists(APP_PATH . '/helpers/mock-data.php'));
assert_test('UI_DEMO_MODE not defined', !defined('UI_DEMO_MODE'));
assert_test('Session timeout is 30 minutes', SESSION_TIMEOUT === 30);

$token = csrf_token();
assert_test('CSRF token generated', strlen($token) === 64);
assert_test('CSRF token verifies', verify_csrf($token));
assert_test('CSRF rejects invalid token', !verify_csrf('invalid-token'));

$_POST['csrf_token'] = $token;
assert_test('CSRF verifies from POST', verify_csrf());
unset($_POST['csrf_token']);

assert_test('Logs directory exists', is_dir(STORAGE_PATH . '/logs'));

app_log('TEST', 'Phase 8 log entry');
assert_test('Application logger writes to file', file_exists(STORAGE_PATH . '/logs/app.log'));

// --- No mock-data references in app/public code ---
$scanDirs = [APP_PATH, ROOT_PATH . '/public'];
$mockRefs = 0;
foreach ($scanDirs as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $content = file_get_contents($file->getPathname());
        if (str_contains($content, 'mock-data.php') || str_contains($content, 'mock_users(')) {
            $mockRefs++;
        }
    }
}
assert_test('No mock-data references in application code', $mockRefs === 0);

// --- POST forms include CSRF ---
$formViews = [
    'app/views/auth/login.php',
    'app/views/items/form.php',
    'app/views/customers/form.php',
    'app/views/stock-in/form.php',
    'app/views/stock-out/form.php',
    'app/views/users/form.php',
    'app/views/partials/approval-modal.php',
];
foreach ($formViews as $view) {
    $path = ROOT_PATH . '/' . str_replace('/', DIRECTORY_SEPARATOR, $view);
    $content = file_get_contents($path);
    assert_test(basename($view) . ' has CSRF field', str_contains($content, 'csrf_field()'));
}

// --- Report print header constants (MVP #9) ---
assert_test('Company name defined for reports', APP_COMPANY === 'YUKIOH MYANMAR CO.,LTD');
assert_test('Company ID defined for reports', APP_COMPANY_ID === '119751578');

// --- Run prior phase regression tests ---
$phaseTests = [
    'test_auth.php',
    'test_phase2.php',
    'test_phase3.php',
    'test_phase4.php',
    'test_phase5.php',
    'test_phase6.php',
    'test_phase7.php',
    'test_expiry_tracking.php',
];

echo "\nRegression tests:\n";
foreach ($phaseTests as $script) {
    $path = ROOT_PATH . '/database/' . $script;
    if (!file_exists($path)) {
        assert_test($script . ' exists', false);
        continue;
    }

    $output = [];
    $code = 0;
    exec('php ' . escapeshellarg($path), $output, $code);
    assert_test($script . ' passes', $code === 0);
}

echo "\n" . str_repeat('=', 44) . "\n";
echo "Results: {$passed} passed, {$failed} failed\n";

if ($failed === 0) {
    echo "Phase 8 check: PASSED\n";
    echo "MVP acceptance criteria: ALL PHASE TESTS PASSED\n";
    exit(0);
}

echo "Phase 8 check: FAILED\n";
exit(1);
