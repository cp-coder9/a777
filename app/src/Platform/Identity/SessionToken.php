<?php
declare(strict_types=1);

namespace Architex\Platform\Identity;

final readonly class SessionToken
{
    public function __construct(public string $sessionId, public string $token, public string $csrfToken, public string $expiresAt) {}
}
