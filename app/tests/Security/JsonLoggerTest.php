<?php

declare(strict_types=1);

namespace Architex\Tests\Security;

use Architex\Infrastructure\Logging\JsonLogger;
use PHPUnit\Framework\TestCase;

final class JsonLoggerTest extends TestCase
{
    public function testStructuredLogsRecursivelyRedactCredentialsAndControlCharacters(): void
    {
        $lines = [];
        $logger = new JsonLogger(static function (string $line) use (&$lines): void { $lines[] = $line; });

        $logger->error("request.failed\r\nforged", [
            'password' => 'never-store-me',
            'nested' => ['authorization' => 'Bearer bearer-secret'],
            'databaseUrl' => 'mysql://admin:database-secret@db.example.test/architex',
            'safe' => 'preserved',
        ]);

        self::assertCount(1, $lines);
        self::assertStringNotContainsString('never-store-me', $lines[0]);
        self::assertStringNotContainsString('bearer-secret', $lines[0]);
        self::assertStringNotContainsString('database-secret', $lines[0]);
        self::assertStringNotContainsString("\r", $lines[0]);
        self::assertStringContainsString('preserved', $lines[0]);
        self::assertIsArray(json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR));
    }
}
