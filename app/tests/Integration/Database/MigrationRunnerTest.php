<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Database;

use Architex\Infrastructure\Config\AppConfig;
use Architex\Infrastructure\Database\ConnectionFactory;
use Architex\Infrastructure\Database\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MigrationRunnerTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $config = AppConfig::fromEnvironment($this->databaseEnvironment());
        self::assertStringEndsWith('_test', $config->dbName, 'Integration tests refuse non-test schemas.');
        $this->pdo = ConnectionFactory::create($config);
        $this->resetDatabase();
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
    }

    public function testChangedAppliedMigrationFailsClosedWithoutLeakingSqlAndReleasesLock(): void
    {
        $directory = $this->temporaryMigrationDirectory();
        file_put_contents($directory . '/0001_example.sql', "CREATE TABLE checksum_probe (id INT PRIMARY KEY) ENGINE=InnoDB;\n");
        $runner = new MigrationRunner($this->pdo, $directory);
        $runner->migrate();
        file_put_contents($directory . '/0001_example.sql', "CREATE TABLE checksum_probe (id BIGINT PRIMARY KEY, secret VARCHAR(40)) ENGINE=InnoDB;\n");

        try {
            $runner->migrate();
            self::fail('Expected checksum drift to fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('Migration checksum mismatch for 0001_example', $exception->getMessage());
            self::assertStringNotContainsString('secret', $exception->getMessage());
        } finally {
            self::assertSame(1, (int) $this->pdo->query("SELECT IS_FREE_LOCK('architex_schema_migration')")->fetchColumn());
            $this->deleteDirectory($directory);
        }
    }

    public function testConnectionFactoryCreatesStrictMariaDbPdo(): void
    {
        self::assertSame(PDO::ERRMODE_EXCEPTION, $this->pdo->getAttribute(PDO::ATTR_ERRMODE));
        self::assertSame(PDO::FETCH_ASSOC, $this->pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
        self::assertSame(0, $this->pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));
        self::assertFalse($this->pdo->getAttribute(PDO::ATTR_STRINGIFY_FETCHES));
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

    /** @return array<string,string> */
    private function databaseEnvironment(): array
    {
        $environment = [];
        foreach (['APP_ENV', 'APP_DEBUG', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $key) {
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

    private function resetDatabase(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $tables = $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            if (!is_string($table) || preg_match('/^[A-Za-z0-9_]+$/D', $table) !== 1) {
                throw new RuntimeException('Unsafe test table identifier.');
            }
            $this->pdo->exec(sprintf('DROP TABLE `%s`', $table));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
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
