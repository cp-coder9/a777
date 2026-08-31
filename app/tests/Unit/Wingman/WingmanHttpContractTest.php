<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Wingman;

use PHPUnit\Framework\TestCase;

final class WingmanHttpContractTest extends TestCase
{
    public function testAuthenticatedKernelOwnsTheFourGuidedWingmanRoutes(): void
    {
        $routes = @file_get_contents(dirname(__DIR__, 3) . '/src/Http/WingmanRoutes.php');
        $controller = @file_get_contents(dirname(__DIR__, 3) . '/src/Http/Controller/WingmanController.php');
        $factory = (string) file_get_contents(dirname(__DIR__, 3) . '/src/Http/AuthenticationKernelFactory.php');
        self::assertIsString($routes);
        self::assertIsString($controller);
        foreach ([
            '/api/v1/wingman/bootstrap', '/api/v1/wingman/messages',
            '/api/v1/wingman/conversations/{conversationId}',
            '/api/v1/wingman/suggestions/{suggestionId}/feedback',
        ] as $route) self::assertStringContainsString($route, $routes);
        foreach (['WingmanController', 'WingmanService', 'NvidiaNemotronClient', 'CurlNvidiaTransport', 'WingmanConfig'] as $type) {
            self::assertStringContainsString($type, $factory);
        }
        self::assertStringContainsString('RateLimitMiddleware', $routes);
        self::assertStringContainsString('PermissionMiddleware', $routes);
        self::assertStringNotContainsString('NVIDIA_API_KEY', $controller);
    }
}
