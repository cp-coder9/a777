<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Logging;

use Closure;
use Throwable;

final readonly class JsonLogger
{
    /** @param Closure(string):void $writer */
    public function __construct(private Closure $writer) {}

    public static function toFile(string $path): self
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            @mkdir($directory, 0700, true);
        }
        return new self(static function (string $line) use ($path): void {
            @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        });
    }

    /** @param array<string,mixed> $context */
    public function info(string $event, array $context = []): void
    {
        $this->write('info', $event, $context);
    }

    /** @param array<string,mixed> $context */
    public function error(string $event, array $context = []): void
    {
        $this->write('error', $event, $context);
    }

    /** @param array<string,mixed> $context */
    private function write(string $level, string $event, array $context): void
    {
        try {
            $line = json_encode([
                'timestamp'=>gmdate('c'),
                'level'=>$level,
                'event'=>$this->cleanString($event, 120),
                'context'=>$this->redact($context),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            ($this->writer)($line);
        } catch (Throwable) {
        }
    }

    private function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && preg_match('/password|passwd|secret|token|authorization|cookie|csrf|api.?key|private.?key/i', $key) === 1) {
            return '[REDACTED]';
        }
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $childKey => $child) {
                $clean[$childKey] = $this->redact($child, is_string($childKey) ? $childKey : null);
            }
            return $clean;
        }
        if (is_string($value)) {
            $clean = preg_replace('#\b([a-z][a-z0-9+.-]*://)[^/@\s]+@#i', '$1[REDACTED]@', $value) ?? $value;
            $clean = preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/-]+=*/i', 'Bearer [REDACTED]', $clean) ?? $clean;
            return $this->cleanString($clean, 4_000);
        }
        return is_scalar($value) || $value === null ? $value : '[UNSERIALIZABLE]';
    }

    private function cleanString(string $value, int $maximum): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? '';
        return strlen($value) > $maximum ? substr($value, 0, $maximum) : $value;
    }
}
