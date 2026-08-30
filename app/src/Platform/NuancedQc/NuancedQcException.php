<?php

declare(strict_types=1);

namespace Architex\Platform\NuancedQc;

use RuntimeException;

final class NuancedQcException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
