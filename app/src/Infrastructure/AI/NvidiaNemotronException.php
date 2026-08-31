<?php

declare(strict_types=1);

namespace Architex\Infrastructure\AI;

use RuntimeException;

final class NvidiaNemotronException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('The Wingman model request could not be completed: ' . $reason);
    }
}
