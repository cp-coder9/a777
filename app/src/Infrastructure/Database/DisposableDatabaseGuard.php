<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Database;

use RuntimeException;

final class DisposableDatabaseGuard
{
    /** @param array<string,string> $environment */
    public static function assertAllowed(array $environment): void
    {
        if (($environment['ARCHITEX_ALLOW_DISPOSABLE_DB'] ?? '') !== '1') {
            throw new RuntimeException('Disposable database operations require explicit opt-in.');
        }
        if (($environment['APP_ENV'] ?? '') !== 'test') {
            throw new RuntimeException('Disposable database operations require APP_ENV=test.');
        }
        if (!in_array($environment['DB_HOST'] ?? '', ['127.0.0.1', '::1'], true)) {
            throw new RuntimeException('Disposable database operations require a loopback database host.');
        }
        if (preg_match('/^architex_task[a-z0-9]+_[a-f0-9]{8}_test$/D', $environment['DB_NAME'] ?? '') !== 1) {
            throw new RuntimeException('Disposable database name is outside the task-owned namespace.');
        }
    }
}
