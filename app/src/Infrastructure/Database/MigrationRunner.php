<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Database;

use PDO;
use RuntimeException;
use Throwable;

final class MigrationRunner
{
    private const LOCK_NAME = 'architex_schema_migration';
    private const LOCK_TIMEOUT_SECONDS = 10;
    private const MAX_MIGRATION_BYTES = 10_485_760;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $migrationDirectory,
    ) {
    }

    public function migrate(): MigrationReport
    {
        $migrations = $this->migrationFiles();
        $this->acquireLock();

        try {
            $this->ensureMigrationTable();
            $applied = $this->appliedMigrations();
            $this->assertChecksumsMatch($migrations, $applied);
            $newlyApplied = [];

            foreach ($migrations as $version => $migration) {
                if (isset($applied[$version])) {
                    continue;
                }
                $this->apply($version, $migration['checksum'], $migration['sql']);
                $applied[$version] = $migration['checksum'];
                $newlyApplied[] = $version;
            }

            return new MigrationReport(count($newlyApplied), 0, array_keys($applied), []);
        } finally {
            $this->releaseLock();
        }
    }

    public function status(): MigrationReport
    {
        $migrations = $this->migrationFiles();
        $this->acquireLock();

        try {
            $applied = $this->migrationTableExists() ? $this->appliedMigrations() : [];
            $this->assertChecksumsMatch($migrations, $applied);
            $pending = array_values(array_diff(array_keys($migrations), array_keys($applied)));

            return new MigrationReport(0, count($pending), array_keys($applied), $pending);
        } finally {
            $this->releaseLock();
        }
    }

    /** @return array<string,array{checksum:string,sql:string}> */
    private function migrationFiles(): array
    {
        $directory = realpath($this->migrationDirectory);
        if ($directory === false || !is_dir($directory) || !is_readable($directory)) {
            throw new RuntimeException('Migration directory is not readable.');
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . '*.sql');
        if ($files === false) {
            throw new RuntimeException('Migration directory could not be read.');
        }
        sort($files, SORT_STRING);
        if ($files === []) {
            throw new RuntimeException('No migration files found.');
        }
        $migrations = [];

        foreach ($files as $file) {
            $name = basename($file);
            if (preg_match('/^(\d{4}_[a-z0-9_]+)\.sql$/D', $name, $matches) !== 1 || is_link($file)) {
                throw new RuntimeException('Invalid migration filename: ' . $name);
            }
            $size = filesize($file);
            if ($size === false || $size < 1 || $size > self::MAX_MIGRATION_BYTES) {
                throw new RuntimeException('Migration file has an invalid size: ' . $name);
            }
            $sql = file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                throw new RuntimeException('Migration file could not be read: ' . $name);
            }
            $version = $matches[1];
            if (isset($migrations[$version])) {
                throw new RuntimeException('Duplicate migration version: ' . $version);
            }
            $migrations[$version] = ['checksum' => hash('sha256', $sql), 'sql' => $sql];
        }

        return $migrations;
    }

    private function acquireLock(): void
    {
        $statement = $this->pdo->prepare('SELECT GET_LOCK(:lock_name, :timeout_seconds)');
        $statement->bindValue('lock_name', self::LOCK_NAME, PDO::PARAM_STR);
        $statement->bindValue('timeout_seconds', self::LOCK_TIMEOUT_SECONDS, PDO::PARAM_INT);
        $statement->execute();
        if ((int) $statement->fetchColumn() !== 1) {
            throw new RuntimeException('Could not acquire the database migration lock.');
        }
    }

    private function releaseLock(): void
    {
        $statement = $this->pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $statement->execute(['lock_name' => self::LOCK_NAME]);
    }

    private function ensureMigrationTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations ('
            . 'version VARCHAR(64) PRIMARY KEY,'
            . 'checksum CHAR(64) NOT NULL,'
            . 'applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }

    private function migrationTableExists(): bool
    {
        $statement = $this->pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'schema_migrations'",
        );

        return (int) $statement->fetchColumn() === 1;
    }

    /** @return array<string,string> */
    private function appliedMigrations(): array
    {
        $statement = $this->pdo->query('SELECT version, checksum FROM schema_migrations ORDER BY version');
        $applied = [];
        foreach ($statement as $row) {
            $applied[(string) $row['version']] = (string) $row['checksum'];
        }

        return $applied;
    }

    /**
     * @param array<string,array{checksum:string,sql:string}> $migrations
     * @param array<string,string> $applied
     */
    private function assertChecksumsMatch(array $migrations, array $applied): void
    {
        foreach ($applied as $version => $checksum) {
            if (!isset($migrations[$version])) {
                throw new RuntimeException('Applied migration file is missing: ' . $version);
            }
            if (!hash_equals($checksum, $migrations[$version]['checksum'])) {
                throw new RuntimeException('Migration checksum mismatch for ' . $version);
            }
        }
    }

    private function apply(string $version, string $checksum, string $sql): void
    {
        $transactional = $this->isTransactionCompatible($sql);
        if ($transactional) {
            $this->pdo->beginTransaction();
        }

        try {
            $this->pdo->exec($sql);
            $statement = $this->pdo->prepare(
                'INSERT INTO schema_migrations (version, checksum, applied_at) VALUES (:version, :checksum, UTC_TIMESTAMP(6))',
            );
            $statement->execute(['version' => $version, 'checksum' => $checksum]);
            if ($transactional) {
                $this->pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Migration failed: ' . $version, 0, $exception);
        }
    }

    private function isTransactionCompatible(string $sql): bool
    {
        return preg_match(
            '/(?:^|;)\s*(?:(?:--|#)[^\r\n]*(?:\R|$)|\/\*.*?\*\/\s*)*(?:CREATE|ALTER|DROP|TRUNCATE|RENAME|GRANT|REVOKE|LOCK|UNLOCK)\b/is',
            $sql,
        ) !== 1;
    }
}
