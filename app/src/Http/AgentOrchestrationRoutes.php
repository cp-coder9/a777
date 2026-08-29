<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\AgentOrchestrationController;

final readonly class AgentOrchestrationRoutes
{
    public function __construct(private AgentOrchestrationController$controller,private Middleware$read,private Middleware$write,private Middleware$govern) {}

    public function register(Router$router):void
    {
        $router->get('/api/v1/projects/{projectId}/agent-orchestration-workspace',fn(Request$request):Response=>$this->read->process($request,$this->controller->workspace(...)));
        $router->post('/api/v1/projects/{projectId}/agent/bootstrap',fn(Request$request):Response=>$this->write->process($request,$this->controller->bootstrap(...)));
        $router->post('/api/v1/agent/policies',fn(Request$request):Response=>$this->govern->process($request,$this->controller->createPolicy(...)));
        $router->post('/api/v1/projects/{projectId}/agent/routes',fn(Request$request):Response=>$this->govern->process($request,$this->controller->createRoute(...)));
        $router->post('/api/v1/projects/{projectId}/agent/events/route',fn(Request$request):Response=>$this->write->process($request,$this->controller->routeEvent(...)));
        $router->post('/api/v1/projects/{projectId}/agent/message-drafts',fn(Request$request):Response=>$this->write->process($request,$this->controller->createMessageDraft(...)));
        $router->post('/api/v1/agent/approval-gates/{gateId}/decisions',fn(Request$request):Response=>$this->govern->process($request,$this->controller->decideApproval(...)));
    }
}
