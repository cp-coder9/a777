<?php

declare(strict_types=1);

namespace Architex\Platform\Communication;

use RuntimeException;

final class CommunicationException extends RuntimeException
{
    public function __construct(public readonly string $reason){parent::__construct($reason);}
}
