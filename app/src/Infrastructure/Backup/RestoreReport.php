<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Backup;

final readonly class RestoreReport
{
    public function __construct(public string $backupId, public string $database, public int $rowCount, public int $fileCount) {}
}
