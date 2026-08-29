<?php

declare(strict_types=1);

namespace Architex\Platform\Analytics;

use RuntimeException;

final class AnalyticsReportingException extends RuntimeException
{
    public function __construct(public readonly string $reason){parent::__construct($reason);}
}
