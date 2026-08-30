<?php

declare(strict_types=1);

namespace Architex\Tests\Http;

use PHPUnit\Framework\TestCase;

final class MunicipalReadinessRefinementRoutesContractTest extends TestCase
{
    public function testAuthenticatedMunicipalRefinementSurfaceIsWired():void
    {
        $routes=(string)file_get_contents(dirname(__DIR__,2).'/src/Http/MunicipalReadinessRoutes.php');
        $controller=(string)file_get_contents(dirname(__DIR__,2).'/src/Http/Controller/MunicipalReadinessController.php');
        $factory=(string)file_get_contents(dirname(__DIR__,2).'/src/Http/AuthenticationKernelFactory.php');
        foreach([
            '/api/v1/projects/{projectId}/municipal-profile',
            '/api/v1/submission-readiness/{assessmentId}/workflow',
            '/api/v1/submission-readiness/{assessmentId}/findings/{findingId}/resolve',
            '/api/v1/submission-readiness/{assessmentId}/routes/{routeId}/assign',
            '/api/v1/submission-readiness/{assessmentId}/routes/{routeId}/sign-off',
            '/api/v1/submission-readiness/{assessmentId}/packs/issue',
            '/api/v1/projects/{projectId}/municipal-submission-packs',
            '/api/v1/municipal-submission-packs/{packId}',
        ]as$route)self::assertStringContainsString($route,$routes);
        foreach(['profile','saveProfile','workflow','resolveFinding','assignRoute','signOffRoute','issuePack','packs','showPack']as$method)self::assertStringContainsString('function '.$method.'(',$controller);
        self::assertStringContainsString('MunicipalReadinessRefinementService',$factory);
        self::assertStringContainsString('new MunicipalReadinessRefinementService($this->pdo)',$factory);
        self::assertStringContainsString("'municipal_pack_integrity_failed'",$controller);
    }
}
