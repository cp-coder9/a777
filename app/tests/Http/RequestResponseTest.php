<?php

declare(strict_types=1);

namespace Architex\Tests\Http;

use Architex\Http\Request;
use Architex\Http\Response;
use PHPUnit\Framework\TestCase;

final class RequestResponseTest extends TestCase
{
    public function testFromGlobalsParsesMethodHeadersAndQueryFreePathWithoutGlobalMutation(): void
    {
        $request = Request::fromGlobals([
            'REQUEST_METHOD' => 'post',
            'REQUEST_URI' => '/api/v1/health?probe=one',
            'HTTP_X_REQUEST_ID' => 'browser-request',
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => '2',
            'REMOTE_ADDR' => '127.0.0.1',
        ], '{}');

        self::assertSame('POST', $request->method());
        self::assertSame('/api/v1/health', $request->path());
        self::assertSame('browser-request', $request->header('X-Request-Id'));
        self::assertSame('application/json', $request->header('content-type'));
        self::assertSame('2', $request->header('Content-Length'));
        self::assertSame('{}', $request->body());
        self::assertSame('127.0.0.1', $request->attribute('remoteIp'));
    }

    public function testEmitUsesInjectedEmittersAndContentTypeCannotBeOverriddenOrDuplicated(): void
    {
        $status = null;
        $headers = [];
        $bodies = [];
        $response = Response::error(418, 'teapot', 'Short and stout.', headers: [
            'content-type' => 'text/plain',
            'X-Trace' => 'first',
            'x-trace' => 'second',
        ]);

        $response->emit(
            static function (int $value) use (&$status): void { $status = $value; },
            static function (string $value) use (&$headers): void { $headers[] = $value; },
            static function (string $value) use (&$bodies): void { $bodies[] = $value; },
        );

        self::assertSame(418, $status);
        self::assertSame(['Content-Type: application/json; charset=utf-8', 'X-Trace: second'], $headers);
        self::assertCount(1, $bodies);
        self::assertSame('teapot', json_decode($bodies[0], true, flags: JSON_THROW_ON_ERROR)['error']['code']);
    }

    public function testEmitDoesNotWriteABodyForHeadResponse(): void
    {
        $status = null;
        $headers = [];
        $bodies = [];

        Response::success(['status' => 'ok'])->withoutBody()->emit(
            static function (int $value) use (&$status): void { $status = $value; },
            static function (string $value) use (&$headers): void { $headers[] = $value; },
            static function (string $value) use (&$bodies): void { $bodies[] = $value; },
        );

        self::assertSame(200, $status);
        self::assertSame(['Content-Type: application/json; charset=utf-8'], $headers);
        self::assertSame([], $bodies);
    }
}
