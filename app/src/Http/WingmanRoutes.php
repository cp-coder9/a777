<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\WingmanController;
use Architex\Http\Middleware\PermissionMiddleware;
use Architex\Http\Middleware\RateLimitMiddleware;

final readonly class WingmanRoutes
{
    public function __construct(
        private WingmanController $controller,
        private PermissionMiddleware $read,
        private RateLimitMiddleware $messageLimit,
    ) {}

    public function register(Router $router): void
    {
        $router->get('/api/v1/wingman/tutorial', fn (Request $request): Response => $this->read->process($request, $this->controller->tutorial(...)));
        $router->post('/api/v1/wingman/tutorial', fn (Request $request): Response => $this->read->process($request, fn (Request $allowed): Response => $this->messageLimit->process($allowed, $this->controller->updateTutorial(...))));
        $router->get('/api/v1/wingman/bootstrap', fn (Request $request): Response => $this->read->process($request, $this->controller->bootstrap(...)));
        $router->post('/api/v1/wingman/messages', fn (Request $request): Response => $this->read->process($request, fn (Request $allowed): Response => $this->messageLimit->process($allowed, $this->controller->message(...))));
        $router->get('/api/v1/wingman/conversations/{conversationId}', fn (Request $request): Response => $this->read->process($request, $this->controller->conversation(...)));
        $router->post('/api/v1/wingman/suggestions/{suggestionId}/feedback', fn (Request $request): Response => $this->read->process($request, $this->controller->feedback(...)));
    }
}
