<?php

declare(strict_types=1);
namespace Architex\Platform\SiteExecution;

final class WorkPermitException extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $status = 422)
    {
        parent::__construct($reason);
    }
}