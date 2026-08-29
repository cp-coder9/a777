<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\DemoSandboxController;
use Architex\Http\Middleware\PermissionMiddleware;
use Architex\Http\Middleware\RateLimitMiddleware;

final readonly class DemoSandboxRoutes
{
    public function __construct(
        private DemoSandboxController $controller,
        private PermissionMiddleware $read,
        private PermissionMiddleware $manage,
        private RateLimitMiddleware $resetLimit,
    ) {}

    public function register(Router $router): void
    {
        $router->post('/api/v1/demo/sandbox/ensure', fn(Request $request): Response => $this->manage->process($request, $this->controller->ensure(...)));
        $router->get('/api/v1/demo/sandbox', fn(Request $request): Response => $this->read->process($request, $this->controller->state(...)));
        $router->put('/api/v1/demo/sandbox/role', fn(Request $request): Response => $this->read->process($request, $this->controller->role(...)));
        $router->get('/api/v1/demo/sandbox/projects', fn(Request $request): Response => $this->read->process($request, $this->controller->projects(...)));
        $router->get('/api/v1/demo/sandbox/projects/{projectId}', fn(Request $request): Response => $this->read->process($request, $this->controller->project(...)));
        $router->get('/api/v1/demo/sandbox/learning', fn(Request $request): Response => $this->read->process($request, $this->controller->learning(...)));
        $router->post('/api/v1/demo/sandbox/reset', fn(Request $request): Response => $this->manage->process($request, fn(Request $authorised): Response => $this->resetLimit->process($authorised, $this->controller->reset(...))));
    }
}
