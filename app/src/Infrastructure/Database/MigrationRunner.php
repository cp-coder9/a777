<?php
declare(strict_types=1);

namespace Architex\Infrastructure\Database;

use PDO;
use RuntimeException;
use Throwable;

final class MigrationRunner
{
    private const LOCK_TIMEOUT_SECONDS = 10;
    private const MAX_MIGRATION_BYTES = 10_485_760;
    private readonly SqlStatementLexer $lexer;
    private readonly MigrationExecutionPolicy $policy;
    private readonly DdlSchemaValidator $ddlValidator;

    public function __construct(private readonly PDO $pdo, private readonly string $migrationDirectory)
    {
        $this->lexer = new SqlStatementLexer();
        $this->policy = new MigrationExecutionPolicy($this->lexer);
        $this->ddlValidator = new DdlSchemaValidator($pdo, $this->lexer);
    }

    public function migrate(): MigrationReport
    {
        $migrations = $this->migrationFiles();
        return $this->withLock(function () use ($migrations): MigrationReport {
            $this->ensureMigrationTable();
            $applied = $this->appliedMigrations();
            $this->assertChecksumsMatch($migrations, $applied);
            $new = [];
            foreach ($migrations as $version => $migration) {
                if (!isset($applied[$version])) {
                    $this->apply($version, $migration);
                    $applied[$version] = $migration['checksum'];
                    $new[] = $version;
                }
            }
            return new MigrationReport(count($new), 0, array_keys($applied), []);
        });
    }

    public function status(): MigrationReport
    {
        $migrations = $this->migrationFiles();
        return $this->withLock(function () use ($migrations): MigrationReport {
            if ($this->migrationTableExists()) {
                $this->assertMigrationTableContract();
                $applied = $this->appliedMigrations();
            } else {
                $applied = [];
            }
            $this->assertChecksumsMatch($migrations, $applied);
            $pending = array_values(array_diff(array_keys($migrations), array_keys($applied)));
            return new MigrationReport(0, count($pending), array_keys($applied), $pending);
        });
    }

