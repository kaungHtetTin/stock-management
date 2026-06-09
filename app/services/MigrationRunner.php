<?php
/**
 * Database migration runner (CLI)
 */

class MigrationRunner
{
    private PDO $pdo;
    private string $migrationsPath;

    public function __construct(?PDO $pdo = null, ?string $migrationsPath = null)
    {
        $this->pdo = $pdo ?? self::connect();
        $this->migrationsPath = $migrationsPath ?? (ROOT_PATH . '/database/migrations');
    }

    public static function connect(bool $withDatabase = true): PDO
    {
        $dsn = $withDatabase
            ? sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET)
            : sprintf('mysql:host=%s;charset=%s', DB_HOST, DB_CHARSET);

        return new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]);
    }

    public function ensureMigrationsTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration   VARCHAR(255) NOT NULL,
                applied_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_schema_migrations_name (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return list<string> Basenames of applied migrations, sorted */
    public function applied(): array
    {
        $this->ensureMigrationsTable();
        $rows = $this->pdo->query(
            'SELECT migration FROM schema_migrations ORDER BY migration ASC'
        )->fetchAll(PDO::FETCH_COLUMN);

        return array_map('strval', $rows);
    }

    /** @return list<string> Full paths to migration SQL files, sorted */
    public function migrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob($this->migrationsPath . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        return $files;
    }

    /** @return array<int, array{name: string, path: string, applied: bool}> */
    public function status(): array
    {
        $applied = array_flip($this->applied());
        $list = [];

        foreach ($this->migrationFiles() as $path) {
            $name = basename($path);
            $list[] = [
                'name'    => $name,
                'path'    => $path,
                'applied' => isset($applied[$name]),
            ];
        }

        return $list;
    }

    public function runPending(): int
    {
        $this->ensureMigrationsTable();
        $applied = array_flip($this->applied());
        $ran = 0;

        foreach ($this->migrationFiles() as $path) {
            $name = basename($path);
            if (isset($applied[$name])) {
                continue;
            }

            $this->runOne($name, $path);
            $ran++;
        }

        return $ran;
    }

    public function runOne(string $name, string $path): void
    {
        $this->ensureMigrationsTable();
        $this->runFile($path);
        $this->recordApplied($name);
    }

    public function stampAll(): int
    {
        $this->ensureMigrationsTable();
        $applied = array_flip($this->applied());
        $stamped = 0;

        foreach ($this->migrationFiles() as $path) {
            $name = basename($path);
            if (isset($applied[$name])) {
                continue;
            }

            $this->recordApplied($name);
            $stamped++;
        }

        return $stamped;
    }

    public function fresh(): void
    {
        $root = self::connect(false);
        $dbName = str_replace('`', '``', DB_NAME);

        $root->exec("DROP DATABASE IF EXISTS `{$dbName}`");

        $this->install();
        $this->stampAll();
    }

    public function install(): void
    {
        $schema = ROOT_PATH . '/database/schema.sql';
        $seeds = ROOT_PATH . '/database/seeds.sql';

        if (!is_file($schema)) {
            throw new RuntimeException('Schema file not found: ' . $schema);
        }
        if (!is_file($seeds)) {
            throw new RuntimeException('Seeds file not found: ' . $seeds);
        }

        $root = self::connect(false);
        // Keep USE in schema.sql — root connection has no default database
        $this->executeSql($root, (string) file_get_contents($schema), stripUse: false);

        $this->pdo = self::connect(true);
        $this->executeSql($this->pdo, (string) file_get_contents($seeds));
        $this->ensureMigrationsTable();
    }

    public function runFile(string $path): void
    {
        if (!is_file($path)) {
            throw new RuntimeException('Migration file not found: ' . $path);
        }

        $sql = (string) file_get_contents($path);
        $this->executeSql($this->pdo, $sql);
    }

    private function recordApplied(string $name): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
        $stmt->execute(['migration' => $name]);
    }

    private function executeSql(PDO $pdo, string $sql, bool $stripUse = true): void
    {
        $sql = $stripUse ? $this->normalizeSql($sql) : trim($sql);
        if ($sql === '') {
            return;
        }

        $pdo->exec($sql);
        $this->flushResults($pdo);
    }

    private function flushResults(PDO $pdo): void
    {
        if (!method_exists($pdo, 'nextRowset')) {
            return;
        }

        try {
            while ($pdo->nextRowset()) {
                // Drain extra result sets from multi-statement SQL
            }
        } catch (Throwable) {
            // Ignore driver-specific flush issues after exec()
        }
    }

    private function normalizeSql(string $sql): string
    {
        // Remove USE statements — connection already targets DB_NAME
        $sql = preg_replace('/^\s*USE\s+[`\w]+\s*;\s*$/mi', '', $sql) ?? $sql;

        return trim($sql);
    }
}
