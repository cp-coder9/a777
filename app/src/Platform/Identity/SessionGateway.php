<?php
declare(strict_types=1);

namespace Architex\Platform\Identity;

interface SessionGateway
{
    public function rotate(string $userId, ?string $previousSessionId = null, ?string $ipAddress = null, ?string $userAgent = null): SessionToken;
    public function resolve(string $plaintextToken): AuthenticatedSession;
    public function verifyCsrf(string $sessionId, string $plaintextCsrf): bool;
    public function issueCsrf(string $sessionId): string;
    public function revoke(string $sessionId): void;
}
