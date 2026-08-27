<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Backup;

final readonly class BackupReport
{
    public function __construct(public string $path, public string $backupId, public int $rowCount, public int $fileCount) {}
}
