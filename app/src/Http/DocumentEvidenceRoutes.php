<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\DocumentEvidenceController;
use Architex\Http\Middleware\PermissionMiddleware;

final readonly class DocumentEvidenceRoutes
{
    public function __construct(private DocumentEvidenceController $controller, private PermissionMiddleware $read, private PermissionMiddleware $write) {}

    public function register(Router $router): void
    {
        $router->get('/api/v1/document-transmittals', fn (Request $request): Response => $this->read->process($request, $this->controller->transmittalIndex(...)));
        $router->post('/api/v1/document-transmittals', fn (Request $request): Response => $this->write->process($request, $this->controller->transmittalCreate(...)));
        $router->get('/api/v1/document-transmittals/{transmittalId}', fn (Request $request): Response => $this->read->process($request, $this->controller->transmittalShow(...)));
        $router->post('/api/v1/document-transmittals/{transmittalId}/issue', fn (Request $request): Response => $this->write->process($request, $this->controller->transmittalIssue(...)));
        $router->get('/api/v1/drawing-intelligence-candidates', fn (Request $request): Response => $this->read->process($request, $this->controller->candidateIndex(...)));
        $router->post('/api/v1/drawing-intelligence-candidates', fn (Request $request): Response => $this->write->process($request, $this->controller->candidateCreate(...)));
        $router->get('/api/v1/drawing-intelligence-candidates/{candidateId}', fn (Request $request): Response => $this->read->process($request, $this->controller->candidateShow(...)));
        $router->post('/api/v1/drawing-intelligence-candidates/{candidateId}/review', fn (Request $request): Response => $this->write->process($request, $this->controller->candidateReview(...)));
    }
}
