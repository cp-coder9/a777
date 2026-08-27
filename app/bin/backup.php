<?php

declare(strict_types=1);

use Architex\Infrastructure\Backup\BackupService;
use Architex\Infrastructure\Config\AppConfig;
use Architex\Infrastructure\Database\ConnectionFactory;

require dirname(__DIR__) . '/bootstrap.php';

$environment = getenv(); $environment = is_array($environment) ? $environment : [];
$output = null;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--env=')) $environment['APP_ENV'] = substr($argument, 6);
    elseif (str_starts_with($argument, '--output=')) $output = substr($argument, 9);
    else { fwrite(STDERR, "Usage: php app/bin/backup.php [--env=<environment>] --output=<path>\n"); exit(2); }
}
if (!is_string($output) || $output === '') { fwrite(STDERR, "A backup output path is required.\n"); exit(2); }
try {
    $password = $environment['BACKUP_ARCHIVE_PASSWORD'] ?? null; $key = $environment['APP_KEY'] ?? null;
    if (!is_string($password) || !is_string($key)) throw new RuntimeException('Backup secrets are unavailable.');
    $config = AppConfig::fromEnvironment($environment);
    $fileRoot = is_string($environment['FILE_STORAGE_PATH'] ?? null) ? $environment['FILE_STORAGE_PATH'] : dirname(__DIR__) . '/storage/files';
    $report = (new BackupService(ConnectionFactory::create($config), $fileRoot, $password, $key))->create($output);
    fwrite(STDOUT, json_encode(['path'=>$report->path,'backupId'=>$report->backupId,'rows'=>$report->rowCount,'files'=>$report->fileCount], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
} catch (Throwable) {
    fwrite(STDERR, "Backup failed.\n");
    exit(1);
}
