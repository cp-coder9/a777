<?php

declare(strict_types=1);

namespace Architex\Platform\Meeting;

use RuntimeException;

final class MeetingException extends RuntimeException
{
    public function __construct(public readonly string $reason){parent::__construct($reason);}
}
