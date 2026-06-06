<?php
/**
 * Phase 0 — PDO connection & schema verification
 * Run: php database/test_connection.php
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

require APP_PATH . '/config/database.php';
require APP_PATH . '/helpers/Database.php';

$expectedTables = ['users', 'items', 'customers', 'stock_in', 'stock_out'];

echo "Stock Management — Database Test (Phase 0)\n";
echo str_repeat('=', 44) . "\n\n";

try {
    $pdo = Database::connect();
    echo "[OK] PDO connection to '" . DB_NAME . "'\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "[OK] Found " . count($tables) . " table(s)\n\n";

    foreach ($expectedTables as $table) {
        if (in_array($table, $tables, true)) {
            $count = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
            echo "  - {$table}: exists ({$count} row(s))\n";
        } else {
            echo "  - {$table}: MISSING\n";
            exit(1);
        }
    }

    $admin = $pdo->query("SELECT id, username, display_name, role, status FROM users WHERE username = 'admin' LIMIT 1")->fetch();

    if ($admin) {
        echo "\n[OK] Default admin user found\n";
        echo "     Username: {$admin['username']}\n";
        echo "     Name:     {$admin['display_name']}\n";
        echo "     Role:     {$admin['role']}\n";
        echo "     Status:   {$admin['status']}\n";
        echo "     Password: password (default — change in production)\n";
    } else {
        echo "\n[WARN] Admin user not found — run database/seeds.sql\n";
        exit(1);
    }

    echo "\n" . str_repeat('=', 44) . "\n";
    echo "Phase 0 database check: PASSED\n";
    exit(0);

} catch (PDOException $e) {
    echo "[FAIL] " . $e->getMessage() . "\n\n";
    echo "Tips:\n";
    echo "  1. Start MySQL in XAMPP\n";
    echo "  2. Run: mysql -u root < database/schema.sql\n";
    echo "  3. Run: mysql -u root stock_manage < database/seeds.sql\n";
    echo "  4. Check app/config/database.php credentials\n";
    exit(1);
}
