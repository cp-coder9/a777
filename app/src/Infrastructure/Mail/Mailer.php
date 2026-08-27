<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Mail;

interface Mailer
{
    public function isConfigured(): bool;

    public function send(Message $message): DeliveryReceipt;
}
