<?php

declare(strict_types=1);

namespace Architex\Platform\Refuse;

final class RefuseException extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $status = 422)
    {
        parent::__construct($reason);
    }
}
