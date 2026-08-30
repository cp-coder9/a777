<?php

declare(strict_types=1);

namespace Architex\Tests\Http;

use PHPUnit\Framework\TestCase;

final class NuancedQcRoutesContractTest extends TestCase
{
    public function testAuthenticatedNuancedQcRoutesAndDeepLinksAreRegistered(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2).'/src/Http/NuancedQcRoutes.php');
        foreach ([
            "get('/api/v1/nuanced-qc'", "post('/api/v1/nuanced-qc/runs'",
            "post('/api/v1/nuanced-qc/runs/{runId}/summary'", "post('/api/v1/nuanced-qc/runs/{runId}/categories'",
            "post('/api/v1/nuanced-qc/runs/{runId}/findings'", "post('/api/v1/nuanced-qc/runs/{runId}/evidence'",
            "post('/api/v1/nuanced-qc/findings/{findingId}/retests'", "post('/api/v1/nuanced-qc/retests/{retestId}/complete'",
            "post('/api/v1/nuanced-qc/risks/{riskId}/accept'", "post('/api/v1/nuanced-qc/runs/{runId}/signoffs'",
            "post('/api/v1/nuanced-qc/roles'",
        ] as $route) self::assertStringContainsString($route, $routes);
        $factory = (string) file_get_contents(dirname(__DIR__, 2).'/src/Http/AuthenticationKernelFactory.php');
        self::assertStringContainsString('new NuancedQcService', $factory);
        self::assertStringContainsString('new NuancedQcRoutes', $factory);
        $front = (string) file_get_contents(dirname(__DIR__, 2).'/public/index.php');
        foreach (['admin/qc-controls/nuanced-gap-test', 'qc/runs/', 'qc/projects/'] as $deepLink) self::assertStringContainsString($deepLink, $front);
    }
}
