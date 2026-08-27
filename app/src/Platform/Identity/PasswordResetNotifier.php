<?php
declare(strict_types=1);

namespace Architex\Platform\Identity;

interface PasswordResetNotifier
{
    public function isAvailable(): bool;
    public function send(string $email, string $token, string $expiresAt): void;
}
