<?php

declare(strict_types=1);

namespace Architex\Platform\Procurement;

use RuntimeException;

final class ProcurementException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
