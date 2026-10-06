<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\RefuseController;
use Closure;

/**
 * Municipal refuse area calculator. Rule packs are verified by a professional with
 * `verification.review`, which is the only state in which their criteria are applied.
 */
final readonly class RefuseRoutes
{
    public function __construct(
        private RefuseController $controller,
        private Middleware $readPermission,
        private Middleware $writePermission,
        private Middleware $reviewPermission,
    ) {
    }

    public function register(Router $router): void
    {
        $router->get('/api/v1/refuse/catalogue', $this->through($this->readPermission, $this->controller->catalogue(...)));
        $router->get('/api/v1/refuse/rule-packs', $this->through($this->readPermission, $this->controller->rulePacks(...)));
        $router->post('/api/v1/refuse/rule-packs', $this->through($this->writePermission, $this->controller->saveRulePack(...)));
        $router->post('/api/v1/refuse/rule-packs/{id}/review', $this->through($this->reviewPermission, $this->controller->reviewRulePack(...)));

        $router->get('/api/v1/projects/{projectId}/refuse-calculations', $this->through($this->readPermission, $this->controller->index(...)));
        $router->get('/api/v1/refuse-calculations/{id}', $this->through($this->readPermission, $this->controller->show(...)));
        $router->post('/api/v1/projects/{projectId}/refuse-calculations', $this->through($this->writePermission, $this->controller->create(...)));
        $router->put('/api/v1/refuse-calculations/{id}', $this->through($this->writePermission, $this->controller->update(...)));
        $router->post('/api/v1/refuse-calculations/{id}/declare', $this->through($this->writePermission, $this->controller->declare(...)));
        $router->delete('/api/v1/refuse-calculations/{id}', $this->through($this->writePermission, $this->controller->destroy(...)));
    }

    /** @param Closure(Request):Response $handler @return Closure(Request):Response */
    private function through(Middleware $middleware, Closure $handler): Closure
    {
        return static fn (Request $request): Response => $middleware->process($request, $handler);
    }
}
