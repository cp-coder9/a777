<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\BriefController;
use Closure;

final readonly class BriefRoutes
{
    public function __construct(private BriefController $controller,private Middleware $read,private Middleware $write){}
    public function register(Router $router):void
    {
        $router->get('/api/v1/guided-briefs',$this->through($this->read,$this->controller->index(...)));
        $router->post('/api/v1/guided-briefs',$this->through($this->write,$this->controller->create(...)));
        $router->post('/api/v1/guided-briefs/{briefId}',$this->through($this->write,$this->controller->save(...)));
        $router->post('/api/v1/guided-briefs/{briefId}/submit',$this->through($this->write,$this->controller->submit(...)));
        $router->post('/api/v1/guided-briefs/{briefId}/assign',$this->through($this->write,$this->controller->assign(...)));
    }
    /** @param Closure(Request):Response $handler @return Closure(Request):Response */
    private function through(Middleware $middleware,Closure $handler):Closure{return static fn(Request $request):Response=>$middleware->process($request,$handler);}
}
