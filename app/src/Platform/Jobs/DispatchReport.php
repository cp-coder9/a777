<?php

declare(strict_types=1);

namespace Architex\Platform\Jobs;

final readonly class DispatchReport
{
    public function __construct(
        public int $claimed,
        public int $succeeded,
        public int $retried,
        public int $dead,
    ) {}
}
