<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\TeamController;
use Closure;

final readonly class TeamRoutes
{
    public function __construct(private TeamController $controller,private Middleware $read,private Middleware $write){}
    public function register(Router $router):void
    {
        $router->get('/api/v1/projects/{projectId}/team',$this->through($this->read,$this->controller->project(...)));
        $router->post('/api/v1/projects/{projectId}/team',$this->through($this->write,$this->controller->invite(...)));
        $router->delete('/api/v1/projects/{projectId}/team/{teamMemberId}',$this->through($this->write,$this->controller->remove(...)));
        $router->get('/api/v1/team-invitations',$this->controller->pending(...));
        $router->post('/api/v1/team-invitations/{teamMemberId}/accept',$this->controller->accept(...));
    }
    /** @param Closure(Request):Response $handler @return Closure(Request):Response */
    private function through(Middleware $middleware,Closure $handler):Closure{return static fn(Request $request):Response=>$middleware->process($request,$handler);}
}
