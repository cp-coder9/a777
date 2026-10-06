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
                $createTable = preg_match('/^CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\b/is', $sql) === 1;
                $cpdTrueFalseEnumExtension = preg_match("/^ALTER\\s+TABLE\\s+`?cpd_questions`?\\s+MODIFY\\s+(?:COLUMN\\s+)?`?question_type`?\\s+ENUM\\s*\\(\\s*'single_choice'\\s*,\\s*'multiple_choice'\\s*,\\s*'scenario'\\s*,\\s*'true_false'\\s*,\\s*'text_review'\\s*\\)\\s+NOT\\s+NULL\\s*$/is", $sql) === 1;
                $toolboxPdfStorageUpgrade = preg_replace('/\s+/', ' ', trim($sql)) === "ALTER TABLE tool_run_exports MODIFY format ENUM('json','csv','html','pdf') NOT NULL, MODIFY content MEDIUMBLOB NOT NULL";
                if (!$createTable && !$cpdTrueFalseEnumExtension && !$toolboxPdfStorageUpgrade) {
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
