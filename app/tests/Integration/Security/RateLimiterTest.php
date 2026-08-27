<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Security;

use Architex\Platform\Security\RateLimiter;
use Architex\Http\Middleware\RateLimitMiddleware;
use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Tests\Support\MariaDbTestCase;
use InvalidArgumentException;

final class RateLimiterTest extends MariaDbTestCase
{
    public function testPersistsAndRejectsRequestsBeyondTheBucketLimit(): void
    {
        $limiter = new RateLimiter($this->pdo);

        self::assertTrue($limiter->consume('registration', '127.0.0.1', 2, 60)->allowed);
        self::assertTrue($limiter->consume('registration', '127.0.0.1', 2, 60)->allowed);
        $blocked = $limiter->consume('registration', '127.0.0.1', 2, 60);

        self::assertFalse($blocked->allowed);
        self::assertGreaterThanOrEqual(1, $blocked->retryAfterSeconds);
        self::assertSame(3, (int) $this->pdo->query('SELECT request_count FROM rate_limit_buckets')->fetchColumn());
    }

    public function testScopesAndKeysAreIndependentAndRawKeysAreNotStored(): void
    {
        $limiter = new RateLimiter($this->pdo);
        self::assertTrue($limiter->consume('login', 'person@example.com|127.0.0.1', 1, 60)->allowed);
        self::assertTrue($limiter->consume('login', 'other@example.com|127.0.0.1', 1, 60)->allowed);
        self::assertTrue($limiter->consume('registration', 'person@example.com|127.0.0.1', 1, 60)->allowed);
        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM rate_limit_buckets WHERE key_hash LIKE '%person%'")->fetchColumn());
    }

    public function testRejectsInvalidPolicyValuesBeforeDatabaseAccess(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new RateLimiter($this->pdo))->consume('registration', 'key', 0, 60);
    }

    public function testMiddlewareReturns429AndRetryHeadersWithoutCallingDownstream(): void
    {
        $middleware = new RateLimitMiddleware(new RateLimiter($this->pdo), 'registration', 1, 60);
        $request = Request::json('POST', '/api/v1/registrations')->withAttribute('remoteIp', '127.0.0.1');
        $calls = 0;
        $next = static function (Request $request) use (&$calls): Response {
            $calls++;
            return Response::success([]);
        };

        self::assertSame(200, $middleware->process($request, $next)->status());
        $blocked = $middleware->process($request, $next);
        self::assertSame(429, $blocked->status());
        self::assertSame('rate_limited', $blocked->json()['error']['code']);
        self::assertGreaterThanOrEqual(1, (int) $blocked->header('Retry-After'));
        self::assertSame(1, $calls);
    }
}
