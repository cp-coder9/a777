<?php

declare(strict_types=1);

namespace Architex\Platform\SiteExecution;

final class SiteExecutionException extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
