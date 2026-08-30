<?php

declare(strict_types=1);

namespace Architex\Tests\Http;

use PHPUnit\Framework\TestCase;

final class PlatformCompletenessRoutesContractTest extends TestCase
{
    public function testAllGovernedRoutesAreRegistered():void
    {
        $routes=(string)file_get_contents(dirname(__DIR__,2).'/src/Http/PlatformCompletenessRoutes.php');
        foreach(["get('/api/v1/platform-completeness'","put('/api/v1/platform-completeness/items/{itemId}'","put('/api/v1/platform-completeness/workstreams/{workstreamId}'","post('/api/v1/platform-completeness/items/{itemId}/reviews'","post('/api/v1/platform-completeness/items/{itemId}/evidence'","post('/api/v1/platform-completeness/items/{itemId}/uploads'","post('/api/v1/platform-completeness/items/{itemId}/signoffs'","post('/api/v1/platform-completeness/workstreams/{workstreamId}/signoffs'","put('/api/v1/platform-completeness/roles/{userId}'"]as$route)self::assertStringContainsString($route,$routes);
        $factory=(string)file_get_contents(dirname(__DIR__,2).'/src/Http/AuthenticationKernelFactory.php');self::assertStringContainsString('new PlatformCompletenessRoutes',$factory);
    }
}
