<?php
declare(strict_types=1);

namespace Architex\Platform\Identity;

use RuntimeException;

final class IdentityException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
