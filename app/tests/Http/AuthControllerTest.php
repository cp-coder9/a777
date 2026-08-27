<?php

declare(strict_types=1);

namespace Architex\Tests\Http;

use Architex\Http\Controller\AuthController;
use Architex\Http\Controller\RegistrationController;
use Architex\Http\AuthenticationRoutes;
use Architex\Http\Middleware;
use Architex\Http\Router;
use Architex\Http\Response;
use Closure;
use Architex\Http\Request;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\Identity\AuthenticatedSession;
use Architex\Platform\Identity\AuthenticationGateway;
use Architex\Platform\Identity\IdentityException;
use Architex\Platform\Identity\PendingRegistration;
use Architex\Platform\Identity\PasswordResetGateway;
use Architex\Platform\Identity\RegisterOrganisation;
use Architex\Platform\Identity\RegistrationGateway;
use Architex\Platform\Identity\SessionGateway;
use Architex\Platform\Identity\SessionToken;
use PHPUnit\Framework\TestCase;

final class AuthControllerTest extends TestCase
{
    public function testLoginReturnsSecureCookieAndCsrfWithoutExposingSessionTokenInJson(): void
    {
        $identity = new AuthenticatedIdentity(str_repeat('a', 32), 'owner@example.com', [['organisationId' => str_repeat('b', 32), 'role' => 'owner']]);
        $auth = new class($identity) implements AuthenticationGateway {
            public function __construct(private AuthenticatedIdentity $identity) {}
            public function authenticate(string $email, string $password): AuthenticatedIdentity { return $this->identity; }
        };
        $sessions = $this->sessions($identity);
        $response = (new AuthController($auth, $sessions))->login(Request::json('POST', '/api/v1/sessions', [], json_encode(['email' => 'owner@example.com', 'password' => 'a valid password'])));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('HttpOnly; Secure; SameSite=Lax', (string) $response->header('Set-Cookie'));
        self::assertSame('csrf-secret', $response->json()['data']['csrfToken']);
        self::assertStringNotContainsString('session-secret', $response->body());
    }

    public function testInvalidCredentialsReturnGeneric401(): void
    {
        $auth = new class implements AuthenticationGateway {
            public function authenticate(string $email, string $password): AuthenticatedIdentity { throw new IdentityException('invalid_credentials', 'Email or password is invalid.'); }
        };
        $response = (new AuthController($auth, $this->sessions()))->login(Request::json('POST', '/', [], '{"email":"nobody@example.com","password":"wrong password"}'));
        self::assertSame(401, $response->status());
        self::assertSame('invalid_credentials', $response->json()['error']['code']);
    }

    public function testAnonymousCurrentSessionFailsClosed(): void
    {
        $response = (new AuthController($this->authentication(), $this->sessions()))->current(Request::json('GET', '/'));
        self::assertSame(401, $response->status());
        self::assertSame('unauthenticated', $response->json()['error']['code']);
    }

    public function testRegistrationReturns201WithoutVerificationSecret(): void
    {
        $registration = new class implements RegistrationGateway {
            public function register(RegisterOrganisation $command): PendingRegistration { return new PendingRegistration(str_repeat('a', 32), str_repeat('b', 32), 'email-secret'); }
            public function verifyEmail(string $plaintextToken): AuthenticatedIdentity { throw new \LogicException(); }
        };
        $body = ['email'=>'owner@example.com','password'=>'correct horse battery staple','organisationName'=>'Practice','organisationSlug'=>'practice','acceptedNoticeVersions'=>[
            'terms_of_service'=>'1.0.0','privacy_policy'=>'1.0.0','platform_disclaimer'=>'1.0.0','signup_privacy_notice'=>'1.0.0',
        ]];
        $response = (new RegistrationController($registration))->create(Request::json('POST', '/', [], json_encode($body)));
        self::assertSame(201, $response->status());
        self::assertSame('pending_email_verification', $response->json()['data']['status']);
        self::assertStringNotContainsString('email-secret', $response->body());
    }

    public function testRegistrationDeliveryUnavailableReturns503(): void
    {
        $registration = new class implements RegistrationGateway {
            public function register(RegisterOrganisation $command): PendingRegistration { throw new IdentityException('registration_email_unavailable', 'internal detail'); }
            public function verifyEmail(string $plaintextToken): AuthenticatedIdentity { throw new \LogicException(); }
        };
        $response = (new RegistrationController($registration))->create(Request::json('POST', '/', [], $this->registrationJson()));
        self::assertSame(503, $response->status());
        self::assertSame('registration_email_unavailable', $response->json()['error']['code']);
        self::assertStringNotContainsString('internal detail', $response->body());
    }

