<?php

declare(strict_types=1);

namespace Architex\Platform\CPD;

use RuntimeException;

final class CPDAssessmentException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
