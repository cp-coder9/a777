<?php

declare(strict_types=1);

namespace Architex\Tests\Http;

use Architex\Http\Controller\ReadinessController;
use Architex\Http\Request;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReadinessControllerTest extends TestCase
{
    public function testReportsReadyOnlyWhenEveryRequiredDependencyIsReady(): void
    {
        $controller = new ReadinessController(
            static fn (): bool => true,
            static fn (): bool => true,
            static fn (): bool => true,
            static fn (): bool => true,
        );

        $response = $controller(Request::json('GET', '/api/v1/readiness'));

        self::assertSame(200, $response->status());
        self::assertSame('ready', $response->json()['data']['status']);
        self::assertSame('ready', $response->json()['data']['components']['database']['status']);
    }

    public function testReportsUnavailableWithoutLeakingProbeFailures(): void
    {
        $controller = new ReadinessController(
            static fn (): bool => throw new RuntimeException('db password secret'),
            static fn (): bool => false,
            static fn (): bool => true,
            static fn (): bool => false,
        );

        $response = $controller(Request::json('GET', '/api/v1/readiness'));

        self::assertSame(503, $response->status());
        self::assertSame('unavailable', $response->json()['data']['status']);
        self::assertStringNotContainsString('secret', $response->body());
        self::assertSame('unavailable', $response->json()['data']['components']['mail']['status']);
    }
}
