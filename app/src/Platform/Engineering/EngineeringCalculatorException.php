<?php

declare(strict_types=1);

namespace Architex\Platform\Engineering;

use RuntimeException;

final class EngineeringCalculatorException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
