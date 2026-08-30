<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\NuancedQcController;

final readonly class NuancedQcRoutes
{
    public function __construct(private NuancedQcController $controller, private Middleware $read, private Middleware $write) {}

    public function register(Router $router): void
    {
        $router->get('/api/v1/nuanced-qc', fn (Request $request): Response => $this->read->process($request, $this->controller->workspace(...)));
        $router->post('/api/v1/nuanced-qc/runs', fn (Request $request): Response => $this->write->process($request, $this->controller->createRun(...)));
        $router->post('/api/v1/nuanced-qc/runs/{runId}/summary', fn (Request $request): Response => $this->write->process($request, $this->controller->recordSummary(...)));
        $router->post('/api/v1/nuanced-qc/runs/{runId}/categories', fn (Request $request): Response => $this->write->process($request, $this->controller->recordCategory(...)));
        $router->post('/api/v1/nuanced-qc/runs/{runId}/findings', fn (Request $request): Response => $this->write->process($request, $this->controller->addFinding(...)));
        $router->post('/api/v1/nuanced-qc/runs/{runId}/evidence', fn (Request $request): Response => $this->write->process($request, $this->controller->linkEvidence(...)));
        $router->post('/api/v1/nuanced-qc/findings/{findingId}/retests', fn (Request $request): Response => $this->write->process($request, $this->controller->createRetest(...)));
        $router->post('/api/v1/nuanced-qc/retests/{retestId}/complete', fn (Request $request): Response => $this->write->process($request, $this->controller->completeRetest(...)));
        $router->post('/api/v1/nuanced-qc/risks/{riskId}/accept', fn (Request $request): Response => $this->write->process($request, $this->controller->acceptRisk(...)));
        $router->post('/api/v1/nuanced-qc/runs/{runId}/signoffs', fn (Request $request): Response => $this->write->process($request, $this->controller->signOff(...)));
        $router->post('/api/v1/nuanced-qc/roles', fn (Request $request): Response => $this->write->process($request, $this->controller->assignRole(...)));
    }
}
