<?php
declare(strict_types=1);

namespace Architex\Tests\Http;

use Architex\Http\Kernel;
use Architex\Http\Middleware\JsonRequestMiddleware;
use Architex\Http\Middleware\SecurityHeadersMiddleware;
use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Http\Router;
use PHPUnit\Framework\TestCase;

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

    /** @param list<\Architex\Http\Middleware> $middleware */
    private function kernel(array $middleware): Kernel
    {
        $router = new Router();
        $router->post('/write', static fn (Request $request): Response => Response::success($request->jsonBody()));
        return new Kernel($router, middleware: $middleware);
    }
}
