<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Mail;

use Architex\Platform\Identity\PasswordResetNotifier;
use Architex\Platform\Identity\RegistrationNotifier;
use Architex\Platform\Jobs\JobQueue;
use RuntimeException;

final readonly class QueuedIdentityNotifier implements RegistrationNotifier, PasswordResetNotifier
{
    public function __construct(private JobQueue $queue, private string $jobType, private bool $available = true)
    {
        if (!in_array($jobType, ['send_verification_email', 'send_password_reset_email'], true)) {
            throw new RuntimeException('The identity notification type is invalid.');
        }
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function send(string $email, string $token, string $expiresAt): void
    {
        if (!$this->available) {
            throw new RuntimeException('Identity email delivery is unavailable.');
        }
        $this->queue->enqueue($this->jobType, ['to' => $email, 'token' => $token, 'expiresAt' => $expiresAt], $this->jobType . ':' . hash('sha256', $token));
    }
}
