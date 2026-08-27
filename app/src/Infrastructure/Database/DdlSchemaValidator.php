<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Database;

use PDO;
use RuntimeException;

final readonly class DdlSchemaValidator
{
    public function __construct(private PDO $pdo, private SqlStatementLexer $lexer = new SqlStatementLexer()) {}

    public function assertCreateTableMatches(string $statement): void
    {
        $sql = $this->lexer->withoutLeadingComments($statement);
        if (preg_match('/^CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([a-z][a-z0-9_]*)`?/i', $sql, $matches) !== 1) {
            throw new RuntimeException('Could not determine the DDL migration table.');
        }
        $actual = strtolower($matches[1]);
        $expected = '__ax_expected_' . substr(hash('sha256', $statement), 0, 16);
        $expectedSql = preg_replace('/^(CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+)`?[a-z][a-z0-9_]*`?/i', '$1`' . $expected . '`', $sql, 1);
        if (!is_string($expectedSql)) {
            throw new RuntimeException('Could not construct the DDL schema check.');
        }
        $this->pdo->exec('DROP TABLE IF EXISTS `' . $expected . '`');
        try {
            $this->pdo->exec($expectedSql);
            if (!hash_equals($this->normalise($this->showCreate($expected), $expected), $this->normalise($this->showCreate($actual), $actual))) {
                throw new RuntimeException('Existing table does not match the migration definition: ' . $actual);
            }
        } finally {
            $this->pdo->exec('DROP TABLE IF EXISTS `' . $expected . '`');
        }
    }

    private function showCreate(string $table): string
    {
        $row = $this->pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM);
        if (!is_array($row) || !isset($row[1]) || !is_string($row[1])) {
            throw new RuntimeException('Could not inspect a migrated table.');
        }
        return $row[1];
    }

    private function normalise(string $definition, string $table): string
    {
        $definition = str_replace('`' . $table . '`', '`{table}`', $definition);
        $definition = preg_replace('/\s+AUTO_INCREMENT=\d+\b/i', '', $definition) ?? $definition;
        return preg_replace('/\s+/', ' ', trim($definition)) ?? $definition;
    }
}
