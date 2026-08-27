<?php

declare(strict_types=1);

namespace Architex\Platform\Identity;

use PDO;
use Throwable;

final readonly class PasswordResetService implements PasswordResetGateway
{
    private PasswordHasher $passwords;

    public function __construct(private PDO $pdo, private PasswordResetNotifier $notifier)
    {
        $this->passwords = new PasswordHasher();
    }

    public function request(string $email): void
    {
        if (!$this->notifier->isAvailable()) {
            throw new IdentityException('password_reset_unavailable', 'Password reset delivery is temporarily unavailable.');
        }
        $normalised = strtolower(trim($email));
        $find = $this->pdo->prepare("SELECT HEX(id) id,email FROM users WHERE email=:email AND status='active'");
        $find->execute(['email' => $normalised]);
        $user = $find->fetch();
        if (!is_array($user)) {
            return;
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $tokenId = Identifier::generate();
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE password_reset_tokens SET consumed_at=UTC_TIMESTAMP(6) WHERE user_id=UNHEX(:user_id) AND consumed_at IS NULL')->execute(['user_id' => strtolower((string) $user['id'])]);
            $insert = $this->pdo->prepare('INSERT INTO password_reset_tokens (id,user_id,token_hash,expires_at) VALUES (UNHEX(:id),UNHEX(:user_id),:hash,UTC_TIMESTAMP(6)+INTERVAL 30 MINUTE)');
            $insert->execute(['id' => $tokenId, 'user_id' => strtolower((string) $user['id']), 'hash' => hash('sha256', $token)]);
            $expires = (string) $this->pdo->query("SELECT DATE_FORMAT(UTC_TIMESTAMP(6)+INTERVAL 30 MINUTE,'%Y-%m-%dT%H:%i:%s.%fZ')")->fetchColumn();
            $this->notifier->send((string) $user['email'], $token, $expires);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function complete(string $token, string $newPassword): void
    {
        if (strlen($token) < 40 || strlen($token) > 200) {
            throw new IdentityException('password_reset_invalid', 'The password reset token is invalid or expired.');
        }
        $this->pdo->beginTransaction();
        try {
            $find = $this->pdo->prepare('SELECT HEX(id) token_id,HEX(user_id) user_id FROM password_reset_tokens WHERE token_hash=:hash AND consumed_at IS NULL AND expires_at>UTC_TIMESTAMP(6) FOR UPDATE');
            $find->execute(['hash' => hash('sha256', $token)]);
            $row = $find->fetch();
            if (!is_array($row)) {
                throw new IdentityException('password_reset_invalid', 'The password reset token is invalid or expired.');
            }
            $userId = strtolower((string) $row['user_id']);
            $this->pdo->prepare('UPDATE users SET password_hash=:hash,failed_login_count=0,locked_until=NULL WHERE id=UNHEX(:id)')->execute(['hash' => $this->passwords->hash($newPassword), 'id' => $userId]);
            $this->pdo->prepare('UPDATE password_reset_tokens SET consumed_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id) AND consumed_at IS NULL')->execute(['id' => strtolower((string) $row['token_id'])]);
            $this->pdo->prepare('UPDATE user_sessions SET revoked_at=UTC_TIMESTAMP(6) WHERE user_id=UNHEX(:user_id) AND revoked_at IS NULL')->execute(['user_id' => $userId]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
