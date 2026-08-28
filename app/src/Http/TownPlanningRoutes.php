<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\TownPlanningController;
use Architex\Http\Middleware\PermissionMiddleware;

final readonly class TownPlanningRoutes
{
    public function __construct(private TownPlanningController$controller,private PermissionMiddleware$read,private PermissionMiddleware$write){}
    public function register(Router$router):void
    {
        $router->get('/api/v1/planning-applications',fn(Request$r):Response=>$this->read->process($r,$this->controller->portfolio(...)));
        $router->post('/api/v1/planning-applications',fn(Request$r):Response=>$this->write->process($r,$this->controller->createStandalone(...)));
        $router->get('/api/v1/projects/{projectId}/planning-applications',fn(Request$r):Response=>$this->read->process($r,$this->controller->index(...)));
        $router->post('/api/v1/projects/{projectId}/planning-applications',fn(Request$r):Response=>$this->write->process($r,$this->controller->create(...)));
        $router->get('/api/v1/planning-applications/{applicationId}',fn(Request$r):Response=>$this->read->process($r,$this->controller->show(...)));
        $router->post('/api/v1/planning-applications/{applicationId}/entries',fn(Request$r):Response=>$this->write->process($r,$this->controller->entry(...)));
        $router->post('/api/v1/planning-applications/{applicationId}/submit',fn(Request$r):Response=>$this->write->process($r,$this->controller->submit(...)));
        $router->post('/api/v1/planning-applications/{applicationId}/stages',fn(Request$r):Response=>$this->write->process($r,$this->controller->advance(...)));
    }
}
