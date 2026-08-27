<?php
declare(strict_types=1);

namespace Architex\Platform\Identity;

interface AuthenticationGateway
{
    public function authenticate(string $email, string $password): AuthenticatedIdentity;
}
