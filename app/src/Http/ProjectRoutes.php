<?php
declare(strict_types=1);
namespace Architex\Http;

use Architex\Http\Controller\ProjectController;
use Closure;

final readonly class ProjectRoutes
{
    public function __construct(private ProjectController $controller, private Middleware $readPermission, private Middleware $writePermission) {}
    public function register(Router $router): void
    {
        $router->get('/api/v1/projects', $this->through($this->readPermission, $this->controller->index(...)));
        $router->get('/api/v1/projects/{projectId}', $this->through($this->readPermission, $this->controller->show(...)));
        $router->post('/api/v1/projects', $this->through($this->writePermission, $this->controller->create(...)));
        $router->post('/api/v1/projects/{projectId}/details', $this->through($this->writePermission, $this->controller->updateDetails(...)));
        $router->post('/api/v1/projects/transition', $this->through($this->writePermission, $this->controller->transition(...)));
    }
    /** @param Closure(Request):Response $handler @return Closure(Request):Response */
    private function through(Middleware $middleware, Closure $handler): Closure
    {
        return static fn (Request $request): Response => $middleware->process($request, $handler);
    }
}
