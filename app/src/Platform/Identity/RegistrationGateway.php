<?php
declare(strict_types=1);

namespace Architex\Platform\Identity;

interface RegistrationGateway
{
    public function register(RegisterOrganisation $command): PendingRegistration;
    public function verifyEmail(string $plaintextToken): AuthenticatedIdentity;
}
