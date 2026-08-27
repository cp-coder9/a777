<?php

declare(strict_types=1);

namespace Architex\Tests\Http;

use Architex\Http\Controller\HealthController;
use Architex\Http\Kernel;
use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Http\Router;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class KernelTest extends TestCase
{
    private const REQUEST_ID = '01JQ2V8S1K5XW3Y7Z9ABCD4EFG';

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
        self::assertSame(self::REQUEST_ID, $response->json()['meta']['requestId']);
        self::assertSame(['code' => 'not_found', 'message' => 'Route not found.', 'details' => null], $response->json()['error']);
    }

    public function testUnsupportedMethodReturnsAllowedMethodsWithoutDispatchingHandler(): void
    {
        $response = $this->kernel()->handle(Request::json('POST', '/api/v1/health'));

        self::assertSame(405, $response->status());
        self::assertSame('GET, HEAD', $response->header('Allow'));
        self::assertSame('method_not_allowed', $response->json()['error']['code']);
        self::assertSame(self::REQUEST_ID, $response->json()['meta']['requestId']);
    }

    public function testHeadUsesGetRouteAndSuppressesBody(): void
    {
        $response = $this->kernel()->handle(Request::json('HEAD', '/api/v1/health'));

        self::assertSame(200, $response->status());
        self::assertSame('', $response->body());
        self::assertSame('ok', $response->json()['data']['status']);
        self::assertSame(self::REQUEST_ID, $response->json()['meta']['requestId']);
    }

    public function testUnexpectedFailureReturnsSafeErrorWithoutExceptionDetails(): void
    {
        $router = new Router();
        $router->get('/api/v1/explode', static fn (Request $request): Response => throw new RuntimeException('database password=secret'));

        $response = (new Kernel($router, static fn (): string => self::REQUEST_ID))
            ->handle(Request::json('GET', '/api/v1/explode'));

        self::assertSame(500, $response->status());
        self::assertSame(['code' => 'internal_error', 'message' => 'The request could not be completed.', 'details' => null], $response->json()['error']);
        self::assertStringNotContainsString('database password=secret', $response->body());
        self::assertStringNotContainsString('KernelTest.php', $response->body());
    }

    public function testJsonEncodingFailureProducesSafeErrorEnvelope(): void
    {
        $response = Response::success(['invalid' => INF])->withMeta(['requestId' => self::REQUEST_ID]);

        self::assertSame(500, $response->status());
        self::assertSame(
            ['data' => null, 'meta' => ['requestId' => self::REQUEST_ID], 'error' => ['code' => 'internal_error', 'message' => 'The response could not be encoded.', 'details' => null]],
            json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    private function kernel(): Kernel
    {
        $router = new Router();
        $router->get('/api/v1/health', new HealthController());

        return new Kernel($router, static fn (): string => self::REQUEST_ID);
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
}
