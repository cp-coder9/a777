<?php

declare(strict_types=1);

namespace Architex\Tests\Http;

use Architex\Http\Controller\HealthController;
use Architex\Http\Kernel;
use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Http\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class KernelTest extends TestCase
{
    public function testHealthResponseUsesStableEnvelopeAndRequestId(): void
    {
        $response = $this->kernel()->handle(Request::json('GET', '/api/v1/health'));

        self::assertSame(200, $response->status());
        self::assertSame('application/json; charset=utf-8', $response->header('Content-Type'));
        self::assertSame('ok', $response->json()['data']['status']);
        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $response->json()['meta']['requestId']);
        self::assertNull($response->json()['error']);
    }

    public function testDefaultKernelGeneratesUlidWithCurrentTimestamp(): void
    {
        $router = new Router();
        $router->get('/api/v1/health', new HealthController());
        $before = (int) floor(microtime(true) * 1_000);

        $response = (new Kernel($router))->handle(Request::json('GET', '/api/v1/health'));
        $after = (int) floor(microtime(true) * 1_000);
        $requestId = $response->json()['meta']['requestId'];

        self::assertGreaterThanOrEqual($before, self::ulidTimestamp($requestId));
        self::assertLessThanOrEqual($after, self::ulidTimestamp($requestId));
    }

    public function testUnknownRouteReturnsStableNotFoundEnvelope(): void
    {
        $response = $this->kernel()->handle(Request::json('GET', '/api/v1/missing'));

        self::assertSame(404, $response->status());
        self::assertNull($response->json()['data']);
        self::assertCurrentUlid($response->json()['meta']['requestId']);
        self::assertSame(['code' => 'not_found', 'message' => 'Route not found.', 'details' => null], $response->json()['error']);
    }

    public function testUnsupportedMethodReturnsAllowedMethodsWithoutDispatchingHandler(): void
    {
        $response = $this->kernel()->handle(Request::json('POST', '/api/v1/health'));

        self::assertSame(405, $response->status());
        self::assertSame('GET, HEAD', $response->header('Allow'));
        self::assertSame('method_not_allowed', $response->json()['error']['code']);
        self::assertCurrentUlid($response->json()['meta']['requestId']);
    }

    public function testDynamicRouteAddsDecodedPathParameterWithoutMatchingExtraSegments(): void
    {
        $router = new Router();
        $router->get('/api/v1/projects/{projectId}', static fn (Request $request): Response => Response::success(['id' => $request->attribute('projectId')]));
        $kernel = new Kernel($router, static fn (): string => self::currentUlid());

        $response = $kernel->handle(Request::json('GET', '/api/v1/projects/abc%20123'));
        self::assertSame(200, $response->status());
        self::assertSame('abc 123', $response->json()['data']['id']);
        self::assertSame(404, $kernel->handle(Request::json('GET', '/api/v1/projects/a/b'))->status());
    }

    public function testHeadUsesGetRouteAndSuppressesBody(): void
    {
        $response = $this->kernel()->handle(Request::json('HEAD', '/api/v1/health'));

        self::assertSame(200, $response->status());
        self::assertSame('', $response->body());
        self::assertSame('ok', $response->json()['data']['status']);
        self::assertCurrentUlid($response->json()['meta']['requestId']);
    }

    public function testHeadNotFoundSuppressesResponseBody(): void
    {
        $response = $this->kernel()->handle(Request::json('HEAD', '/api/v1/missing'));

        self::assertSame(404, $response->status());
        self::assertSame('', $response->body());
        self::assertSame('not_found', $response->json()['error']['code']);
    }

    public function testHeadMethodNotAllowedSuppressesResponseBody(): void
    {
        $router = new Router();
        $router->add('POST', '/api/v1/write-only', static fn (Request $request): Response => Response::success([]));

        $response = (new Kernel($router, static fn (): string => self::currentUlid()))
            ->handle(Request::json('HEAD', '/api/v1/write-only'));

        self::assertSame(405, $response->status());
        self::assertSame('', $response->body());
        self::assertSame('method_not_allowed', $response->json()['error']['code']);
    }

    public function testHeadHandlerFailureSuppressesResponseBody(): void
    {
        $router = new Router();
        $router->get('/api/v1/explode', static fn (Request $request): Response => throw new RuntimeException('secret failure'));

        $response = (new Kernel($router, static fn (): string => self::currentUlid()))
            ->handle(Request::json('HEAD', '/api/v1/explode'));

        self::assertSame(500, $response->status());
        self::assertSame('', $response->body());
        self::assertSame('internal_error', $response->json()['error']['code']);
    }

    public function testGeneratorFailureUsesCurrentValidFallbackUlid(): void
    {
        $response = (new Kernel($this->healthRouter(), static fn (): string => throw new RuntimeException('generator failed')))
            ->handle(Request::json('GET', '/api/v1/health'));

        self::assertSame(200, $response->status());
        self::assertCurrentUlid($response->json()['meta']['requestId']);
    }

    #[DataProvider('invalidRequestIds')]
    public function testInvalidOrStaleInjectedRequestIdIsReplaced(string $candidate): void
    {
        $response = (new Kernel($this->healthRouter(), static fn (): string => $candidate))
            ->handle(Request::json('GET', '/api/v1/health'));

        self::assertNotSame($candidate, $response->json()['meta']['requestId']);
        self::assertCurrentUlid($response->json()['meta']['requestId']);
    }

    /** @return iterable<string,array{string}> */
    public static function invalidRequestIds(): iterable
    {
        yield 'wrong alphabet and length' => ['not-a-valid-ulid'];
        yield 'stale but syntactically valid' => [self::ulidAt((int) floor(microtime(true) * 1_000) - 600_000)];
        yield 'invalid utf8' => ["\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff"];
    }

    public function testExactPathPolicyRejectsTrailingAndRepeatedLeadingSlashAliases(): void
    {
        foreach (['/api/v1/health/', '//api/v1/health'] as $path) {
            $response = $this->kernel()->handle(Request::json('GET', $path . '?ignored=query'));

            self::assertSame(404, $response->status(), $path);
            self::assertSame('not_found', $response->json()['error']['code'], $path);
        }
    }

    public function testUnexpectedFailureReturnsSafeErrorWithoutExceptionDetails(): void
    {
        $router = new Router();
        $router->get('/api/v1/explode', static fn (Request $request): Response => throw new RuntimeException('database password=secret'));

        $response = (new Kernel($router, static fn (): string => self::currentUlid()))
            ->handle(Request::json('GET', '/api/v1/explode'));

        self::assertSame(500, $response->status());
        self::assertSame(['code' => 'internal_error', 'message' => 'The request could not be completed.', 'details' => null], $response->json()['error']);
        self::assertStringNotContainsString('database password=secret', $response->body());
        self::assertStringNotContainsString('KernelTest.php', $response->body());
    }

    public function testJsonEncodingFailureProducesSafeErrorEnvelope(): void
    {
        $requestId = self::currentUlid();
        $response = Response::success(['invalid' => INF])->withMeta(['requestId' => $requestId]);

        self::assertSame(500, $response->status());
        self::assertSame(
            ['data' => null, 'meta' => ['requestId' => $requestId], 'error' => ['code' => 'internal_error', 'message' => 'The response could not be encoded.', 'details' => null]],
            json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    private function kernel(): Kernel
    {
        return new Kernel($this->healthRouter(), static fn (): string => self::currentUlid());
    }

    private function healthRouter(): Router
    {
        $router = new Router();
        $router->get('/api/v1/health', new HealthController());

        return $router;
    }

    private static function ulidTimestamp(string $ulid): int
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $value = 0;
        foreach (str_split(substr($ulid, 0, 10)) as $character) {
            $value = ($value << 5) | strpos($alphabet, $character);
        }

        return $value;
    }

    private static function currentUlid(): string
    {
        return self::ulidAt((int) floor(microtime(true) * 1_000));
    }

    private static function ulidAt(int $milliseconds): string
    {
        $bytes = pack('Nn', intdiv($milliseconds, 65_536), $milliseconds & 0xffff) . str_repeat("\0", 10);
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $ulid = $alphabet[ord($bytes[0]) >> 5];
        $buffer = ord($bytes[0]) & 31;
        $bits = 5;

        foreach (str_split(substr($bytes, 1)) as $byte) {
            $buffer = ($buffer << 8) | ord($byte);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $ulid .= $alphabet[($buffer >> $bits) & 31];
                $buffer &= (1 << $bits) - 1;
            }
        }

        return $ulid;
    }

    private static function assertCurrentUlid(string $ulid): void
    {
        $now = (int) floor(microtime(true) * 1_000);
        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/D', $ulid);
        self::assertGreaterThanOrEqual($now - 60_000, self::ulidTimestamp($ulid));
        self::assertLessThanOrEqual($now + 60_000, self::ulidTimestamp($ulid));
    }
}
