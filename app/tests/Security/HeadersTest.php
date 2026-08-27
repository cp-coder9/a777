<?php

declare(strict_types=1);

namespace Architex\Tests\Security;

use Architex\Http\Middleware\SecurityHeadersMiddleware;
use Architex\Http\Request;
use Architex\Http\Response;
use PHPUnit\Framework\TestCase;

final class HeadersTest extends TestCase
{
    public function testSecurityHeadersIncludeRestrictiveCspAndBrowserControls(): void
    {
        $response = (new SecurityHeadersMiddleware())->process(Request::json('GET', '/'), static fn (): Response => Response::success(['ok'=>true]));

        self::assertSame("default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; frame-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'", $response->header('Content-Security-Policy'));
        self::assertSame('nosniff', $response->header('X-Content-Type-Options'));
        self::assertSame('DENY', $response->header('X-Frame-Options'));
        self::assertSame('camera=(), microphone=(), geolocation=()', $response->header('Permissions-Policy'));
    }
}
