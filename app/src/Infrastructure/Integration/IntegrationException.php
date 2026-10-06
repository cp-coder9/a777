<?php
declare(strict_types=1);

namespace Architex\Infrastructure\Integration;

final class IntegrationException extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $httpStatus = 503)
    {
        parent::__construct($reason);
    }
}
