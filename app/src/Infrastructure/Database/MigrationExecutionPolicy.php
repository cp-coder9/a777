<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Database;

use RuntimeException;

final readonly class MigrationExecutionPolicy
{
    public function __construct(private SqlStatementLexer $lexer = new SqlStatementLexer())
    {
    }

    /** @param list<string> $statements */
    public function isTransactional(string $mode, array $statements): bool
    {
        if ($mode === 'ddl') {
            foreach ($statements as $statement) {
                $sql = $this->lexer->withoutLeadingComments($statement);
                if (preg_match('/^CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\b/is', $sql) !== 1) {
                    throw new RuntimeException('DDL migration contains a statement outside the retry-safe policy');
                }
            }

            return false;
        }

        if ($mode === 'dml') {
            foreach ($statements as $statement) {
                $sql = $this->lexer->withoutLeadingComments($statement);
                if (preg_match('/^(?:INSERT|UPDATE|DELETE)\b/is', $sql) !== 1) {
                    throw new RuntimeException('DML migration contains a statement outside the transactional policy');
                }
            }

            return true;
        }

        throw new RuntimeException('Unsupported migration execution mode: ' . $mode);
    }
}
