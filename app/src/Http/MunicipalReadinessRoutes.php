<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\MunicipalReadinessController;
use Closure;

final readonly class MunicipalReadinessRoutes
{
    public function __construct(private MunicipalReadinessController$controller,private Middleware$read,private Middleware$write,private Middleware$govern){}

    public function register(Router$router):void
    {
        $router->get('/api/v1/municipal-requirement-versions',$this->through($this->read,$this->controller->matrices(...)));
        $router->post('/api/v1/municipal-requirement-versions/seed',$this->through($this->govern,$this->controller->seed(...)));
        $router->post('/api/v1/municipal-requirement-versions',$this->through($this->govern,$this->controller->createMatrix(...)));
        $router->post('/api/v1/municipal-requirement-versions/{matrixVersionId}/verify',$this->through($this->govern,$this->controller->verifyMatrix(...)));
        $router->get('/api/v1/projects/{projectId}/submission-readiness',$this->through($this->read,$this->controller->history(...)));
        $router->post('/api/v1/projects/{projectId}/submission-readiness',$this->through($this->write,$this->controller->assess(...)));
        $router->get('/api/v1/submission-readiness/{assessmentId}',$this->through($this->read,$this->controller->show(...)));
        $router->post('/api/v1/submission-readiness/{assessmentId}/approve',$this->through($this->write,$this->controller->approve(...)));
    }

    /** @param Closure(Request):Response $handler @return Closure(Request):Response */
    private function through(Middleware$middleware,Closure$handler):Closure{return static fn(Request$request):Response=>$middleware->process($request,$handler);}
}
