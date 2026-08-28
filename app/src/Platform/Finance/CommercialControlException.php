<?php

declare(strict_types=1);

namespace Architex\Platform\Finance;

use RuntimeException;

final class CommercialControlException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
