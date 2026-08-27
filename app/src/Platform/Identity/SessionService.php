<?php
declare(strict_types=1);

namespace Architex\Platform\Identity;

use Architex\Platform\Organisation\MembershipRepository;
use PDO;

final readonly class SessionService
{
    private MembershipRepository $memberships;

    public function __construct(private PDO $pdo)
    {
        $this->memberships = new MembershipRepository($pdo);
    }

    public function rotate(string $userId, ?string $previousSessionId = null, ?string $ipAddress = null, ?string $userAgent = null): SessionToken
    {
        Identifier::binary($userId);
        if ($ipAddress !== null && filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            throw new IdentityException('session_context_invalid', 'Session context is invalid.');
        }
        $this->pdo->beginTransaction();
        try {
            $activeUser = $this->pdo->prepare("SELECT 1 FROM users WHERE id=UNHEX(:user_id) AND status='active' FOR UPDATE");
            $activeUser->execute(['user_id' => $userId]);
            if ($activeUser->fetchColumn() === false) {
                throw new IdentityException('session_invalid', 'Only active users can receive a session.');
            }
            if ($previousSessionId !== null) {
                Identifier::binary($previousSessionId);
                $revoke = $this->pdo->prepare('UPDATE user_sessions SET revoked_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:session_id) AND user_id=UNHEX(:user_id) AND revoked_at IS NULL');
                $revoke->execute(['session_id' => $previousSessionId, 'user_id' => $userId]);
                if ($revoke->rowCount() !== 1) {
                    throw new IdentityException('session_invalid', 'The session cannot be rotated.');
                }
            }
            $sessionId = Identifier::generate();
            $token = $this->newToken();
            $csrf = $this->newToken();
            $insert = $this->pdo->prepare('INSERT INTO user_sessions (id,user_id,token_hash,csrf_token_hash,expires_at,rotated_from_id,ip_address,user_agent_hash) VALUES (UNHEX(:id),UNHEX(:user_id),:token_hash,:csrf_hash,UTC_TIMESTAMP(6)+INTERVAL 12 HOUR,UNHEX(:rotated_from),:ip_address,:user_agent_hash)');
            $insert->execute([
                'id' => $sessionId, 'user_id' => $userId, 'token_hash' => hash('sha256', $token),
                'csrf_hash' => hash('sha256', $csrf), 'rotated_from' => $previousSessionId,
                'ip_address' => $ipAddress === null ? null : inet_pton($ipAddress),
                'user_agent_hash' => $userAgent === null ? null : hash('sha256', $userAgent),
            ]);
            $expires = (string) $this->pdo->query("SELECT DATE_FORMAT(UTC_TIMESTAMP(6)+INTERVAL 12 HOUR,'%Y-%m-%dT%H:%i:%s.%fZ')")->fetchColumn();
            $this->pdo->commit();
            return new SessionToken($sessionId, $token, $csrf, $expires);
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function authenticate(string $plaintextToken): AuthenticatedIdentity
    {
        $statement = $this->pdo->prepare("SELECT HEX(s.id) session_id,HEX(u.id) user_id,u.email FROM user_sessions s JOIN users u ON u.id=s.user_id WHERE s.token_hash=:token_hash AND s.revoked_at IS NULL AND s.expires_at>UTC_TIMESTAMP(6) AND u.status='active'");
        $statement->execute(['token_hash' => hash('sha256', $plaintextToken)]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            throw new IdentityException('session_invalid', 'The session is invalid or expired.');
        }
        $this->pdo->prepare('UPDATE user_sessions SET last_seen_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id)')->execute(['id' => strtolower((string) $row['session_id'])]);
        $userId = strtolower((string) $row['user_id']);
        return new AuthenticatedIdentity($userId, (string) $row['email'], $this->memberships->activeForUser($userId));
    }

    public function verifyCsrf(string $sessionId, string $plaintextCsrf): bool
    {
        Identifier::binary($sessionId);
        $statement = $this->pdo->prepare('SELECT csrf_token_hash FROM user_sessions WHERE id=UNHEX(:id) AND revoked_at IS NULL AND expires_at>UTC_TIMESTAMP(6)');
        $statement->execute(['id' => $sessionId]);
        $hash = $statement->fetchColumn();
        return is_string($hash) && hash_equals($hash, hash('sha256', $plaintextCsrf));
    }

    public function revoke(string $sessionId): void
    {
        Identifier::binary($sessionId);
        $this->pdo->prepare('UPDATE user_sessions SET revoked_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id) AND revoked_at IS NULL')->execute(['id' => $sessionId]);
    }

    private function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
