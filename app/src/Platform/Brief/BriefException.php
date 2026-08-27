<?php

declare(strict_types=1);

namespace Architex\Platform\Brief;

use RuntimeException;

final class BriefException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
