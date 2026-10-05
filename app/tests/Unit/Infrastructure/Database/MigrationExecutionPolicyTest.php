<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Infrastructure\Database;

use Architex\Infrastructure\Database\MigrationExecutionPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MigrationExecutionPolicyTest extends TestCase
{
    public function testDdlModeAllowsOnlyRetrySafeCreateTableStatements(): void
    {
        self::assertFalse((new MigrationExecutionPolicy())->isTransactional('ddl', [
            'CREATE TABLE IF NOT EXISTS example (id INT PRIMARY KEY) ENGINE=InnoDB',
        ]));
    }

    public function testDdlModeAllowsOnlyTheForwardCpdTrueFalseEnumExtension(): void
    {
        self::assertFalse((new MigrationExecutionPolicy())->isTransactional('ddl', [
            "ALTER TABLE cpd_questions MODIFY question_type ENUM('single_choice','multiple_choice','scenario','true_false','text_review') NOT NULL",
        ]));
    }

    /** @param list<string> $statements */
    public function testDdlModeAllowsOnlyTheExactToolboxPdfStorageUpgrade(): void
    {
        self::assertFalse((new MigrationExecutionPolicy())->isTransactional('ddl', [
            "ALTER TABLE tool_run_exports MODIFY format ENUM('json','csv','html','pdf') NOT NULL, MODIFY content MEDIUMBLOB NOT NULL",
        ]));
    }

    /** @param list<string> $statements */
    #[DataProvider('unsafeDdlProvider')]
    public function testDdlModeRejectsNonRetrySafeOrAdministrativeStatements(array $statements): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DDL migration contains a statement outside the retry-safe policy');
        (new MigrationExecutionPolicy())->isTransactional('ddl', $statements);
    }

    /** @return iterable<string,array{list<string>}> */
    public static function unsafeDdlProvider(): iterable
    {
        yield 'create without guard' => [['CREATE TABLE example (id INT)']];
        yield 'alter' => [['ALTER TABLE example ADD value INT']];
        yield 'analyze' => [['ANALYZE TABLE example']];
        yield 'optimize' => [['OPTIMIZE TABLE example']];
        yield 'repair' => [['REPAIR TABLE example']];
        yield 'flush' => [['FLUSH TABLES']];
        yield 'autocommit' => [['SET autocommit = 0']];
        yield 'other export table' => [["ALTER TABLE other_exports MODIFY format ENUM('json','csv','html','pdf') NOT NULL, MODIFY content MEDIUMBLOB NOT NULL"]];
        yield 'drops existing formats' => [["ALTER TABLE tool_run_exports MODIFY format ENUM('pdf') NOT NULL, MODIFY content MEDIUMBLOB NOT NULL"]];
        yield 'adds unapproved format' => [["ALTER TABLE tool_run_exports MODIFY format ENUM('json','csv','html','pdf','zip') NOT NULL, MODIFY content MEDIUMBLOB NOT NULL"]];
        yield 'changes nullability' => [["ALTER TABLE tool_run_exports MODIFY format ENUM('json','csv','html','pdf') NULL, MODIFY content MEDIUMBLOB NOT NULL"]];
    }

    public function testDmlModeAllowsOnlyExplicitTransactionalDmlAllowlist(): void
    {
        self::assertTrue((new MigrationExecutionPolicy())->isTransactional('dml', [
            "INSERT INTO example (id) VALUES (1)",
            "UPDATE example SET id = 2 WHERE id = 1",
            "DELETE FROM example WHERE id = 2",
        ]));
    }

    #[DataProvider('unsafeDmlProvider')]
    public function testDmlModeRejectsStatementsOutsideAllowlist(string $statement): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DML migration contains a statement outside the transactional policy');
        (new MigrationExecutionPolicy())->isTransactional('dml', [$statement]);
    }

    /** @return iterable<string,array{string}> */
    public static function unsafeDmlProvider(): iterable
    {
        yield 'select' => ['SELECT 1'];
        yield 'ddl' => ['CREATE TABLE IF NOT EXISTS example (id INT)'];
        yield 'set' => ['SET autocommit = 0'];
        yield 'admin' => ['ANALYZE TABLE example'];
    }

    public function testUnknownExecutionModeFailsClosed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported migration execution mode: automatic');
        (new MigrationExecutionPolicy())->isTransactional('automatic', ['INSERT INTO example VALUES (1)']);
    }
}
