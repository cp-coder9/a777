<?php

declare(strict_types=1);

namespace Architex\Platform\Knowledge;

use RuntimeException;
use Throwable;

final class KnowledgeException extends RuntimeException
{
    public function __construct(public readonly string $reason, ?Throwable $previous = null)
    {
        parent::__construct($reason, 0, $previous);
    }
}
