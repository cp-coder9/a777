<?php

declare(strict_types=1);

namespace Architex\Platform\SupplierProcurement;

use RuntimeException;

final class SupplierProcurementException extends RuntimeException
{
    public function __construct(public readonly string $reason){parent::__construct($reason);}
}
