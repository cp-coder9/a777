<?php

declare(strict_types=1);

namespace Architex\Platform\Toolbox;

use RuntimeException;

final class ToolException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
