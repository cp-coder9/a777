<?php

declare(strict_types=1);

namespace Architex\Tests\Http;

use PHPUnit\Framework\TestCase;

final class JobTicketMarketplaceRoutesContractTest extends TestCase
{
    public function testAuthenticatedMarketplaceRoutesAreRegisteredInTheProductionKernel(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2).'/src/Http/JobTicketMarketplaceRoutes.php');
        foreach ([
            "get('/api/v1/job-tickets'",
            "post('/api/v1/job-tickets'",
            "get('/api/v1/job-tickets/{ticketId}'",
            "get('/api/v1/job-tickets/{ticketId}/tree'",
            "post('/api/v1/job-tickets/{ticketId}/classification-override'",
            "post('/api/v1/job-tickets/{ticketId}/clarifications'",
            "post('/api/v1/job-tickets/{ticketId}/quotations'",
            "get('/api/v1/job-tickets/{ticketId}/quotations'",
            "post('/api/v1/job-tickets/{ticketId}/quotations/{quotationId}/withdraw'",
            "post('/api/v1/job-tickets/{ticketId}/quotations/{quotationId}/accept'",
            "post('/api/v1/job-tickets/{ticketId}/freelancer-quotations/{quotationId}/accept'",
            "post('/api/v1/job-tickets/{ticketId}/expansions'",
            "put('/api/v1/freelancers/me'",
            "get('/api/v1/freelancers/me'",
            "post('/api/v1/freelancers/me/publish'",
            "get('/api/v1/freelancer-engagements'",
            "get('/api/v1/freelancer-engagements/{engagementId}'",
            "post('/api/v1/freelancer-engagements/{engagementId}/timesheets'",
            "post('/api/v1/freelancer-engagements/{engagementId}/timesheets/{timesheetId}/review'",
            "post('/api/v1/freelancer-engagements/{engagementId}/claims'",
            "post('/api/v1/freelancer-engagements/{engagementId}/claims/{claimId}/review'",
            "post('/api/v1/freelancer-engagements/{engagementId}/claims/{claimId}/payment-evidence'",
            "post('/api/v1/freelancer-engagements/{engagementId}/deliverables'",
            "post('/api/v1/freelancer-engagements/{engagementId}/deliverables/{deliverableId}/revisions'",
            "post('/api/v1/freelancer-engagements/{engagementId}/deliverables/{deliverableId}/revisions/{revisionId}/sign-off'",
            "post('/api/v1/freelancer-engagements/{engagementId}/deliverables/{deliverableId}/issue'",
            "post('/api/v1/freelancer-engagements/{engagementId}/complete'",
            "post('/api/v1/freelancer-engagements/{engagementId}/reviews'",
        ] as $route) {
            self::assertStringContainsString($route, $routes);
        }

        $factory = (string) file_get_contents(dirname(__DIR__, 2).'/src/Http/AuthenticationKernelFactory.php');
        self::assertStringContainsString('new JobTicketMarketplaceService', $factory);
        self::assertStringContainsString('new JobTicketMarketplaceRoutes', $factory);
        self::assertStringContainsString("new PermissionMiddleware(new PermissionService(),'projects.read')", $factory);
        self::assertStringContainsString("new PermissionMiddleware(new PermissionService(),'projects.write')", $factory);
        self::assertStringContainsString("new PermissionMiddleware(new PermissionService(),'verification.review')", $factory);
    }
}
