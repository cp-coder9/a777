<?php

declare(strict_types=1);

namespace Architex\Platform\Planning;

use RuntimeException;

final class TownPlanningException extends RuntimeException
{
    public function __construct(public readonly string $reason){parent::__construct($reason);}
}
