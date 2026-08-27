<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Identity;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\Identity\IdentityException;
use Architex\Platform\Identity\PasswordHasher;
use Architex\Platform\Identity\PasswordResetNotifier;
use Architex\Platform\Identity\PasswordResetService;
use Architex\Platform\Identity\SessionService;
use Architex\Tests\Support\MariaDbTestCase;

final class PasswordResetServiceTest extends MariaDbTestCase
{
    public function testIssuesHashedOneTimeTokenCompletesResetAndRevokesSessions(): void
    {
        $userId = $this->activeUser('owner@example.com', 'original valid password');
        $session = (new SessionService($this->pdo))->rotate($userId);
        $notifier = new class implements PasswordResetNotifier {
            public ?string $token = null;
            public function isAvailable(): bool { return true; }
            public function send(string $email, string $token, string $expiresAt): void { $this->token = $token; }
        };
        $service = new PasswordResetService($this->pdo, $notifier);

        $service->request('owner@example.com');
        self::assertNotNull($notifier->token);
        self::assertNotSame($notifier->token, $this->pdo->query('SELECT token_hash FROM password_reset_tokens')->fetchColumn());
        $service->complete($notifier->token, 'replacement valid password');

        self::assertTrue((new PasswordHasher())->verify('replacement valid password', (string) $this->pdo->query('SELECT password_hash FROM users')->fetchColumn()));
        self::assertNotNull($this->pdo->query('SELECT consumed_at FROM password_reset_tokens')->fetchColumn());
        self::assertNotNull($this->pdo->query("SELECT revoked_at FROM user_sessions WHERE id=UNHEX('{$session->sessionId}')")->fetchColumn());
        $this->expectException(IdentityException::class);
        $service->complete($notifier->token, 'another replacement password');
    }

    public function testUnknownEmailIsIndistinguishableAndDoesNotNotify(): void
    {
        $notifier = new class implements PasswordResetNotifier {
            public int $calls = 0;
            public function isAvailable(): bool { return true; }
            public function send(string $email, string $token, string $expiresAt): void { $this->calls++; }
        };
        (new PasswordResetService($this->pdo, $notifier))->request('missing@example.com');
        self::assertSame(0, $notifier->calls);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn());
    }

    public function testUnavailableDeliveryFailsBeforeIssuingToken(): void
    {
        $this->activeUser('owner@example.com', 'original valid password');
        $notifier = new class implements PasswordResetNotifier {
            public function isAvailable(): bool { return false; }
            public function send(string $email, string $token, string $expiresAt): void { throw new \LogicException(); }
        };
        try {
            (new PasswordResetService($this->pdo, $notifier))->request('owner@example.com');
            self::fail('Expected unavailable delivery to fail closed.');
        } catch (IdentityException $exception) {
            self::assertSame('password_reset_unavailable', $exception->reason);
        }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn());
    }

    private function activeUser(string $email, string $password): string
    {
        $id = Identifier::generate();
        $insert = $this->pdo->prepare("INSERT INTO users (id,email,password_hash,status,email_verified_at) VALUES (UNHEX(:id),:email,:hash,'active',UTC_TIMESTAMP(6))");
        $insert->execute(['id' => $id, 'email' => $email, 'hash' => (new PasswordHasher())->hash($password)]);
        return $id;
    }
}
