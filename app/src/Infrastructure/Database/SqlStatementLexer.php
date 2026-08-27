<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Database;

use RuntimeException;

final class SqlStatementLexer
{
    /** @return list<string> */
    public function split(string $sql): array
    {
        if (str_contains($sql, "\0")) {
            throw new RuntimeException('NUL bytes are not allowed in migration SQL');
        }

        $statements = [];
        $statement = '';
        $state = 'normal';
        $hasCode = false;
        $lineStart = true;
        $length = strlen($sql);

        for ($index = 0; $index < $length; $index++) {
            $character = $sql[$index];
            $next = $index + 1 < $length ? $sql[$index + 1] : '';

            if ($state === 'normal') {
                if ($lineStart && ($character === ' ' || $character === "\t" || $character === "\r")) {
                    $statement .= $character;
                    continue;
                }
                if ($lineStart && $this->isClientCommand(substr($sql, $index))) {
                    throw new RuntimeException('DELIMITER and client commands are not supported in migration SQL');
                }
                $lineStart = false;

                if ($character === '-' && $next === '-' && $this->startsDashComment($sql, $index + 2)) {
                    $state = 'line-comment';
                    $statement .= '--';
                    $index++;
                    continue;
                }
                if ($character === '#') {
                    $state = 'line-comment';
                    $statement .= $character;
                    continue;
                }
                if ($character === '/' && $next === '*') {
                    if (($sql[$index + 2] ?? '') === '!' || strncasecmp(substr($sql, $index, 4), '/*M!', 4) === 0) {
                        throw new RuntimeException('Executable comments are not supported in migration SQL');
                    }
                    $state = 'block-comment';
                    $statement .= '/*';
                    $index++;
                    continue;
                }
                if ($character === "'") {
                    $state = 'single-quote';
                    $hasCode = true;
                    $statement .= $character;
                    continue;
                }
                if ($character === '"') {
                    $state = 'double-quote';
                    $hasCode = true;
                    $statement .= $character;
                    continue;
                }
                if ($character === '`') {
                    $state = 'backtick';
                    $hasCode = true;
                    $statement .= $character;
                    continue;
                }
                if ($character === ';') {
                    if ($hasCode) {
                        $statements[] = trim($statement);
                    }
                    $statement = '';
                    $hasCode = false;
                    continue;
                }

                $statement .= $character;
                if (!ctype_space($character)) {
                    $hasCode = true;
                }
                if ($character === "\n") {
                    $lineStart = true;
                }
                continue;
            }

            $statement .= $character;
            if ($state === 'line-comment') {
                if ($character === "\n") {
                    $state = 'normal';
                    $lineStart = true;
                }
                continue;
            }
            if ($state === 'block-comment') {
                if ($character === '*' && $next === '/') {
                    $statement .= '/';
                    $index++;
                    $state = 'normal';
                }
                continue;
            }
            if ($state === 'single-quote' || $state === 'double-quote') {
                $quote = $state === 'single-quote' ? "'" : '"';
                if ($character === '\\') {
                    if ($next !== '') {
                        $statement .= $next;
                        $index++;
                    }
                    continue;
                }
                if ($character === $quote) {
                    if ($next === $quote) {
                        $statement .= $next;
                        $index++;
                    } else {
                        $state = 'normal';
                    }
                }
                continue;
            }
            if ($state === 'backtick' && $character === '`') {
                if ($next === '`') {
                    $statement .= $next;
                    $index++;
                } else {
                    $state = 'normal';
                }
            }
        }

        if ($state === 'single-quote' || $state === 'double-quote') {
            throw new RuntimeException('Unterminated quoted string in migration SQL');
        }
        if ($state === 'backtick') {
            throw new RuntimeException('Unterminated quoted identifier in migration SQL');
        }
        if ($state === 'block-comment') {
            throw new RuntimeException('Unterminated block comment in migration SQL');
        }
        if ($hasCode) {
            $statements[] = trim($statement);
        }

        foreach ($statements as $candidate) {
            if ($this->isClientCommand($this->withoutLeadingComments($candidate))) {
                throw new RuntimeException('DELIMITER and client commands are not supported in migration SQL');
            }
        }

        return $statements;
    }

    public function withoutLeadingComments(string $statement): string
    {
        $remaining = ltrim($statement);
        while ($remaining !== '') {
            if (str_starts_with($remaining, '#') || preg_match('/^--(?:\s|$)/', $remaining) === 1) {
                $newline = strpos($remaining, "\n");
                $remaining = $newline === false ? '' : ltrim(substr($remaining, $newline + 1));
                continue;
            }
            if (str_starts_with($remaining, '/*')) {
                $end = strpos($remaining, '*/', 2);
                if ($end === false) {
                    throw new RuntimeException('Unterminated block comment in migration SQL');
                }
                $remaining = ltrim(substr($remaining, $end + 2));
                continue;
            }
            break;
        }

        return $remaining;
    }

    private function startsDashComment(string $sql, int $offset): bool
    {
        if ($offset >= strlen($sql)) {
            return true;
        }

        return ord($sql[$offset]) <= 32;
    }

    private function isClientCommand(string $remaining): bool
    {
        return preg_match('/^(?:DELIMITER|SOURCE)\b/i', $remaining) === 1
            || str_starts_with($remaining, '\\.')
            || str_starts_with($remaining, '\\!');
    }
}
