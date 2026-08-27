<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\ProposalController;
use Architex\Http\Middleware\PermissionMiddleware;

final readonly class ProposalRoutes
{
    public function __construct(
        private ProposalController $controller,
        private PermissionMiddleware $read,
        private PermissionMiddleware $write,
    ) {}

    public function register(Router $router): void
    {
        $router->get('/api/v1/proposals', fn (Request $request): Response => $this->read->process($request, $this->controller->list(...)));
        $router->post('/api/v1/proposals', fn (Request $request): Response => $this->write->process($request, $this->controller->create(...)));
        $router->get('/api/v1/proposals/{proposalId}', fn (Request $request): Response => $this->read->process($request, $this->controller->get(...)));
        $router->post('/api/v1/proposals/{proposalId}/issue', fn (Request $request): Response => $this->write->process($request, $this->controller->issue(...)));
        $router->post('/api/v1/proposals/{proposalId}/revisions', fn (Request $request): Response => $this->write->process($request, $this->controller->revise(...)));
        $router->get('/api/v1/proposal-acceptance/{token}', $this->controller->publicView(...));
        $router->post('/api/v1/proposal-acceptance/{token}', $this->controller->accept(...));
    }
}
