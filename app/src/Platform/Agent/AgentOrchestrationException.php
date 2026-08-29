<?php

declare(strict_types=1);

namespace Architex\Platform\Agent;

use RuntimeException;

final class AgentOrchestrationException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
