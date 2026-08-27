<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Infrastructure\Database;

use Architex\Infrastructure\Database\DisposableDatabaseGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DisposableDatabaseGuardTest extends TestCase
{
    public function testAcceptsAnExplicitlyOptedInTaskOwnedLoopbackDatabase(): void
    {
        DisposableDatabaseGuard::assertAllowed($this->validEnvironment());
        self::addToAssertionCount(1);
    }

    /** @param array<string,string> $override */
    #[DataProvider('unsafeEnvironmentProvider')]
    public function testRejectsUnsafeDatabaseTargets(array $override): void
    {
        $this->expectException(RuntimeException::class);
        DisposableDatabaseGuard::assertAllowed(array_replace($this->validEnvironment(), $override));
    }

    /** @return iterable<string,array{array<string,string>}> */
    public static function unsafeEnvironmentProvider(): iterable
    {
        yield 'no opt in' => [['ARCHITEX_ALLOW_DISPOSABLE_DB' => '0']];
        yield 'production environment' => [['APP_ENV' => 'production']];
        yield 'remote host' => [['DB_HOST' => 'db.example.com']];
        yield 'localhost hostname is not exact loopback' => [['DB_HOST' => 'localhost']];
        yield 'generic test suffix' => [['DB_NAME' => 'customer_test']];
        yield 'old shared task schema' => [['DB_NAME' => 'architex_task3_test']];
        yield 'identifier injection' => [['DB_NAME' => 'architex_task3_deadbeef_test`; DROP DATABASE prod;--']];
    }

    /** @return array<string,string> */
    private function validEnvironment(): array
    {
        return [
            'ARCHITEX_ALLOW_DISPOSABLE_DB' => '1',
            'APP_ENV' => 'test',
            'DB_HOST' => '127.0.0.1',
            'DB_NAME' => 'architex_task3_deadbeef_test',
        ];
    }
}
