<?php

declare(strict_types=1);

namespace Architex\Platform\Forms;

use RuntimeException;

final class FormException extends RuntimeException
{
    public function __construct(public readonly string $reason){parent::__construct($reason);}
}
