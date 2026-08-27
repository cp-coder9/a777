<?php

declare(strict_types=1);

namespace Architex\Platform\Proposal;

use RuntimeException;

final class ProposalException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
