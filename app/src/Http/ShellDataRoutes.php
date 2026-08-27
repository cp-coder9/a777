<?php
declare(strict_types=1);
namespace Architex\Http;

use Architex\Http\Controller\InboxController;
use Architex\Http\Controller\ToolRegistryController;
use Closure;

final readonly class ShellDataRoutes
{
    public function __construct(private InboxController $inbox, private ToolRegistryController $tools, private Middleware $readPermission) {}
    public function register(Router $router): void
    {
        $router->get('/api/v1/inbox', $this->through($this->readPermission, $this->inbox->index(...)));
        $router->get('/api/v1/tools', $this->through($this->readPermission, $this->tools->index(...)));
    }
    /** @param Closure(Request):Response $handler @return Closure(Request):Response */
    private function through(Middleware $middleware, Closure $handler): Closure
    {
        return static fn (Request $request): Response => $middleware->process($request, $handler);
    }
}
