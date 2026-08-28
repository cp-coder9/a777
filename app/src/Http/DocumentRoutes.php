<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\DocumentController;
use Architex\Http\Middleware\PermissionMiddleware;

final readonly class DocumentRoutes
{
    public function __construct(private DocumentController $controller,private PermissionMiddleware $read,private PermissionMiddleware $write){}
    public function register(Router $router):void
    {
        $router->get('/api/v1/documents',fn(Request$r):Response=>$this->read->process($r,$this->controller->index(...)));
        $router->post('/api/v1/documents',fn(Request$r):Response=>$this->write->process($r,$this->controller->create(...)));
        $router->get('/api/v1/documents/{revisionId}',fn(Request$r):Response=>$this->read->process($r,$this->controller->show(...)));
        $router->post('/api/v1/documents/{revisionId}/draft',fn(Request$r):Response=>$this->write->process($r,$this->controller->updateDraft(...)));
        $router->post('/api/v1/documents/{revisionId}/revisions',fn(Request$r):Response=>$this->write->process($r,$this->controller->revise(...)));
        $router->post('/api/v1/documents/{revisionId}/submit-review',fn(Request$r):Response=>$this->write->process($r,$this->controller->submitReview(...)));
        $router->post('/api/v1/documents/{revisionId}/approve',fn(Request$r):Response=>$this->write->process($r,$this->controller->approve(...)));
        $router->post('/api/v1/documents/{revisionId}/issue',fn(Request$r):Response=>$this->write->process($r,$this->controller->issue(...)));
        $router->get('/api/v1/projects/{projectId}/document-readiness',fn(Request$r):Response=>$this->read->process($r,$this->controller->readiness(...)));
    }
}
