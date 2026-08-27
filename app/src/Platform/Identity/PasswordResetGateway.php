<?php
declare(strict_types=1);

namespace Architex\Platform\Identity;

interface PasswordResetGateway
{
    public function request(string $email): void;
    public function complete(string $token, string $newPassword): void;
}
