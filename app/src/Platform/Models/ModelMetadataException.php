<?php

declare(strict_types=1);

namespace Architex\Platform\Models;

use RuntimeException;

final class ModelMetadataException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
