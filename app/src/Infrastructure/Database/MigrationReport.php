<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Database;

final readonly class MigrationReport
{
    /**
     * @param list<string> $appliedVersions
     * @param list<string> $pendingVersions
     */
    public function __construct(
        public int $appliedCount,
        public int $pendingCount,
        public array $appliedVersions,
        public array $pendingVersions,
    ) {
    }

    public function isCurrent(): bool
    {
        return $this->pendingCount === 0;
    }
}
