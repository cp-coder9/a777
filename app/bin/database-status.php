<?php

declare(strict_types=1);

use Architex\Infrastructure\Config\AppConfig;
use Architex\Infrastructure\Database\ConnectionFactory;
use Architex\Infrastructure\Database\MigrationRunner;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$environment = getenv();
if (!is_array($environment)) {
    $environment = [];
}
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--env=')) {
        $environment['APP_ENV'] = substr($argument, strlen('--env='));
        continue;
    }
    fwrite(STDERR, "Usage: php app/bin/database-status.php [--env=production|staging|development|test]\n");
    exit(2);
}

try {
    $config = AppConfig::fromEnvironment($environment);
    $runner = new MigrationRunner(ConnectionFactory::create($config), dirname(__DIR__) . '/database/migrations');
    $report = $runner->status();
    printf("Database migration status: %s; applied: %d; pending: %d\n", $report->isCurrent() ? 'current' : 'pending', count($report->appliedVersions), $report->pendingCount);
    foreach ($report->pendingVersions as $version) {
        printf("PENDING %s\n", $version);
    }
    exit($report->isCurrent() ? 0 : 1);
} catch (Throwable) {
    fwrite(STDERR, "Database status check failed.\n");
    exit(1);
}
