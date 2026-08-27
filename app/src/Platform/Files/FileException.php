<?php

declare(strict_types=1);

namespace Architex\Platform\Files;

use RuntimeException;

final class FileException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
