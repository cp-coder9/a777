<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\FinanceController;
use Architex\Http\Middleware\PermissionMiddleware;

final readonly class FinanceRoutes
{
    public function __construct(private FinanceController $controller, private PermissionMiddleware $read, private PermissionMiddleware $write, private Middleware $webhookLimit) {}

    public function register(Router $router): void
    {
        $router->get('/api/v1/projects/{projectId}/finance-workspace', fn(Request $request): Response => $this->read->process($request, $this->controller->workspace(...)));
        $router->post('/api/v1/projects/{projectId}/commercial-baselines', fn(Request $request): Response => $this->write->process($request, $this->controller->createBaseline(...)));
        $router->post('/api/v1/commercial-baselines/{baselineId}/payment-schedules', fn(Request $request): Response => $this->write->process($request, $this->controller->createSchedule(...)));
        $router->post('/api/v1/commercial-baselines/{baselineId}/variations', fn(Request $request): Response => $this->write->process($request, $this->controller->createVariation(...)));
        $router->post('/api/v1/commercial-variations/{variationId}/submit', fn(Request $request): Response => $this->write->process($request, $this->controller->submitVariation(...)));
        $router->post('/api/v1/commercial-variations/{variationId}/approvals', fn(Request $request): Response => $this->write->process($request, $this->controller->decideVariation(...)));
        $router->post('/api/v1/commercial-baselines/{baselineId}/claims', fn(Request $request): Response => $this->write->process($request, $this->controller->submitClaim(...)));
        $router->post('/api/v1/commercial-claims/{claimId}/certificates', fn(Request $request): Response => $this->write->process($request, $this->controller->certifyClaim(...)));
        $router->get('/api/v1/commercial-baselines/{baselineId}/retention', fn(Request $request): Response => $this->read->process($request, $this->controller->retention(...)));
        $router->post('/api/v1/commercial-baselines/{baselineId}/cashflow-snapshots', fn(Request $request): Response => $this->write->process($request, $this->controller->createCashflow(...)));
        $router->post('/api/v1/commercial-baselines/{baselineId}/retention-release-requests', fn(Request $request): Response => $this->write->process($request, $this->controller->createRetentionRelease(...)));
        $router->post('/api/v1/retention-release-requests/{retentionReleaseId}/approvals', fn(Request $request): Response => $this->write->process($request, $this->controller->approveRetentionRelease(...)));
        $router->post('/api/v1/financial-providers', fn(Request $request): Response => $this->write->process($request, $this->controller->registerProvider(...)));
        $router->get('/api/v1/financial-providers/{providerId}/capabilities', fn(Request $request): Response => $this->read->process($request, $this->controller->providerCapabilities(...)));
        $router->post('/api/v1/provider-release-requests/{releaseRequestId}/submit', fn(Request $request): Response => $this->write->process($request, $this->controller->submitRelease(...)));
        $router->post('/api/v1/payment-certificates/{certificateRevisionId}/release-requests', fn(Request $request): Response => $this->write->process($request, $this->controller->createReleaseRequest(...)));
        $router->post('/api/v1/provider-release-requests/{releaseRequestId}/approvals', fn(Request $request): Response => $this->write->process($request, $this->controller->approveReleaseRequest(...)));
        $router->post('/api/v1/webhooks/finance/{providerId}', fn(Request $request): Response => $this->webhookLimit->process($request, $this->controller->providerWebhook(...)));
    }
}
