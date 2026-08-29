<?php

declare(strict_types=1);

namespace Architex\Platform\Practice;

use RuntimeException;

final class PracticeException extends RuntimeException
{
    public function __construct(public readonly string $reason){parent::__construct($reason);}
}
