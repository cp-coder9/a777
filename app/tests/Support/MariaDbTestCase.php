<?php

declare(strict_types=1);

namespace Architex\Tests\Support;

use Architex\Infrastructure\Config\AppConfig;
use Architex\Infrastructure\Database\ConnectionFactory;
use Architex\Infrastructure\Database\DisposableDatabaseGuard;
use Architex\Infrastructure\Database\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class MariaDbTestCase extends TestCase
{
    private const SEED_TABLES = ['form_templates','community_channels','community_channel_governance','drawing_compliance_sources','cpd_professional_body_rules'];
    private const BASELINE_PREFIX = '__architex_test_baseline_';
    private static bool $schemaReady = false;
    private static bool $cleanupRegistered = false;
    private static string $databaseSignature = '';
    protected PDO $pdo;
    /** @var array<string,string> */
    private array $databaseEnvironment;

    protected function setUp(): void
    {
        $this->databaseEnvironment = $this->readDatabaseEnvironment();
        if (($this->databaseEnvironment['ARCHITEX_ALLOW_DISPOSABLE_DB'] ?? '') !== '1') {
            $this->markTestSkipped('Disposable MariaDB integration test was not explicitly enabled.');
        }
        DisposableDatabaseGuard::assertAllowed($this->databaseEnvironment);
        $config = AppConfig::fromEnvironment($this->databaseEnvironment);
        $admin = $this->adminConnection($config);
        $signature = implode(':', [$config->dbHost,$config->dbPort,$config->dbName,$config->dbUser]);
        $exists = (int) $admin->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=" . $admin->quote($config->dbName) . " AND table_name='schema_migrations'")->fetchColumn() === 1;
        if (!self::$schemaReady || self::$databaseSignature !== $signature || !$exists) {
            $admin->exec('DROP DATABASE IF EXISTS `' . $config->dbName . '`');
            $admin->exec('CREATE DATABASE `' . $config->dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->pdo = ConnectionFactory::create($config);
            (new MigrationRunner($this->pdo, dirname(__DIR__, 2) . '/database/migrations'))->migrate();
            $this->createSeedBaselines();
            self::$schemaReady = true;
            self::$databaseSignature = $signature;
            $this->registerCleanup($config);
            return;
        }
        $this->pdo = ConnectionFactory::create($config);
        $this->resetData();
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo)) {
            return;
        }
        unset($this->pdo);
    }

    private function createSeedBaselines(): void
    {
        foreach (self::SEED_TABLES as $table) {
            $baseline = self::BASELINE_PREFIX . $table;
            $this->pdo->exec("CREATE TABLE `$baseline` LIKE `$table`");
            $this->pdo->exec("INSERT INTO `$baseline` SELECT * FROM `$table`");
        }
    }

    private function resetData(): void
    {
        // A failed test must not leak its transaction into cleanup or the next test.
        if ($this->pdo->inTransaction()) $this->pdo->rollBack();
        $rows = $this->pdo->query("SELECT table_name, engine FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE' ORDER BY table_name")->fetchAll(PDO::FETCH_ASSOC);
        $tables = [];
        $transactional = true;
        foreach ($rows as $row) {
            $table = (string) $row['table_name'];
            if ($table === 'schema_migrations' || str_starts_with($table, self::BASELINE_PREFIX)) continue;
            $tables[] = $table;
            if (strtoupper((string) $row['engine']) !== 'INNODB') $transactional = false;
        }
        // DELETE preserves auto-increment counters and invokes triggers, unlike TRUNCATE.
        // Keep the original reset semantics if a future fixture introduces either feature.
        $hasSequences = (int) $this->pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND extra LIKE '%auto_increment%'")->fetchColumn() > 0;
        $hasTriggers = (int) $this->pdo->query("SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE()")->fetchColumn() > 0;
        $transactional = $transactional && !$hasSequences && !$hasTriggers;
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            if ($transactional) $this->pdo->beginTransaction();
            // Windows TRUNCATE recreates every table's files; one DELETE transaction avoids
            // hundreds of synchronous DDL operations without weakening data isolation.
            foreach ($tables as $table) {
                $this->pdo->exec(($transactional ? 'DELETE FROM `' : 'TRUNCATE TABLE `') . str_replace('`', '``', $table) . '`');
            }
            foreach (self::SEED_TABLES as $table) {
                $baseline = self::BASELINE_PREFIX . $table;
                $this->pdo->exec("INSERT INTO `$table` SELECT * FROM `$baseline`");
            }
            if ($transactional) $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function registerCleanup(AppConfig $config): void
    {
        if (self::$cleanupRegistered) return;
        self::$cleanupRegistered = true;
        $environment = $this->databaseEnvironment;
        register_shutdown_function(static function () use ($config, $environment): void {
            try {
                DisposableDatabaseGuard::assertAllowed($environment);
                $pdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config->dbHost, $config->dbPort), $config->dbUser, $config->dbPassword, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
                $pdo->exec('DROP DATABASE IF EXISTS `' . $config->dbName . '`');
            } catch (\Throwable) {}
        });
    }

    /** @return array<string,string> */
    private function readDatabaseEnvironment(): array
    {
        $values = [];
        foreach (['ARCHITEX_ALLOW_DISPOSABLE_DB','APP_ENV','APP_DEBUG','DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASSWORD'] as $key) {
            $value = getenv($key);
            if ($value !== false) {
                $values[$key] = $value;
            }
        }
        return $values;
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
}
