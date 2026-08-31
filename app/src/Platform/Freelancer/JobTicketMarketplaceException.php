<?php

declare(strict_types=1);

namespace Architex\Platform\Freelancer;

use RuntimeException;

final class JobTicketMarketplaceException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
