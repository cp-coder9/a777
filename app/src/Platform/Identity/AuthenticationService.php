<?php
declare(strict_types=1);

namespace Architex\Platform\Identity;

use Architex\Platform\Organisation\MembershipRepository;
use PDO;

final readonly class AuthenticationService
{
    private const DUMMY_HASH = '$argon2id$v=19$m=19456,t=2,p=1$UDdpMUdWTWhmUlJmTjcxbw$LJpLqubuUkzpZfSVHfRjtfvxAPDiDO0o7p2oddgHeT8';
    private PasswordHasher $passwords;
    private MembershipRepository $memberships;

    public function __construct(private PDO $pdo)
    {
        $this->passwords = new PasswordHasher();
        $this->memberships = new MembershipRepository($pdo);
    }

    public function authenticate(string $email, string $password): AuthenticatedIdentity
    {
        $normalised = strtolower(trim($email));
        $statement = $this->pdo->prepare('SELECT HEX(id) id,email,password_hash,status,locked_until FROM users WHERE email=:email');
        $statement->execute(['email' => $normalised]);
        $user = $statement->fetch();
        $passwordMatches = is_array($user)
            ? $this->passwords->verify($password, (string) $user['password_hash'])
            : $this->passwords->verify($password, self::DUMMY_HASH);
        $valid = is_array($user) && $user['status'] === 'active'
            && ($user['locked_until'] === null || strtotime((string) $user['locked_until']) <= time())
            && $passwordMatches;
        if (!$valid) {
            if (is_array($user)) {
                $this->pdo->prepare("UPDATE users SET locked_until=IF(failed_login_count>=4 AND (locked_until IS NULL OR locked_until<=UTC_TIMESTAMP(6)),UTC_TIMESTAMP(6)+INTERVAL 15 MINUTE,locked_until),failed_login_count=LEAST(failed_login_count+1,65535) WHERE id=UNHEX(:id) AND status='active'")->execute(['id' => strtolower((string) $user['id'])]);
            }
            throw new IdentityException('invalid_credentials', 'Email or password is invalid.');
        }
        $userId = strtolower((string) $user['id']);
        $updates = ['id' => $userId];
        $sql = 'UPDATE users SET failed_login_count=0,locked_until=NULL';
        if ($this->passwords->needsRehash((string) $user['password_hash'])) {
            $sql .= ',password_hash=:password_hash';
            $updates['password_hash'] = $this->passwords->hash($password);
        }
        $this->pdo->prepare($sql . ' WHERE id=UNHEX(:id)')->execute($updates);
        return new AuthenticatedIdentity($userId, (string) $user['email'], $this->memberships->activeForUser($userId));
    }
}
