<?php
declare(strict_types=1);

namespace Architex\Tests\Http;

use Architex\Http\Kernel;
use Architex\Http\Middleware\JsonRequestMiddleware;
use Architex\Http\Middleware\SecurityHeadersMiddleware;
use Architex\Http\Middleware\CsrfMiddleware;
use Architex\Http\Middleware\SessionMiddleware;
use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Http\Router;
use PHPUnit\Framework\TestCase;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\Identity\AuthenticatedSession;
use Architex\Platform\Identity\IdentityException;
use Architex\Platform\Identity\SessionGateway;
use Architex\Platform\Identity\SessionToken;

final class MiddlewarePipelineTest extends TestCase
{
    public function testJsonBoundaryRejectsUnsupportedMalformedAndOversizedBodies(): void
    {
        $kernel = $this->kernel([new JsonRequestMiddleware(20)]);
        $unsupported = $kernel->handle(Request::json('POST', '/write', [], '{}'));
        $malformed = $kernel->handle(Request::json('POST', '/write', ['Content-Type' => 'application/json'], '{'));
        $oversized = $kernel->handle(Request::json('POST', '/write', ['Content-Type' => 'application/json'], json_encode(['value' => str_repeat('x', 40)])));

        self::assertSame([415, 'unsupported_media_type'], [$unsupported->status(), $unsupported->json()['error']['code']]);
        self::assertSame([400, 'invalid_json'], [$malformed->status(), $malformed->json()['error']['code']]);
        self::assertSame([413, 'request_too_large'], [$oversized->status(), $oversized->json()['error']['code']]);
    }

    public function testSecurityHeadersApplyToSuccessAndFailureResponses(): void
    {
        $kernel = $this->kernel([new SecurityHeadersMiddleware(), new JsonRequestMiddleware()]);
        foreach ([
            Request::json('POST', '/write', ['Content-Type' => 'application/json'], '{}'),
            Request::json('POST', '/write', [], '{}'),
        ] as $request) {
            $response = $kernel->handle($request);
            self::assertSame('nosniff', $response->header('X-Content-Type-Options'));
            self::assertSame('DENY', $response->header('X-Frame-Options'));
            self::assertSame('no-store', $response->header('Cache-Control'));
        }
    }

    public function testRequestAttributesAreImmutable(): void
    {
        $request = Request::json('GET', '/');
        $changed = $request->withAttribute('identity', 'user');
        self::assertNull($request->attribute('identity'));
        self::assertSame('user', $changed->attribute('identity'));
    }

    public function testSessionAndCsrfMiddlewareRejectInvalidCredentialsAndUnsafeRequests(): void
    {
        $identity = new AuthenticatedIdentity(str_repeat('a', 32), 'owner@example.com', []);
        $gateway = new class($identity) implements SessionGateway {
            public function __construct(private AuthenticatedIdentity $identity) {}
            public function rotate(string $userId, ?string $previousSessionId = null, ?string $ipAddress = null, ?string $userAgent = null): SessionToken { throw new \LogicException(); }
            public function resolve(string $plaintextToken): AuthenticatedSession {
                if ($plaintextToken !== str_repeat('x', 43)) { throw new IdentityException('session_invalid', 'invalid'); }
                return new AuthenticatedSession(str_repeat('c', 32), $this->identity);
            }
            public function verifyCsrf(string $sessionId, string $plaintextCsrf): bool { return $plaintextCsrf === 'valid-csrf'; }
            public function issueCsrf(string $sessionId): string { return 'fresh-csrf'; }
            public function revoke(string $sessionId): void {}
        };
        $next = static fn (Request $request): Response => Response::success(['identity' => $request->attribute('identity') instanceof AuthenticatedIdentity]);
        $invalid = (new SessionMiddleware($gateway))->process(Request::json('GET', '/', ['Authorization' => 'Bearer ' . str_repeat('y', 43)]), $next);
        self::assertSame(401, $invalid->status());

        $authenticatedRequest = Request::json('POST', '/', ['Authorization' => 'Bearer ' . str_repeat('x', 43)]);
        $missingCsrf = (new SessionMiddleware($gateway))->process($authenticatedRequest, static fn (Request $request): Response => (new CsrfMiddleware($gateway))->process($request, $next));
        self::assertSame(419, $missingCsrf->status());
        $publicAcceptance = (new SessionMiddleware($gateway))->process(Request::json('POST', '/api/v1/proposal-acceptance/' . str_repeat('a', 43), ['Authorization' => 'Bearer ' . str_repeat('x', 43)]), static fn (Request $request): Response => (new CsrfMiddleware($gateway))->process($request, $next));
        self::assertSame(200, $publicAcceptance->status());
        $valid = (new SessionMiddleware($gateway))->process(Request::json('POST', '/', ['Authorization' => 'Bearer ' . str_repeat('x', 43), 'X-CSRF-Token' => 'valid-csrf']), static fn (Request $request): Response => (new CsrfMiddleware($gateway))->process($request, $next));
        self::assertSame(200, $valid->status());
        self::assertTrue($valid->json()['data']['identity']);
    }

    /** @param list<\Architex\Http\Middleware> $middleware */
    private function kernel(array $middleware): Kernel
    {
        $router = new Router();
        $router->post('/write', static fn (Request $request): Response => Response::success($request->jsonBody()));
        return new Kernel($router, middleware: $middleware);
    }
}
