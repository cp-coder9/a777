<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\TrustController;
use Closure;

final readonly class TrustRoutes
{
    public function __construct(
        private TrustController $controller,
        private Middleware $readPermission,
        private Middleware $writePermission,
        private Middleware $reviewPermission,
    ) {}

    public function register(Router $router): void
    {
        $router->get('/api/v1/professional-profile', $this->through($this->readPermission, $this->controller->profile(...)));
        $router->post('/api/v1/professional-profile', $this->through($this->writePermission, $this->controller->saveProfile(...)));
        $router->get('/api/v1/verifications', $this->through($this->readPermission, $this->controller->verifications(...)));
        $router->post('/api/v1/verifications', $this->through($this->writePermission, $this->controller->submit(...)));
        $router->post('/api/v1/verifications/{verificationId}/review', $this->through($this->reviewPermission, $this->controller->review(...)));
        $router->get('/api/v1/directory/search', $this->through($this->readPermission, $this->controller->directory(...)));
    }

    /** @param Closure(Request):Response $handler @return Closure(Request):Response */
    private function through(Middleware $middleware, Closure $handler): Closure
    {
        return static fn(Request $request): Response => $middleware->process($request, $handler);
    }
}
