<?php
declare(strict_types=1);

namespace Architex\Tests\Integration\Identity;

use Architex\Platform\Identity\AuthenticationService;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Identity\IdentityException;
use Architex\Platform\Identity\RegisterOrganisation;
use Architex\Platform\Identity\RegistrationService;
use Architex\Platform\Identity\RegistrationNotifier;
use Architex\Platform\Identity\SessionService;
use Architex\Platform\Legal\LegalAcceptanceService;
use Architex\Platform\Organisation\OrganisationRepository;
use Architex\Tests\Support\MariaDbTestCase;

final class RegistrationServiceTest extends MariaDbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->publishSignupNotices();
    }

    public function testRegistrationCreatesPendingTenantMembershipLegalEvidenceAndHashedTokenAtomically(): void
    {
        $result = (new RegistrationService($this->pdo))->register($this->registration());

        self::assertSame('pending_email_verification', $result->status);
        self::assertSame(1, $this->countRows('users'));
        self::assertSame(1, $this->countRows('organisations'));
        self::assertSame(1, $this->countRows('organisation_memberships'));
        self::assertSame(4, $this->countRows('legal_acceptances'));
        self::assertSame(1, $this->countRows('email_verification_tokens'));
        $user = $this->pdo->query('SELECT email,password_hash,status FROM users')->fetch();
        self::assertSame('owner@example.com', $user['email']);
        self::assertNotSame('correct horse battery staple', $user['password_hash']);
        self::assertSame('pending_email_verification', $user['status']);
        self::assertSame(hash('sha256', $result->verificationToken), $this->pdo->query('SELECT token_hash FROM email_verification_tokens')->fetchColumn());
        self::assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM legal_acceptances WHERE LENGTH(ip_address)=4 AND JSON_VALID(device_metadata)=1')->fetchColumn());
    }

    public function testDuplicateEmailAndInvalidNoticeRollBackTheWholeRegistration(): void
    {
        $service = new RegistrationService($this->pdo);
        $service->register($this->registration());
        try {
            $service->register($this->registration(['organisationSlug' => 'second-practice']));
            self::fail('Expected duplicate email rejection.');
        } catch (IdentityException $exception) {
            self::assertSame('registration_conflict', $exception->reason);
        }
        try {
            $service->register($this->registration(['email' => 'other@example.com', 'organisationSlug' => 'other', 'acceptedNoticeVersions' => ['terms_of_service' => '1.0.0']]));
            self::fail('Expected incomplete legal acceptance rejection.');
        } catch (IdentityException $exception) {
            self::assertSame('legal_acceptance_required', $exception->reason);
        }
        self::assertSame(1, $this->countRows('users'));
        self::assertSame(1, $this->countRows('organisations'));
    }

    public function testVerificationActivatesExactlyOnceAndExpiredTokensFailClosed(): void
    {
        $service = new RegistrationService($this->pdo);
        $pending = $service->register($this->registration());
        $identity = $service->verifyEmail($pending->verificationToken);
        self::assertSame($pending->userId, $identity->userId);
        self::assertSame('owner', $identity->memberships[0]['role']);
        self::assertSame('active', $this->pdo->query('SELECT status FROM users')->fetchColumn());
        try {
            $service->verifyEmail($pending->verificationToken);
            self::fail('Expected token replay rejection.');
        } catch (IdentityException $exception) {
            self::assertSame('verification_invalid', $exception->reason);
        }

        $second = $service->register($this->registration(['email' => 'expired@example.com', 'organisationSlug' => 'expired-practice']));
        $this->pdo->exec("UPDATE email_verification_tokens SET expires_at=UTC_TIMESTAMP(6)-INTERVAL 1 SECOND WHERE token_hash='" . hash('sha256', $second->verificationToken) . "'");
        $this->expectException(IdentityException::class);
        $this->expectExceptionMessage('invalid or expired');
        $service->verifyEmail($second->verificationToken);
    }

    public function testAuthenticationAndSessionRotationRejectPendingAndRevokedCredentials(): void
    {
        $registration = new RegistrationService($this->pdo);
        $pending = $registration->register($this->registration());
        $auth = new AuthenticationService($this->pdo);
        try {
            $auth->authenticate('owner@example.com', 'correct horse battery staple');
            self::fail('Pending account must not authenticate.');
        } catch (IdentityException $exception) {
            self::assertSame('invalid_credentials', $exception->reason);
        }
        try {
            (new SessionService($this->pdo))->rotate($pending->userId);
            self::fail('Pending account must not receive a session.');
        } catch (IdentityException $exception) {
            self::assertSame('session_invalid', $exception->reason);
        }
        $registration->verifyEmail($pending->verificationToken);
        $obsoleteHash = password_hash('correct horse battery staple', PASSWORD_BCRYPT, ['cost' => 4]);
        self::assertIsString($obsoleteHash);
        $replaceHash = $this->pdo->prepare('UPDATE users SET password_hash=:hash WHERE id=UNHEX(:id)');
        $replaceHash->execute(['hash' => $obsoleteHash, 'id' => $pending->userId]);
        $identity = $auth->authenticate('OWNER@example.com', 'correct horse battery staple');
        self::assertStringStartsWith('$argon2id$', (string) $this->pdo->query('SELECT password_hash FROM users')->fetchColumn());
        $sessions = new SessionService($this->pdo);
        $first = $sessions->rotate($identity->userId, null, '127.0.0.1', 'test-agent');
        self::assertNotSame($first->token, $this->pdo->query('SELECT token_hash FROM user_sessions')->fetchColumn());
        self::assertSame($identity->userId, $sessions->authenticate($first->token)->userId);
        self::assertTrue($sessions->verifyCsrf($first->sessionId, $first->csrfToken));
        self::assertFalse($sessions->verifyCsrf($first->sessionId, 'wrong'));
        $second = $sessions->rotate($identity->userId, $first->sessionId);
        try {
            $sessions->authenticate($first->token);
            self::fail('Rotated token must be revoked.');
        } catch (IdentityException $exception) {
            self::assertSame('session_invalid', $exception->reason);
        }
        self::assertSame($identity->userId, $sessions->authenticate($second->token)->userId);

        $locked = $registration->register($this->registration(['email' => 'locked@example.com', 'organisationSlug' => 'locked-practice']));
        $registration->verifyEmail($locked->verificationToken);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $auth->authenticate('locked@example.com', 'definitely the wrong password');
            } catch (IdentityException) {
            }
        }
        self::assertNotNull($this->pdo->query("SELECT locked_until FROM users WHERE email='locked@example.com'")->fetchColumn());
        $this->expectException(IdentityException::class);
        $auth->authenticate('locked@example.com', 'correct horse battery staple');
    }

    public function testOrganisationLookupCannotCrossTenantBoundary(): void
    {
        $service = new RegistrationService($this->pdo);
        $a = $service->register($this->registration());
        $b = $service->register($this->registration(['email' => 'b@example.com', 'organisationName' => 'B Practice', 'organisationSlug' => 'b-practice']));
        $service->verifyEmail($a->verificationToken);
        $service->verifyEmail($b->verificationToken);
        $repository = new OrganisationRepository($this->pdo);
        self::assertSame($a->organisationId, $repository->findForUser($a->organisationId, $a->userId)['id']);
        self::assertNull($repository->findForUser($b->organisationId, $a->userId));
    }

    public function testRegistrationDeliveryFailsClosedAndParticipatesInTheTransaction(): void
    {
        $unavailable = new class implements RegistrationNotifier {
            public function isAvailable(): bool { return false; }
            public function send(string $email, string $token, string $expiresAt): void { throw new \LogicException(); }
        };
        try {
            (new RegistrationService($this->pdo, $unavailable))->register($this->registration());
            self::fail('Expected unavailable registration delivery to fail closed.');
        } catch (IdentityException $exception) {
            self::assertSame('registration_email_unavailable', $exception->reason);
        }
        self::assertSame(0, $this->countRows('users'));

        $captured = new class implements RegistrationNotifier {
            public ?string $token = null;
            public function isAvailable(): bool { return true; }
            public function send(string $email, string $token, string $expiresAt): void { $this->token = $token; }
        };
        $pending = (new RegistrationService($this->pdo, $captured))->register($this->registration());
        self::assertSame($pending->verificationToken, $captured->token);
    }

    /** @param array<string,mixed> $override */
    private function registration(array $override = []): RegisterOrganisation
    {
        return RegisterOrganisation::fromArray(array_replace([
            'email' => 'owner@example.com', 'password' => 'correct horse battery staple',
            'organisationName' => 'Example Practice', 'organisationSlug' => 'example-practice',
            'acceptedNoticeVersions' => array_fill_keys(LegalAcceptanceService::SIGNUP_NOTICE_TYPES, '1.0.0'),
            'ipAddress' => '127.0.0.1', 'deviceMetadata' => ['userAgent' => 'test'],
        ], $override));
    }

    private function publishSignupNotices(): void
    {
        $insert = $this->pdo->prepare("INSERT INTO legal_notices (id,notice_type,version,title,content_sha256,published_at,effective_at) VALUES (UNHEX(:id),:type,'1.0.0',:title,:hash,UTC_TIMESTAMP(6)-INTERVAL 1 DAY,UTC_TIMESTAMP(6)-INTERVAL 1 DAY)");
        foreach (LegalAcceptanceService::SIGNUP_NOTICE_TYPES as $type) {
            $insert->execute(['id' => Identifier::generate(), 'type' => $type, 'title' => $type, 'hash' => hash('sha256', $type)]);
        }
    }

    private function countRows(string $table): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    }

}