    /** @return array<string,array{checksum:string,mode:string,statements:list<string>}> */
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
        $sequences = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (preg_match('/^(\d{4})_([a-z0-9_]+)\.sql$/D', $name, $matches) !== 1 || is_link($file)) {
                throw new RuntimeException('Invalid migration filename: ' . $name);
            }
            if (isset($sequences[$matches[1]])) {
                throw new RuntimeException('Duplicate migration sequence: ' . $matches[1]);
            }
            $sequences[$matches[1]] = true;
            $size = filesize($file);
            if ($size === false || $size < 1 || $size > self::MAX_MIGRATION_BYTES) {
                throw new RuntimeException('Migration file has an invalid size: ' . $name);
            }
            $sql = file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                throw new RuntimeException('Migration file could not be read: ' . $name);
            }
            if (preg_match('/\A(?:\xEF\xBB\xBF)?[\t ]*--[\t ]*architex:migration-mode=(ddl|dml)[\t ]*(?:\R|$)/', $sql, $mode) !== 1) {
                throw new RuntimeException('Migration execution mode is missing: ' . $name);
            }
            $statements = $this->lexer->split($sql);
            if ($statements === []) {
                throw new RuntimeException('Migration contains no SQL statements: ' . $name);
            }
            $this->policy->isTransactional($mode[1], $statements);
            $version = $matches[1] . '_' . $matches[2];
            $migrations[$version] = ['checksum' => hash('sha256', $sql), 'mode' => $mode[1], 'statements' => $statements];
        }
        return $migrations;
    }

    /** @template T @param callable():T $operation @return T */
    private function withLock(callable $operation): mixed
    {
        $name = $this->lockName();
        $this->acquireLock($name);
        $primary = null;
        try {
            return $operation();
        } catch (Throwable $exception) {
            $primary = $exception;
            throw $exception;
        } finally {
            try {
                $this->releaseLock($name);
            } catch (Throwable $releaseFailure) {
                if ($primary === null) {
                    throw $releaseFailure;
                }
            }
        }
    }

    private function lockName(): string
    {
        $database = $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!is_string($database) || $database === '') {
            throw new RuntimeException('No database is selected for migration.');
        }
        return 'architex_migrate_' . substr(hash('sha256', $database), 0, 32);
    }

    private function acquireLock(string $name): void
    {
        $statement = $this->pdo->prepare('SELECT GET_LOCK(:name, :timeout)');
        $statement->bindValue('name', $name, PDO::PARAM_STR);
        $statement->bindValue('timeout', self::LOCK_TIMEOUT_SECONDS, PDO::PARAM_INT);
        $statement->execute();
        if ((int) $statement->fetchColumn() !== 1) {
            throw new RuntimeException('Could not acquire the database migration lock.');
        }
    }

    private function releaseLock(string $name): void
    {
        $statement = $this->pdo->prepare('SELECT RELEASE_LOCK(:name)');
        $statement->execute(['name' => $name]);
        if ((int) $statement->fetchColumn() !== 1) {
            throw new RuntimeException('Could not release the database migration lock.');
        }
    }

    private function ensureMigrationTable(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(64) PRIMARY KEY,checksum CHAR(64) NOT NULL,applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->assertMigrationTableContract();
    }

    private function assertMigrationTableContract(): void
    {
        $table = $this->pdo->query("SELECT engine,table_collation FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='schema_migrations'")->fetch();
        if (!is_array($table) || strcasecmp((string) $table['engine'], 'InnoDB') !== 0 || strcasecmp((string) $table['table_collation'], 'utf8mb4_unicode_ci') !== 0) {
            throw new RuntimeException('schema_migrations table does not match the required contract.');
        }
        $columns = $this->pdo->query("SELECT column_name,column_type,is_nullable,column_default,column_key FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='schema_migrations' ORDER BY ordinal_position")->fetchAll();
        if (count($columns) !== 3 || !$this->columnMatches($columns[0], 'version', 'varchar(64)', 'NO', 'PRI') || !$this->columnMatches($columns[1], 'checksum', 'char(64)', 'NO', '') || !$this->columnMatches($columns[2], 'applied_at', 'datetime(6)', 'NO', '') || !str_contains(strtolower((string) ($columns[2]['column_default'] ?? '')), 'current_timestamp(6)')) {
            throw new RuntimeException('schema_migrations table does not match the required contract.');
        }
    }

    /** @param array<string,mixed> $column */
    private function columnMatches(array $column, string $name, string $type, string $nullable, string $key): bool
    {
        return (string) $column['column_name'] === $name && strtolower((string) $column['column_type']) === $type && (string) $column['is_nullable'] === $nullable && (string) $column['column_key'] === $key;
    }

    private function migrationTableExists(): bool
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='schema_migrations'")->fetchColumn() === 1;
    }

    /** @return array<string,string> */
    private function appliedMigrations(): array
    {
        $applied = [];
        foreach ($this->pdo->query('SELECT version,checksum FROM schema_migrations ORDER BY version') as $row) {
            $applied[(string) $row['version']] = (string) $row['checksum'];
        }
        return $applied;
    }

    /** @param array<string,array{checksum:string,mode:string,statements:list<string>}> $migrations @param array<string,string> $applied */
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

    /** @param array{checksum:string,mode:string,statements:list<string>} $migration */
    private function apply(string $version, array $migration): void
    {
        $transactional = $this->policy->isTransactional($migration['mode'], $migration['statements']);
        if ($transactional) {
            $this->pdo->beginTransaction();
        }
        try {
            foreach ($migration['statements'] as $statement) {
                $this->pdo->exec($statement);
                if ($migration['mode'] === 'ddl') {
                    $this->ddlValidator->assertCreateTableMatches($statement);
                }
            }
            $record = $this->pdo->prepare('INSERT INTO schema_migrations (version,checksum,applied_at) VALUES (:version,:checksum,UTC_TIMESTAMP(6))');
            $record->execute(['version' => $version, 'checksum' => $migration['checksum']]);
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
}
