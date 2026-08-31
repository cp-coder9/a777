<?php

declare(strict_types=1);

namespace Architex\Platform\Wingman;

use RuntimeException;

final class WingmanException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('The guided Wingman request could not be completed: ' . $reason);
    }
}
