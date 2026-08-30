<?php

declare(strict_types=1);

namespace Architex\Platform\PlatformCompleteness;

use RuntimeException;

final class PlatformCompletenessException extends RuntimeException
{
    public function __construct(public readonly string$reason){parent::__construct($reason);}
}
