<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Infrastructure;

use Architex\Infrastructure\Config\AppConfig;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AppConfigTest extends TestCase
{
    public function testValidEnvironmentProducesTypedConfiguration(): void
    {
        $config = AppConfig::fromEnvironment($this->validEnvironment());

        self::assertSame('test', $config->appEnv);
        self::assertFalse($config->appDebug);
        self::assertSame('127.0.0.1', $config->dbHost);
        self::assertSame(43317, $config->dbPort);
        self::assertSame('architex_task3_test', $config->dbName);
        self::assertSame('architex_test', $config->dbUser);
        self::assertSame('example-test-password', $config->dbPassword);
        self::assertSame('production', $config->appMode);
    }

    public function testApplicationModeDefaultsToProductionAcceptsDemoAndRejectsOtherValues(): void
    {
        self::assertSame('production', AppConfig::fromEnvironment($this->validEnvironment())->appMode);

        $environment = $this->validEnvironment();
        $environment['APP_MODE'] = 'demo';
        self::assertSame('demo', AppConfig::fromEnvironment($environment)->appMode);

        $environment['APP_MODE'] = 'preview';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('APP_MODE must be production or demo');
        AppConfig::fromEnvironment($environment);
    }

    public function testInvalidDatabaseConfigurationNeverLeaksPassword(): void
    {
        try {
            AppConfig::fromEnvironment(['DB_PORT' => 'bad', 'DB_PASSWORD' => 'top-secret']);
            self::fail('Expected invalid database configuration to fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('DB_PORT must be an integer between 1 and 65535', $exception->getMessage());
            self::assertStringNotContainsString('top-secret', $exception->getMessage());
            self::assertStringNotContainsString('DB_PASSWORD', $exception->getMessage());
        }
    }

    public function testDatabasePasswordIsPreservedByteForByte(): void
    {
        $environment = $this->validEnvironment();
        $environment['DB_PASSWORD'] = "  exact password with spaces  ";

        self::assertSame($environment['DB_PASSWORD'], AppConfig::fromEnvironment($environment)->dbPassword);
    }

    /** @param array<string,string> $override */
    #[DataProvider('invalidEnvironmentProvider')]
    public function testInvalidValuesFailWithSafeFieldSpecificMessages(array $override, string $message): void
    {
        $environment = array_replace($this->validEnvironment(), $override);
        if (!array_key_exists('DB_PASSWORD', $override)) {
            $environment['DB_PASSWORD'] = 'credential-that-must-not-leak';
        }

        try {
            AppConfig::fromEnvironment($environment);
            self::fail('Expected invalid configuration to fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame($message, $exception->getMessage());
            if ($environment['DB_PASSWORD'] !== '') {
                self::assertStringNotContainsString($environment['DB_PASSWORD'], $exception->getMessage());
            }
        }
    }

    /** @return iterable<string,array{array<string,string>,string}> */
    public static function invalidEnvironmentProvider(): iterable
    {
        yield 'port below range' => [['DB_PORT' => '0'], 'DB_PORT must be an integer between 1 and 65535'];
        yield 'port above range' => [['DB_PORT' => '65536'], 'DB_PORT must be an integer between 1 and 65535'];
        yield 'boolean is strict' => [['APP_DEBUG' => 'sometimes'], 'APP_DEBUG must be true or false'];
        yield 'environment is enumerated' => [['APP_ENV' => 'prod'], 'APP_ENV must be one of production, staging, development, test'];
        yield 'host cannot alter DSN' => [['DB_HOST' => 'localhost;dbname=other'], 'DB_HOST contains invalid characters'];
        yield 'database is an identifier' => [['DB_NAME' => 'architex-test'], 'DB_NAME must contain only letters, numbers, and underscores'];
        yield 'test database is isolated' => [['DB_NAME' => 'architex'], 'DB_NAME must end in _test when APP_ENV is test'];
        yield 'production password required' => [
            ['APP_ENV' => 'production', 'DB_NAME' => 'architex', 'DB_PASSWORD' => ''],
            'DB_PASSWORD is required in production',
        ];
        yield 'production debug forbidden' => [
            ['APP_ENV' => 'production', 'DB_NAME' => 'architex', 'APP_DEBUG' => 'true'],
            'APP_DEBUG must be false in production',
        ];
    }

    /** @param array<string,mixed> $override */
    #[DataProvider('nonStringEnvironmentProvider')]
    public function testTextConfigurationRejectsNonStringValues(array $override, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        AppConfig::fromEnvironment(array_replace($this->validEnvironment(), $override));
    }

    /** @return iterable<string,array{array<string,mixed>,string}> */
    public static function nonStringEnvironmentProvider(): iterable
    {
        yield 'environment boolean' => [['APP_ENV' => true], 'APP_ENV must be a string'];
        yield 'host integer' => [['DB_HOST' => 127001], 'DB_HOST must be a string'];
        yield 'database boolean' => [['DB_NAME' => false], 'DB_NAME must be a string'];
        yield 'user integer' => [['DB_USER' => 42], 'DB_USER must be a string'];
    }

    /** @return array<string,string> */
    private function validEnvironment(): array
    {
        return [
            'APP_ENV' => 'test',
            'APP_DEBUG' => 'false',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '43317',
            'DB_NAME' => 'architex_task3_test',
            'DB_USER' => 'architex_test',
            'DB_PASSWORD' => 'example-test-password',
        ];
    }
}
