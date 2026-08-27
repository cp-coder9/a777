<?php

declare(strict_types=1);

namespace Architex\Platform\Jobs;

use RuntimeException;

final class PermanentJobFailure extends RuntimeException
{
    public function __construct(public readonly string $errorCode)
    {
        parent::__construct('The scheduled job cannot be processed.');
    }
}
