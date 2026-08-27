<?php

declare(strict_types=1);

namespace Architex\Requirements;

use InvalidArgumentException;

final readonly class PackLedgerLimits
{
    public function __construct(
        public int $perSourceBytes = 1_048_576,
        public int $maximumZipEntries = 2_000,
        public int $maximumZipEntryBytes = 1_048_576,
        public int $maximumTotalInputBytes = 16_777_216,
        public int $maximumCompressionRatio = 100,
        public int $maximumRequirements = 100_000,
        public int $maximumSources = 5_000,
    ) {
        foreach (get_object_vars($this) as $name => $value) {
            if ($value < 1) {
                throw new InvalidArgumentException(sprintf('%s must be greater than zero.', $name));
            }
        }
    }
}
