<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Infrastructure\Database;

use Architex\Infrastructure\Database\SqlStatementLexer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SqlStatementLexerTest extends TestCase
{
    public function testSplitsOnlyOnUnquotedUncommentedSemicolons(): void
    {
        $sql = <<<'SQL'
-- a line comment containing ;
CREATE TABLE IF NOT EXISTS `odd;table` (`value` VARCHAR(50) DEFAULT 'a;b', `quoted``name` INT);
# a hash comment containing ;
INSERT INTO `odd;table` (`value`) VALUES ('it\'s;safe'), ("also;safe");
/* a block comment ; spanning
   lines ; */
UPDATE `odd;table` SET `value` = 'done;still-one' WHERE `value` = 'a;b';
SQL;

        $statements = (new SqlStatementLexer())->split($sql);

        self::assertCount(3, $statements);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `odd;table`', $statements[0]);
        self::assertStringContainsString("VALUES ('it\\'s;safe'), (\"also;safe\")", $statements[1]);
        self::assertStringContainsString("'done;still-one'", $statements[2]);
    }

    #[DataProvider('unsupportedSqlProvider')]
    public function testRejectsClientCommandsAndUnterminatedLexicalStates(string $sql, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        (new SqlStatementLexer())->split($sql);
    }

    /** @return iterable<string,array{string,string}> */
    public static function unsupportedSqlProvider(): iterable
    {
        yield 'delimiter' => ["DELIMITER $$\nCREATE PROCEDURE p() SELECT 1$$", 'DELIMITER and client commands are not supported'];
        yield 'source' => ["SOURCE other.sql;", 'DELIMITER and client commands are not supported'];
        yield 'backslash command' => ["\\. other.sql", 'DELIMITER and client commands are not supported'];
        yield 'single quote' => ["INSERT INTO t VALUES ('unterminated);", 'Unterminated quoted string in migration SQL'];
        yield 'backtick' => ["SELECT `unterminated;", 'Unterminated quoted identifier in migration SQL'];
        yield 'block comment' => ["SELECT 1; /* unterminated", 'Unterminated block comment in migration SQL'];
    }
}
