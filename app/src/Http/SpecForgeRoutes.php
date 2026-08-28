<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\SpecForgeController;
use Architex\Http\Middleware\PermissionMiddleware;

final readonly class SpecForgeRoutes
{
    public function __construct(private SpecForgeController $controller,private PermissionMiddleware $read,private PermissionMiddleware $write){}
    public function register(Router $router):void
    {
        $router->get('/api/v1/projects/{projectId}/specforge-workspaces',fn(Request$r):Response=>$this->read->process($r,$this->controller->index(...)));
        $router->post('/api/v1/projects/{projectId}/specforge-workspaces',fn(Request$r):Response=>$this->write->process($r,$this->controller->create(...)));
        $router->get('/api/v1/specforge-workspaces/{workspaceId}',fn(Request$r):Response=>$this->read->process($r,$this->controller->show(...)));
        $router->post('/api/v1/specforge-workspaces/{workspaceId}/sections',fn(Request$r):Response=>$this->write->process($r,$this->controller->section(...)));
        $router->post('/api/v1/specforge-workspaces/{workspaceId}/items',fn(Request$r):Response=>$this->write->process($r,$this->controller->item(...)));
        $router->post('/api/v1/specforge-items/{itemId}/decisions',fn(Request$r):Response=>$this->write->process($r,$this->controller->decision(...)));
        $router->post('/api/v1/specforge-workspaces/{workspaceId}/issue',fn(Request$r):Response=>$this->write->process($r,$this->controller->issue(...)));
    }
}
