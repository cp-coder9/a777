<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\ControlledRegisterController;

final readonly class ControlledRegisterRoutes
{
    public function __construct(private ControlledRegisterController $controller, private Middleware $read, private Middleware $write) {}

    public function register(Router $router): void
    {
        $router->get('/api/v1/projects/{projectId}/controlled-registers/{registerKey}', fn(Request $request):Response=>$this->read->process($request,$this->controller->workspace(...)));
        $router->post('/api/v1/projects/{projectId}/controlled-registers/{registerKey}', fn(Request $request):Response=>$this->write->process($request,$this->controller->create(...)));
        $router->post('/api/v1/controlled-registers/{entryId}/transition', fn(Request $request):Response=>$this->write->process($request,$this->controller->transition(...)));
    }
}
