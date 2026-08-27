<?php
declare(strict_types=1);

namespace Architex\Platform\Identity;

final readonly class AuthenticatedSession
{
    public function __construct(public string $sessionId, public AuthenticatedIdentity $identity) {}
}
