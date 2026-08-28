<?php

declare(strict_types=1);

namespace Architex\Platform\Municipal;

use RuntimeException;

final class MunicipalReadinessException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
