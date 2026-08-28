<?php

declare(strict_types=1);

namespace Architex\Platform\Documents;

use RuntimeException;

final class DocumentException extends RuntimeException
{
    public function __construct(public readonly string $reason){parent::__construct($reason);}
}
