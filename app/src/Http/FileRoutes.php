<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\FileController;
use Closure;

final readonly class FileRoutes
{
    public function __construct(private FileController $controller, private Middleware $readPermission, private Middleware $writePermission) {}

    public function register(Router $router): void
    {
        $router->post('/api/v1/projects/{projectId}/files', $this->through($this->writePermission, $this->controller->upload(...)));
        $router->get('/api/v1/files/{fileId}', $this->through($this->readPermission, $this->controller->download(...)));
    }

    /** @param Closure(Request):Response $handler @return Closure(Request):Response */
    private function through(Middleware $middleware, Closure $handler): Closure
    {
        return static fn (Request $request): Response => $middleware->process($request, $handler);
    }
}
