<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\ComplianceController;
use Closure;

final readonly class ComplianceRoutes
{
    public function __construct(private ComplianceController $controller,private Middleware $read,private Middleware $govern) {}

    public function register(Router $router):void
    {
        $router->get('/api/v1/compliance-rule-versions',$this->through($this->read,$this->controller->list(...)));
        $router->post('/api/v1/compliance-rule-versions/seed',$this->through($this->govern,$this->controller->seed(...)));
        $router->post('/api/v1/compliance-rule-versions',$this->through($this->govern,$this->controller->create(...)));
        $router->post('/api/v1/compliance-rule-versions/{ruleVersionId}/verify',$this->through($this->govern,$this->controller->verify(...)));
    }

    /** @param Closure(Request):Response $handler @return Closure(Request):Response */
    private function through(Middleware$middleware,Closure$handler):Closure{return static fn(Request$request):Response=>$middleware->process($request,$handler);}
}
