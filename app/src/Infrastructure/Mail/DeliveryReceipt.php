<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Mail;

final readonly class DeliveryReceipt
{
    public function __construct(public string $messageId, public string $acceptedAt) {}
}
