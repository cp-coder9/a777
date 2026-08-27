<?php

declare(strict_types=1);

namespace Architex\Platform\Trust;

use RuntimeException;

final class TrustException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
