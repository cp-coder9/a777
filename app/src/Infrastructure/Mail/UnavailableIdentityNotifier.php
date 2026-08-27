<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Mail;

use Architex\Platform\Identity\PasswordResetNotifier;
use Architex\Platform\Identity\RegistrationNotifier;
use RuntimeException;

final class UnavailableIdentityNotifier implements RegistrationNotifier, PasswordResetNotifier
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function send(string $email, string $token, string $expiresAt): void
    {
        throw new RuntimeException('Identity email delivery is unavailable.');
    }
}
