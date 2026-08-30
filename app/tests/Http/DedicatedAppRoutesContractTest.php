<?php

declare(strict_types=1);

namespace Architex\Tests\Http;

use PHPUnit\Framework\TestCase;

final class DedicatedAppRoutesContractTest extends TestCase
{
    public function testManagementAndScopedClientRoutesAreRegistered():void
    {
        $routes=(string)file_get_contents(dirname(__DIR__,2).'/src/Http/DedicatedAppRoutes.php');
        foreach(["get('/api/v1/dedicated-apps'","get('/api/v1/dedicated-apps/installations'","post('/api/v1/dedicated-apps/installations'","post('/api/v1/dedicated-apps/events'","post('/api/v1/dedicated-apps/events/{eventId}/confirm'","post('/api/v1/dedicated-apps/sync'","post('/api/v1/dedicated-app-client/heartbeat'","post('/api/v1/dedicated-app-client/events'","post('/api/v1/dedicated-app-client/sync'"]as$route)self::assertStringContainsString($route,$routes);
        $factory=(string)file_get_contents(dirname(__DIR__,2).'/src/Http/AuthenticationKernelFactory.php');
        self::assertStringContainsString('new DedicatedAppRoutes',$factory);
        self::assertStringContainsString('new DedicatedAppService',$factory);
    }
}
