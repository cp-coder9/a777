<?php

declare(strict_types=1);

use Architex\Infrastructure\Deployment\ReleaseBuilder;

require dirname(__DIR__) . '/bootstrap.php';

$version = null;
$environment = 'production';
$allowDirtyStaging = false;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--version=')) $version = substr($argument, 10);
    elseif (str_starts_with($argument, '--environment=')) $environment = substr($argument, 14);
    elseif ($argument === '--allow-dirty-staging') $allowDirtyStaging = true;
    else { fwrite(STDERR, "Usage: php app/bin/build-release.php --version=<version> [--environment=production|staging] [--allow-dirty-staging]\n"); exit(2); }
}
if (!is_string($version) || $version === '') { fwrite(STDERR, "A release version is required.\n"); exit(2); }
try {
    $path = (new ReleaseBuilder(dirname(__DIR__, 2), dirname(__DIR__, 2) . '/dist'))->build($version, $environment, $allowDirtyStaging);
    fwrite(STDOUT, "Release built: {$path}\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "Release build failed.\n");
    exit(1);
}
