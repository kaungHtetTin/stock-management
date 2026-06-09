#!/usr/bin/env php
<?php
/**
 * Database migration CLI
 *
 * Usage:
 *   php database/migrate.php              Run pending migrations
 *   php database/migrate.php up           Same as above
 *   php database/migrate.php status       List migrations
 *   php database/migrate.php install      Create DB from schema + seeds
 *   php database/migrate.php fresh        Drop DB, reinstall, stamp migrations
 *   php database/migrate.php stamp        Mark all migrations applied (no SQL run)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Run this script from the command line: php database/migrate.php\n";
    exit(1);
}

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

require APP_PATH . '/config/database.php';
require APP_PATH . '/helpers/Database.php';
require APP_PATH . '/services/MigrationRunner.php';

$command = strtolower($argv[1] ?? 'up');
$cliArgs = $argv;
$runner = new MigrationRunner();

echo "Stock Management — Database Migrations\n";
echo str_repeat('=', 44) . "\n\n";

try {
    match ($command) {
        'up', 'migrate', 'run' => cmdUp($runner),
        'status'              => cmdStatus($runner),
        'install'             => cmdInstall($runner),
        'fresh'               => cmdFresh($runner, $cliArgs),
        'stamp'               => cmdStamp($runner),
        'help', '-h', '--help' => cmdHelp(),
        default               => throw new InvalidArgumentException("Unknown command: {$command}"),
    };
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] " . $e->getMessage() . "\n");
    exit(1);
}

function cmdUp(MigrationRunner $runner): void
{
    $pending = array_values(array_filter($runner->status(), fn ($row) => !$row['applied']));

    if (empty($pending)) {
        echo "No pending migrations.\n";
        exit(0);
    }

    echo 'Pending: ' . count($pending) . " migration(s)\n\n";

    foreach ($pending as $row) {
        echo "Running {$row['name']}... ";
        $runner->runOne($row['name'], $row['path']);
        echo "OK\n";
    }

    echo "\nMigrations complete.\n";
    exit(0);
}

function cmdStatus(MigrationRunner $runner): void
{
    $rows = $runner->status();

    if (empty($rows)) {
        echo "No migration files in database/migrations/\n";
        exit(0);
    }

    foreach ($rows as $row) {
        $flag = $row['applied'] ? '[applied]' : '[pending]';
        echo "{$flag} {$row['name']}\n";
    }

    $pending = count(array_filter($rows, fn ($r) => !$r['applied']));
    echo "\n" . count($rows) . ' total, ' . $pending . " pending\n";
    exit($pending > 0 ? 0 : 0);
}

function cmdInstall(MigrationRunner $runner): void
{
    echo "Installing database (schema + seeds)...\n";
    $runner->install();
    echo "[OK] Database installed.\n";
    echo "Run: php database/migrate.php stamp   (if schema already includes all migrations)\n";
    echo "Or:  php database/migrate.php up     (to apply incremental migrations)\n";
    exit(0);
}

function cmdFresh(MigrationRunner $runner, array $argv): void
{
    $force = in_array('--force', $argv, true) || in_array('-f', $argv, true);

    if (!$force) {
        echo "WARNING: This will DELETE ALL DATA in database '" . DB_NAME . "'.\n";
        echo "Type 'yes' to continue: ";
        $answer = trim((string) fgets(STDIN));

        if (strtolower($answer) !== 'yes') {
            echo "Aborted.\n";
            exit(1);
        }
    }

    echo "Dropping and recreating database...\n";
    $runner->fresh();
    echo "[OK] Database refreshed.\n";
    echo "     - schema.sql applied\n";
    echo "     - seeds.sql applied\n";
    echo "     - migrations stamped\n";
    exit(0);
}

function cmdStamp(MigrationRunner $runner): void
{
    $count = $runner->stampAll();
    echo "Marked {$count} migration(s) as applied (no SQL executed).\n";
    echo "Use this after install when schema.sql is already up to date.\n";
    exit(0);
}

function cmdHelp(): void
{
    echo "Commands:\n";
    echo "  up, migrate, run   Run pending migrations (default)\n";
    echo "  status            Show applied / pending migrations\n";
    echo "  install           Create database from schema.sql + seeds.sql\n";
    echo "  fresh             Drop database, reinstall, stamp migrations\n";
    echo "  stamp             Mark all migration files as applied without running SQL\n";
    echo "  help              Show this help\n\n";
    echo "Options:\n";
    echo "  --force, -f        Skip confirmation prompt (fresh only)\n\n";
    echo "Examples:\n";
    echo "  php database/migrate.php\n";
    echo "  php database/migrate.php status\n";
    echo "  php database/migrate.php install\n";
    echo "  php database/migrate.php fresh\n";
    echo "  php database/migrate.php fresh --force\n";
    echo "  php database/migrate.php stamp\n";
    exit(0);
}
