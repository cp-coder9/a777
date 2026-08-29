<?php

declare(strict_types=1);

namespace Architex\Tests\Http;

use PHPUnit\Framework\TestCase;

final class CPDRoutesContractTest extends TestCase
{
    public function testAuthenticatedCpdLifecycleAndPublicVerificationRoutesAreRegistered(): void
    {
        $routes=(string)file_get_contents(dirname(__DIR__,2).'/src/Http/CPDRoutes.php');
        foreach([
            "get('/api/v1/cpd/workspace'", "get('/api/v1/cpd/professional-bodies'", "post('/api/v1/cpd/profile'",
            "post('/api/v1/cpd/content'", "post('/api/v1/cpd/assessments'", "post('/api/v1/cpd/assessments/{assessmentId}/review'",
            "post('/api/v1/cpd/accreditation'", "post('/api/v1/cpd/accreditation/{applicationId}/decision'", "post('/api/v1/cpd/courses'",
            "post('/api/v1/cpd/entitlements/manual'", "post('/api/v1/cpd/entitlements/{entitlementId}/review'", "post('/api/v1/cpd/courses/{courseId}/attempts'",
            "post('/api/v1/cpd/attempts/{attemptId}/submit'", "post('/api/v1/cpd/attempts/{attemptId}/review'", "post('/api/v1/cpd/attempts/{attemptId}/certificate'",
            "get('/api/v1/cpd/certificates/verify/{verificationCode}'",
        ] as $route) self::assertStringContainsString($route,$routes);
        self::assertStringContainsString('RateLimitMiddleware',$this->factory());
        self::assertStringContainsString('new CPDRoutes',$this->factory());
    }

    private function factory(): string
    {
        return (string)file_get_contents(dirname(__DIR__,2).'/src/Http/AuthenticationKernelFactory.php');
    }
}
