<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\ModuleRecordController;
use Closure;

/**
 * Shared record workflows. PUT and DELETE are real verbs here because the module contracts
 * demand edit and remove, and the shell transport previously only spoke GET/POST.
 */
final readonly class ModuleRecordRoutes
{
    public function __construct(private ModuleRecordController $controller, private Middleware $readPermission, private Middleware $writePermission)
    {
    }

    public function register(Router $router): void
    {
        $router->get('/api/v1/module-records/catalogue', $this->through($this->readPermission, $this->controller->catalogue(...)));
        $router->get('/api/v1/module-records', $this->through($this->readPermission, $this->controller->index(...)));
        $router->get('/api/v1/module-records/{id}', $this->through($this->readPermission, $this->controller->show(...)));
        $router->get('/api/v1/module-records/{id}/events', $this->through($this->readPermission, $this->controller->events(...)));
        $router->post('/api/v1/module-records', $this->through($this->writePermission, $this->controller->create(...)));
        $router->put('/api/v1/module-records/{id}', $this->through($this->writePermission, $this->controller->update(...)));
        $router->put('/api/v1/module-records/{id}/transition', $this->through($this->writePermission, $this->controller->transition(...)));
        $router->delete('/api/v1/module-records/{id}', $this->through($this->writePermission, $this->controller->destroy(...)));
    }

    /** @param Closure(Request):Response $handler @return Closure(Request):Response */
    private function through(Middleware $middleware, Closure $handler): Closure
    {
        return static fn (Request $request): Response => $middleware->process($request, $handler);
    }
}