    public function testPasswordResetRequestIsEnumerationSafeAndCompletionMapsInvalidToken(): void
    {
        $resets = new class implements PasswordResetGateway {
            public int $requests = 0;
            public function request(string $email): void { $this->requests++; }
            public function complete(string $token, string $newPassword): void { throw new IdentityException('password_reset_invalid', 'invalid'); }
        };
        $controller = new AuthController($this->authentication(), $this->sessions(), $resets);
        $requested = $controller->requestPasswordReset(Request::json('POST', '/', [], '{"email":"missing@example.com"}'));
        self::assertSame(202, $requested->status());
        self::assertSame('accepted', $requested->json()['data']['status']);
        self::assertSame(1, $resets->requests);

        $completed = $controller->completePasswordReset(Request::json('POST', '/', [], '{"token":"bad-token-value-that-is-long-enough-for-input","password":"replacement valid password"}'));
        self::assertSame(400, $completed->status());
        self::assertSame('password_reset_invalid', $completed->json()['error']['code']);
    }

    public function testRegistersTheCompleteVersionedAuthenticationRouteSurface(): void
    {
        $identity = new AuthenticatedIdentity(str_repeat('a', 32), 'owner@example.com', []);
        $registration = new class($identity) implements RegistrationGateway {
            public function __construct(private AuthenticatedIdentity $identity) {}
            public function register(RegisterOrganisation $command): PendingRegistration { return new PendingRegistration(str_repeat('a', 32), str_repeat('b', 32), 'secret'); }
            public function verifyEmail(string $plaintextToken): AuthenticatedIdentity { return $this->identity; }
        };
        $resets = new class implements PasswordResetGateway {
            public function request(string $email): void {}
            public function complete(string $token, string $newPassword): void {}
        };
        $pass = new class implements Middleware {
            public function process(Request $request, Closure $next): Response { return $next($request); }
        };
        $router = new Router();
        (new AuthenticationRoutes(
            new RegistrationController($registration),
            new AuthController($this->authentication($identity), $this->sessions($identity), $resets),
            $pass,
            $pass,
            $pass,
        ))->register($router);

        $cases = [
            ['POST','/api/v1/registrations',$this->registrationJson(),201],
            ['POST','/api/v1/registrations/verify-email','{"token":"valid-verification-token-value"}',200],
            ['POST','/api/v1/sessions','{"email":"owner@example.com","password":"valid password"}',200],
            ['DELETE','/api/v1/session','',401],
            ['GET','/api/v1/session','',401],
            ['POST','/api/v1/password-resets','{"email":"owner@example.com"}',202],
            ['POST','/api/v1/password-resets/complete','{"token":"long-enough-token-value-for-reset-completion","password":"replacement valid password"}',200],
        ];
        foreach ($cases as [$method,$path,$body,$status]) {
            self::assertSame($status, $router->dispatch(Request::json($method, $path, [], $body))->status(), $path);
        }
    }

    private function authentication(?AuthenticatedIdentity $identity = null): AuthenticationGateway
    {
        return new class($identity) implements AuthenticationGateway {
            public function __construct(private ?AuthenticatedIdentity $identity) {}
            public function authenticate(string $email, string $password): AuthenticatedIdentity { return $this->identity ?? throw new \LogicException(); }
        };
    }

    private function registrationJson(): string
    {
        return json_encode(['email'=>'owner@example.com','password'=>'correct horse battery staple','organisationName'=>'Practice','organisationSlug'=>'practice','acceptedNoticeVersions'=>[
            'terms_of_service'=>'1.0.0','privacy_policy'=>'1.0.0','platform_disclaimer'=>'1.0.0','signup_privacy_notice'=>'1.0.0',
        ]], JSON_THROW_ON_ERROR);
    }

    private function sessions(?AuthenticatedIdentity $identity = null): SessionGateway
    {
        return new class($identity) implements SessionGateway {
            public function __construct(private ?AuthenticatedIdentity $identity) {}
            public function rotate(string $userId, ?string $previousSessionId = null, ?string $ipAddress = null, ?string $userAgent = null): SessionToken { return new SessionToken(str_repeat('c', 32), 'session-secret', 'csrf-secret', '2099-01-01T00:00:00Z'); }
            public function resolve(string $plaintextToken): AuthenticatedSession { return new AuthenticatedSession(str_repeat('c', 32), $this->identity ?? throw new \LogicException()); }
            public function verifyCsrf(string $sessionId, string $plaintextCsrf): bool { return true; }
            public function issueCsrf(string $sessionId): string { return 'fresh-csrf'; }
            public function revoke(string $sessionId): void {}
        };
    }
}
