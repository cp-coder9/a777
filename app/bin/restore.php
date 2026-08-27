<?php

declare(strict_types=1);

use Architex\Infrastructure\Backup\RestoreService;

require dirname(__DIR__) . '/bootstrap.php';

$environment = getenv(); $environment = is_array($environment) ? $environment : [];
$input = $confirmed = $fileRoot = null;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--env=')) {
        $requested = substr($argument, 6);
        $environment['APP_ENV'] = $requested === 'restore_test' ? 'test' : $requested;
    } elseif (str_starts_with($argument, '--input=')) $input = substr($argument, 8);
    elseif (str_starts_with($argument, '--confirm-database=')) $confirmed = substr($argument, 19);
    elseif (str_starts_with($argument, '--file-root=')) $fileRoot = substr($argument, 12);
    else { fwrite(STDERR, "Usage: php app/bin/restore.php --env=restore_test --input=<archive> --confirm-database=<task-test-db> [--file-root=<empty-directory>]\n"); exit(2); }
}
if (!is_string($input) || !is_file($input) || !is_string($confirmed) || $confirmed === '') { fwrite(STDERR, "Restore input and exact database confirmation are required.\n"); exit(2); }
try {
    $password = $environment['BACKUP_ARCHIVE_PASSWORD'] ?? null; $key = $environment['APP_KEY'] ?? null;
    if (!is_string($password) || !is_string($key)) throw new RuntimeException('Restore secrets are unavailable.');
    $fileRoot ??= dirname(__DIR__) . '/storage/restore/' . $confirmed;
    $report = (new RestoreService(dirname(__DIR__) . '/database/migrations', $password, $key))->restore($input, $environment, $confirmed, $fileRoot);
    fwrite(STDOUT, json_encode(['backupId'=>$report->backupId,'database'=>$report->database,'rows'=>$report->rowCount,'files'=>$report->fileCount], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
} catch (Throwable) {
    fwrite(STDERR, "Restore failed.\n");
    exit(1);
}
