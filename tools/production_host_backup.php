<?php
declare(strict_types=1);

// Hosting adapter library: require after authenticated exact-target bootstrap and
// fenced write/drain controls. Not an HTTP endpoint and never accepts attestations.
// Deliberately no MigrationRunner shortcut: DDL stays with the lease-owning adapter.
use Architex\Infrastructure\Backup\BackupService;

function a777CreateRetainedBackup(PDO $pdo, string $fileRoot, string $password, string $base64Key, string $archive): array
{
    $report = (new BackupService($pdo, $fileRoot, $password, $base64Key))->create($archive);
    $sha = hash_file('sha256', $report->path);
    $bytes = filesize($report->path);
    if (!is_string($sha) || !is_int($bytes) || $bytes < 1) throw new RuntimeException('Retained backup readback failed.');
    return ['retainedPath'=>$report->path, 'archiveSha256'=>$sha,
        'backupId'=>$report->backupId, 'bytes'=>$bytes,
        'rowCount'=>$report->rowCount, 'fileCount'=>$report->fileCount];
}
