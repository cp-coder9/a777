<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Config;

use InvalidArgumentException;

final readonly class AppConfig
{
    private const ENVIRONMENTS = ['production', 'staging', 'development', 'test'];

    private function __construct(
        public string $appEnv,
        public bool $appDebug,
        public string $dbHost,
        public int $dbPort,
        public string $dbName,
        public string $dbUser,
        public string $dbPassword,
    ) {
    }

    /** @param array<string,mixed> $environment */
    public static function fromEnvironment(array $environment): self
    {
        $port = self::parsePort($environment['DB_PORT'] ?? '3306');
        $appEnv = self::stringValue($environment, 'APP_ENV', 'production');
        if (!in_array($appEnv, self::ENVIRONMENTS, true)) {
            throw new InvalidArgumentException('APP_ENV must be one of production, staging, development, test');
        }

        $appDebug = self::parseBoolean($environment['APP_DEBUG'] ?? 'false');
        $host = self::requiredString($environment, 'DB_HOST');
        if (preg_match('/^[A-Za-z0-9._:\[\]-]+$/D', $host) !== 1) {
            throw new InvalidArgumentException('DB_HOST contains invalid characters');
        }

        $name = self::requiredString($environment, 'DB_NAME');
        if (preg_match('/^[A-Za-z0-9_]+$/D', $name) !== 1) {
            throw new InvalidArgumentException('DB_NAME must contain only letters, numbers, and underscores');
        }
        if ($appEnv === 'test' && !str_ends_with($name, '_test')) {
            throw new InvalidArgumentException('DB_NAME must end in _test when APP_ENV is test');
        }

        $user = self::requiredString($environment, 'DB_USER');
        $password = self::passwordValue($environment);
        if ($appEnv === 'production' && $password === '') {
            throw new InvalidArgumentException('DB_PASSWORD is required in production');
        }
        if ($appEnv === 'production' && $appDebug) {
            throw new InvalidArgumentException('APP_DEBUG must be false in production');
        }

        return new self($appEnv, $appDebug, $host, $port, $name, $user, $password);
    }

    private static function parsePort(mixed $value): int
    {
        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException('DB_PORT must be an integer between 1 and 65535');
        }
        $raw = (string) $value;
        if (preg_match('/^[0-9]+$/D', $raw) !== 1) {
            throw new InvalidArgumentException('DB_PORT must be an integer between 1 and 65535');
        }
        $port = (int) $raw;
        if ($port < 1 || $port > 65_535) {
            throw new InvalidArgumentException('DB_PORT must be an integer between 1 and 65535');
        }

        return $port;
    }

    private static function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return match (strtolower($value)) {
                'true', '1' => true,
                'false', '0' => false,
                default => throw new InvalidArgumentException('APP_DEBUG must be true or false'),
            };
        }

        throw new InvalidArgumentException('APP_DEBUG must be true or false');
    }

    /** @param array<string,mixed> $environment */
    private static function requiredString(array $environment, string $key): string
    {
        $value = self::stringValue($environment, $key);
        if ($value === '') {
            throw new InvalidArgumentException($key . ' is required');
        }

        return $value;
    }

    /** @param array<string,mixed> $environment */
    private static function stringValue(array $environment, string $key, string $default = ''): string
    {
        $value = $environment[$key] ?? $default;
        if (!is_string($value) && !is_int($value) && !is_bool($value)) {
            throw new InvalidArgumentException($key . ' must be a string');
        }

        return trim((string) $value);
    }

    /** @param array<string,mixed> $environment */
    private static function passwordValue(array $environment): string
    {
        $value = $environment['DB_PASSWORD'] ?? '';
        if (!is_string($value)) {
            throw new InvalidArgumentException('DB_PASSWORD must be a string');
        }

        return $value;
    }
}
