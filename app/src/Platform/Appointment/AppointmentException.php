<?php

declare(strict_types=1);

namespace Architex\Platform\Appointment;

use RuntimeException;

final class AppointmentException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
