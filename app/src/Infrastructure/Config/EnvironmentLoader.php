<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Config;

use RuntimeException;

final class EnvironmentLoader
{
    public static function apply(string $applicationRoot): void
    {
        $path = $applicationRoot . '/config/runtime.php';
        if (!is_file($path)) return;
        if (is_link($path) || !is_readable($path)) throw new RuntimeException('The runtime configuration is unavailable.');
        $values = require $path;
        if (!is_array($values)) throw new RuntimeException('The runtime configuration is invalid.');
        foreach ($values as $key => $value) {
            if (!is_string($key) || preg_match('/\A[A-Z][A-Z0-9_]{1,63}\z/', $key) !== 1 || !is_string($value) || str_contains($value, "\0")) {
                throw new RuntimeException('The runtime configuration is invalid.');
            }
            if (getenv($key) !== false) continue;
            if (!putenv($key . '=' . $value)) throw new RuntimeException('The runtime configuration could not be applied.');
            $_ENV[$key] = $value;
        }
    }
}
