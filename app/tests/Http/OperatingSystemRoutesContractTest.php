<?php

declare(strict_types=1);

namespace Architex\Tests\Http;

use PHPUnit\Framework\TestCase;

final class OperatingSystemRoutesContractTest extends TestCase
{
    public function testUnifiedProjectMobileInboxCapabilityAndReleaseGateRoutesAreRegistered():void
    {
        $routes=(string)file_get_contents(dirname(__DIR__,2).'/src/Http/OperatingSystemRoutes.php');
        foreach(["get('/api/v1/projects/{projectId}/operating-snapshot'","get('/api/v1/mobile-decision-inbox'","post('/api/v1/inbox/{inboxItemId}/transition'","get('/api/v1/api-capabilities'","get('/api/v1/release-gates'","post('/api/v1/release-gates'"]as$route)self::assertStringContainsString($route,$routes);
        $factory=(string)file_get_contents(dirname(__DIR__,2).'/src/Http/AuthenticationKernelFactory.php');
        self::assertStringContainsString('new OperatingSystemRoutes',$factory);
        self::assertStringContainsString('new ProductOperatingSystemService',$factory);
        self::assertStringContainsString("get('/api/v1/build-info'",$factory);
        self::assertStringContainsString('new BuildInfoController',$factory);
    }
}
