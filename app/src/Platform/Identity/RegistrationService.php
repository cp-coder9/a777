<?php
declare(strict_types=1);

namespace Architex\Platform\Identity;

use Architex\Platform\Legal\LegalAcceptanceService;
use Architex\Platform\Organisation\MembershipRepository;
use Architex\Platform\Organisation\OrganisationRepository;
use PDO;
use PDOException;
use Throwable;

final readonly class RegistrationService
{
    private PasswordHasher $passwords;
    private OrganisationRepository $organisations;
    private MembershipRepository $memberships;
    private LegalAcceptanceService $legal;

    public function __construct(private PDO $pdo)
    {
        $this->passwords = new PasswordHasher();
        $this->organisations = new OrganisationRepository($pdo);
        $this->memberships = new MembershipRepository($pdo);
        $this->legal = new LegalAcceptanceService($pdo);
    }

    public function register(RegisterOrganisation $command): PendingRegistration
    {
        $this->pdo->beginTransaction();
        try {
            $userId = Identifier::generate();
            $createUser = $this->pdo->prepare("INSERT INTO users (id,email,password_hash,status) VALUES (UNHEX(:id),:email,:password_hash,'pending_email_verification')");
            $createUser->execute(['id' => $userId, 'email' => $command->email, 'password_hash' => $this->passwords->hash($command->password)]);
            $organisationId = $this->organisations->createPending($command->organisationName, $command->organisationSlug);
            $this->memberships->addPendingOwner($organisationId, $userId);
            $this->legal->recordRequiredSignupAcceptances($userId, $organisationId, $command->acceptedNoticeVersions, $command->ipAddress, $command->deviceMetadata);

            $token = $this->newToken();
            $issue = $this->pdo->prepare("INSERT INTO email_verification_tokens (id,user_id,token_hash,expires_at) VALUES (UNHEX(:id),UNHEX(:user_id),:token_hash,UTC_TIMESTAMP(6) + INTERVAL 30 MINUTE)");
            $issue->execute(['id' => Identifier::generate(), 'user_id' => $userId, 'token_hash' => hash('sha256', $token)]);
            $this->pdo->commit();
            return new PendingRegistration($userId, $organisationId, $token);
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof PDOException && ($exception->errorInfo[1] ?? null) === 1062) {
                throw new IdentityException('registration_conflict', 'The email address or organisation slug is already registered.');
            }
            throw $exception;
        }
    }

    public function verifyEmail(string $plaintextToken): AuthenticatedIdentity
    {
        if (strlen($plaintextToken) < 32 || strlen($plaintextToken) > 200) {
            throw new IdentityException('verification_invalid', 'The email verification token is invalid.');
        }
        $this->pdo->beginTransaction();
        try {
            $find = $this->pdo->prepare("SELECT HEX(t.id) token_id,HEX(t.user_id) user_id,u.email FROM email_verification_tokens t JOIN users u ON u.id=t.user_id WHERE t.token_hash=:token_hash AND t.consumed_at IS NULL AND t.expires_at>UTC_TIMESTAMP(6) AND u.status='pending_email_verification' FOR UPDATE");
            $find->execute(['token_hash' => hash('sha256', $plaintextToken)]);
            $row = $find->fetch();
            if (!is_array($row)) {
                throw new IdentityException('verification_invalid', 'The email verification token is invalid or expired.');
            }
            $userId = strtolower((string) $row['user_id']);
            $this->pdo->prepare("UPDATE email_verification_tokens SET consumed_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id) AND consumed_at IS NULL")->execute(['id' => strtolower((string) $row['token_id'])]);
            $this->pdo->prepare("UPDATE users SET status='active',email_verified_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:user_id)")->execute(['user_id' => $userId]);
            $this->pdo->prepare("UPDATE organisations o JOIN organisation_memberships m ON m.organisation_id=o.id SET o.status='active' WHERE m.user_id=UNHEX(:user_id) AND m.role='owner' AND m.status='pending'")->execute(['user_id' => $userId]);
            $this->pdo->prepare("UPDATE organisation_memberships SET status='active' WHERE user_id=UNHEX(:user_id) AND role='owner' AND status='pending'")->execute(['user_id' => $userId]);
            $memberships = $this->memberships->activeForUser($userId);
            if ($memberships === []) {
                throw new IdentityException('verification_invalid', 'The registration is incomplete and cannot be activated.');
            }
            $this->pdo->commit();
            return new AuthenticatedIdentity($userId, (string) $row['email'], $memberships);
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
