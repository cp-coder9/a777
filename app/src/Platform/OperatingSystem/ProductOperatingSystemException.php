<?php

declare(strict_types=1);

namespace Architex\Platform\OperatingSystem;

use RuntimeException;

final class ProductOperatingSystemException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
