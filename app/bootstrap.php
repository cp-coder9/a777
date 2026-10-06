<?php

declare(strict_types=1);

$applicationRoot = __DIR__;

// Configuration loading does not open PDO. Fence state belongs in private writable
// storage, never in the immutable release tree. Hold SH for the worker lifetime.
require_once $applicationRoot . '/src/Infrastructure/Deployment/MaintenanceFence.php';
require_once $applicationRoot . '/src/Infrastructure/Config/EnvironmentLoader.php';
try {
    \Architex\Infrastructure\Config\EnvironmentLoader::apply($applicationRoot);
    $storageRoot = getenv('FILE_STORAGE_PATH');
    if ($storageRoot === false || $storageRoot === '') {
        if (getenv('APP_ENV') === 'production') throw new RuntimeException('Private storage required.');
        $storageRoot = $applicationRoot . '/storage';
    }
    $fenceDirectory = rtrim($storageRoot, '/\\') . '/maintenance';
    if (getenv('APP_ENV') === 'production') {
        $resolvedStorage = realpath($storageRoot);
        $resolvedRuntime = realpath($applicationRoot);
        if ($resolvedStorage === false || $resolvedRuntime === false ||
            $resolvedStorage === $resolvedRuntime || str_starts_with($resolvedStorage . '/', $resolvedRuntime . '/')) {
            throw new RuntimeException('Production maintenance requires external private writable storage.');
        }
    }
    if (is_link($storageRoot) || is_link($fenceDirectory)) throw new RuntimeException('Unsafe maintenance storage.');
    if (!is_dir($fenceDirectory) && !mkdir($fenceDirectory, 0700, true) && !is_dir($fenceDirectory)) {
        throw new RuntimeException('Maintenance control unavailable.');
    }
    (new \Architex\Infrastructure\Deployment\MaintenanceFence($fenceDirectory))->enter();
} catch (Throwable) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Application maintenance in progress.\n");
        exit(75);
    }
    http_response_code(503);
    header('Cache-Control: no-store');
    header('Retry-After: 60');
    exit('Application maintenance in progress.');
}
$projectRoot = is_file($applicationRoot . '/vendor/autoload.php') ? $applicationRoot : dirname($applicationRoot);
$autoload = $projectRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Runtime dependencies are unavailable.');
}
require $autoload;
\Architex\Infrastructure\Config\EnvironmentLoader::apply($applicationRoot);
