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
        $admin->exec('DROP DATABASE IF EXISTS `' . $config->dbName . '`');
        $admin->exec('CREATE DATABASE `' . $config->dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->pdo = ConnectionFactory::create($config);
        (new MigrationRunner($this->pdo, dirname(__DIR__, 2) . '/database/migrations'))->migrate();
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo)) {
            return;
        }
        DisposableDatabaseGuard::assertAllowed($this->databaseEnvironment);
        $config = AppConfig::fromEnvironment($this->databaseEnvironment);
        unset($this->pdo);
        $this->adminConnection($config)->exec('DROP DATABASE IF EXISTS `' . $config->dbName . '`');
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
