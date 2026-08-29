<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Database;

use Architex\Infrastructure\Config\AppConfig;
use Architex\Infrastructure\Database\ConnectionFactory;
use Architex\Infrastructure\Database\DisposableDatabaseGuard;
use Architex\Infrastructure\Database\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MigrationRunnerTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $environment = $this->databaseEnvironment();
        if (($environment['ARCHITEX_ALLOW_DISPOSABLE_DB'] ?? '') !== '1') {
            $this->markTestSkipped('Disposable MariaDB integration test was not explicitly enabled.');
        }
        DisposableDatabaseGuard::assertAllowed($environment);
        $config = AppConfig::fromEnvironment($environment);
        $admin = $this->adminConnection($config);
        $admin->exec('DROP DATABASE IF EXISTS `' . $config->dbName . '`');
        $admin->exec('CREATE DATABASE `' . $config->dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->pdo = ConnectionFactory::create($config);
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo)) {
            return;
        }
        $environment = $this->databaseEnvironment();
        DisposableDatabaseGuard::assertAllowed($environment);
        $config = AppConfig::fromEnvironment($environment);
        unset($this->pdo);
        $this->adminConnection($config)->exec('DROP DATABASE IF EXISTS `' . $config->dbName . '`');
    }

    public function testMigrationRunnerIsIdempotentAndBuildsPlatformSpine(): void
    {
        $runner = new MigrationRunner($this->pdo, $this->migrationDirectory());

        $first = $runner->migrate();
        $second = $runner->migrate();

        self::assertGreaterThan(0, $first->appliedCount);
        self::assertSame(0, $second->appliedCount);
        self::assertSame(0, $second->pendingCount);
        self::assertTrue($second->isCurrent());
        self::assertSame(
            ['idempotency_keys', 'legal_notices', 'scheduled_jobs', 'schema_migrations', 'tool_definitions'],
            $this->platformTables(),
        );
        self::assertSame('0001_platform_spine', $this->pdo->query('SELECT version FROM schema_migrations')->fetchColumn());
        self::assertSame(5, (int) $this->pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('schema_migrations','legal_notices','idempotency_keys','scheduled_jobs','tool_definitions') AND engine='InnoDB' AND table_collation='utf8mb4_unicode_ci'")->fetchColumn());
        foreach (['demo_sandboxes', 'demo_personas', 'demo_project_role_assignments', 'demo_seed_items', 'demo_sandbox_events'] as $table) {
            $statement = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table_name');
            $statement->execute(['table_name' => $table]);
            self::assertSame(1, (int) $statement->fetchColumn(), $table . ' was not migrated.');
        }
        self::assertStringContainsString('json_valid', strtolower((string) $this->pdo->query('SHOW CREATE TABLE scheduled_jobs')->fetch(PDO::FETCH_NUM)[1]));
        self::assertSame(2, (int) $this->pdo->query("SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='scheduled_jobs' AND index_name IN ('idx_scheduled_jobs_claim','idx_scheduled_jobs_type_status')")->fetchColumn());
        $insertTool = $this->pdo->prepare("INSERT INTO tool_definitions (id,tool_key,label) VALUES (UNHEX(:id),:tool_key,:label)");
        $insertTool->execute(['id' => str_repeat('01', 16), 'tool_key' => 'proof_tool', 'label' => 'Proof']);
        try {
            $insertTool->execute(['id' => str_repeat('02', 16), 'tool_key' => 'proof_tool', 'label' => 'Duplicate']);
            self::fail('The tool key uniqueness contract must be enforced.');
        } catch (\PDOException) {
            self::addToAssertionCount(1);
        }
        try {
            $this->pdo->exec("INSERT INTO scheduled_jobs (id,job_type,payload,available_at) VALUES (UNHEX('03030303030303030303030303030303'),'proof','not-json',UTC_TIMESTAMP(6))");
            self::fail('The JSON payload contract must be enforced.');
        } catch (\PDOException) {
            self::addToAssertionCount(1);
        }
    }

    public function testChangedAppliedMigrationFailsClosedWithoutLeakingSqlAndReleasesLock(): void
    {
        $directory = $this->temporaryMigrationDirectory();
        file_put_contents($directory . '/0001_example.sql', "-- architex:migration-mode=ddl\nCREATE TABLE IF NOT EXISTS checksum_probe (id INT PRIMARY KEY) ENGINE=InnoDB;\n");
        $runner = new MigrationRunner($this->pdo, $directory);
        $runner->migrate();
        file_put_contents($directory . '/0001_example.sql', "-- architex:migration-mode=ddl\nCREATE TABLE IF NOT EXISTS checksum_probe (id BIGINT PRIMARY KEY, secret VARCHAR(40)) ENGINE=InnoDB;\n");

        try {
            $runner->migrate();
            self::fail('Expected checksum drift to fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('Migration checksum mismatch for 0001_example', $exception->getMessage());
            self::assertStringNotContainsString('secret', $exception->getMessage());
        } finally {
            self::assertSame(1, (int) $this->pdo->query("SELECT IS_FREE_LOCK('" . $this->lockName() . "')")->fetchColumn());
            $this->deleteDirectory($directory);
        }
    }

    public function testConnectionFactoryCreatesStrictMariaDbPdo(): void
    {
        self::assertSame(PDO::ERRMODE_EXCEPTION, $this->pdo->getAttribute(PDO::ATTR_ERRMODE));
        self::assertSame(PDO::FETCH_ASSOC, $this->pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
        self::assertSame(0, $this->pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));
        self::assertFalse($this->pdo->getAttribute(PDO::ATTR_STRINGIFY_FETCHES));
        try {
            $this->pdo->exec('SELECT 1; SELECT 2');
            self::fail('Connection must reject multiple statements in one execution.');
        } catch (\PDOException) {
            self::addToAssertionCount(1);
        }
        self::assertStringContainsString('MariaDB', (string) $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION));
        self::assertSame('+00:00', $this->pdo->query('SELECT @@session.time_zone')->fetchColumn());
    }

    public function testEmptyMigrationDirectoryCannotReportCurrent(): void
    {
        $directory = $this->temporaryMigrationDirectory();

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('No migration files found.');
            (new MigrationRunner($this->pdo, $directory))->status();
        } finally {
            $this->deleteDirectory($directory);
        }
    }

    public function testIncompatiblePreExistingPlatformTableCannotBeRecordedAsMigrated(): void
    {
        $this->pdo->exec('CREATE TABLE legal_notices (id INT PRIMARY KEY) ENGINE=InnoDB');
        $runner = new MigrationRunner($this->pdo, $this->migrationDirectory());

        try {
            $runner->migrate();
            self::fail('Expected an incompatible pre-existing table to fail the migration.');
        } catch (RuntimeException $exception) {
            self::assertSame('Migration failed: 0001_platform_spine', $exception->getMessage());
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn());
        }
    }

    public function testPartiallyCommittedDdlCanBeCorrectedAndRetried(): void
    {
        $directory = $this->temporaryMigrationDirectory();
        $file = $directory . '/0001_partial.sql';
        file_put_contents($file, "-- architex:migration-mode=ddl\nCREATE TABLE IF NOT EXISTS partial_first (id INT PRIMARY KEY) ENGINE=InnoDB;\nCREATE TABLE IF NOT EXISTS partial_second (id INT, id INT) ENGINE=InnoDB;\n");
        $runner = new MigrationRunner($this->pdo, $directory);
        try {
            $runner->migrate();
            self::fail('Expected the second DDL statement to fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('Migration failed: 0001_partial', $exception->getMessage());
            self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='partial_first'")->fetchColumn());
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn());
        }
        file_put_contents($file, "-- architex:migration-mode=ddl\nCREATE TABLE IF NOT EXISTS partial_first (id INT PRIMARY KEY) ENGINE=InnoDB;\nCREATE TABLE IF NOT EXISTS partial_second (id INT PRIMARY KEY) ENGINE=InnoDB;\n");
        self::assertSame(1, $runner->migrate()->appliedCount);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn());
        $this->deleteDirectory($directory);
    }

    public function testMalformedMigrationLedgerFailsClosed(): void
    {
        $this->pdo->exec('CREATE TABLE schema_migrations (version VARCHAR(64) PRIMARY KEY) ENGINE=InnoDB');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('schema_migrations table does not match the required contract.');
        (new MigrationRunner($this->pdo, $this->migrationDirectory()))->migrate();
    }

    public function testDmlMigrationRollsBackAllStatementsAndLedgerRecordOnFailure(): void
    {
        $directory = $this->temporaryMigrationDirectory();
        file_put_contents($directory . '/0001_seed_table.sql', "-- architex:migration-mode=ddl\nCREATE TABLE IF NOT EXISTS seed_probe (id INT PRIMARY KEY, label VARCHAR(20) NOT NULL) ENGINE=InnoDB;\n");
        file_put_contents($directory . '/0002_bad_seed.sql', "-- architex:migration-mode=dml\nINSERT INTO seed_probe (id,label) VALUES (1,'first');\nINSERT INTO seed_probe (id,label) VALUES (1,'duplicate');\n");
        try {
            (new MigrationRunner($this->pdo, $directory))->migrate();
            self::fail('Expected the duplicate DML statement to fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('Migration failed: 0002_bad_seed', $exception->getMessage());
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM seed_probe')->fetchColumn());
            self::assertSame(['0001_seed_table'], $this->pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN));
        } finally {
            $this->deleteDirectory($directory);
        }
    }

    public function testDuplicateNumericMigrationSequenceFailsBeforeExecution(): void
    {
        $directory = $this->temporaryMigrationDirectory();
        file_put_contents($directory . '/0001_alpha.sql', "-- architex:migration-mode=ddl\nCREATE TABLE IF NOT EXISTS alpha (id INT PRIMARY KEY);\n");
        file_put_contents($directory . '/0001_beta.sql', "-- architex:migration-mode=ddl\nCREATE TABLE IF NOT EXISTS beta (id INT PRIMARY KEY);\n");
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Duplicate migration sequence: 0001');
            (new MigrationRunner($this->pdo, $directory))->status();
        } finally {
            $this->deleteDirectory($directory);
        }
    }

    /** @return array<string,string> */
    private function databaseEnvironment(): array
    {
        $environment = [];
        foreach (['ARCHITEX_ALLOW_DISPOSABLE_DB', 'APP_ENV', 'APP_DEBUG', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $key) {
            $value = getenv($key);
            if ($value !== false) {
                $environment[$key] = $value;
            }
        }

        return $environment;
    }

    private function migrationDirectory(): string
    {
        return dirname(__DIR__, 3) . '/database/migrations';
    }

    /** @return list<string> */
    private function platformTables(): array
    {
        $statement = $this->pdo->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('schema_migrations','legal_notices','idempotency_keys','scheduled_jobs','tool_definitions') ORDER BY table_name",
        );

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    private function adminConnection(AppConfig $config): PDO
    {
        return new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config->dbHost, $config->dbPort),
            $config->dbUser,
            $config->dbPassword,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_MULTI_STATEMENTS => false],
        );
    }

    private function lockName(): string
    {
        return 'architex_migrate_' . substr(hash('sha256', (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn()), 0, 32);
    }

    private function temporaryMigrationDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/architex-migrations-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700, true));

        return $directory;
    }

    private function deleteDirectory(string $directory): void
    {
        foreach (glob($directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
}
