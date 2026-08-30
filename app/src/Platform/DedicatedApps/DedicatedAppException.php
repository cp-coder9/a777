<?php

declare(strict_types=1);

namespace Architex\Platform\DedicatedApps;

use RuntimeException;

final class DedicatedAppException extends RuntimeException
{
    public function __construct(public readonly string$reason){parent::__construct($reason);}
}
