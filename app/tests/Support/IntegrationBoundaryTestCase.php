<?php
declare(strict_types=1);

namespace Architex\Tests\Support;

use Architex\Infrastructure\Config\AppConfig;
use Architex\Infrastructure\Database\ConnectionFactory;
use Architex\Infrastructure\Database\DisposableDatabaseGuard;
use Architex\Infrastructure\Database\MigrationExecutionPolicy;
use Architex\Infrastructure\Database\SqlStatementLexer;
use PDO;
use PHPUnit\Framework\TestCase;

/** Integration-owned fixture: actual prerequisite DDL, never application runtime config. */
abstract class IntegrationBoundaryTestCase extends TestCase
{
    private static bool $ready=false;
    protected PDO $pdo;

    protected function setUp(): void
    {
        $environment=[];
        foreach (['ARCHITEX_ALLOW_DISPOSABLE_DB','APP_ENV','APP_DEBUG','DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASSWORD'] as $key) {
            $value=getenv($key); if (is_string($value)) $environment[$key]=$value;
        }
        if (($environment['ARCHITEX_ALLOW_DISPOSABLE_DB'] ?? '')!=='1') $this->markTestSkipped('Disposable loopback database was not enabled.');
        DisposableDatabaseGuard::assertAllowed($environment);
        $config=AppConfig::fromEnvironment($environment);
        if (!self::$ready) {
            $admin=new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4',$config->dbHost,$config->dbPort),$config->dbUser,$config->dbPassword,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::MYSQL_ATTR_MULTI_STATEMENTS=>false]);
            $admin->exec('CREATE DATABASE `'.$config->dbName.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->pdo=ConnectionFactory::create($config);
            $lexer=new SqlStatementLexer(); $policy=new MigrationExecutionPolicy($lexer);
            $directory=dirname(__DIR__,2).'/database/migrations';
            foreach (glob($directory.'/*.sql') as $file) {
                $sequence=(int)substr(basename($file),0,4);
                if (!($sequence<=14 || in_array($sequence,[29,30,57,67],true))) continue;
                $statements=$lexer->split((string)file_get_contents($file));
                $policy->isTransactional('ddl',$statements);
                foreach ($statements as $sql) $this->pdo->exec($sql);
            }
            self::$ready=true;
            register_shutdown_function(static function () use ($environment,$config): void {
                DisposableDatabaseGuard::assertAllowed($environment);
                $admin=new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4',$config->dbHost,$config->dbPort),$config->dbUser,$config->dbPassword,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
                $admin->exec('DROP DATABASE IF EXISTS `'.$config->dbName.'`');
            });
            return;
        }
        $this->pdo=ConnectionFactory::create($config);
        $tables=$this->pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $this->pdo->beginTransaction();
        try {
            foreach ($tables as $table) $this->pdo->exec('DELETE FROM `'.str_replace('`','``',$table).'`');
            $this->pdo->commit();
        } finally {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
