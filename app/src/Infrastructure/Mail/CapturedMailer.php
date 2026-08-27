<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Mail;

final class CapturedMailer implements Mailer
{
    /** @var list<Message> */
    private array $messages = [];

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(Message $message): DeliveryReceipt
    {
        $this->messages[] = $message;
        return new DeliveryReceipt(bin2hex(random_bytes(16)) . '@captured.local', gmdate('c'));
    }

    /** @return list<Message> */
    public function messages(): array
    {
        return $this->messages;
    }
}
